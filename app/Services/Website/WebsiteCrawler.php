<?php

namespace App\Services\Website;

/**
 * Visits a business website: the home page plus up to four likely contact,
 * about or team pages. Deliberately shallow; going deeper finds few extra
 * emails and loads their server. Ported from LeadMap's Website_Crawler.
 */
class WebsiteCrawler
{
    private const MAX_EXTRA_PAGES = 4;

    private const MAX_PROBES = 4;

    /**
     * Squashed fragments that suggest a contact page, best first. Squashing
     * (letters and digits only) makes "/Contact-Us.aspx" and "/contactus.htm" equal.
     *
     * @var array<string, 'contact'|'about'|'other'>
     */
    private const PATTERNS = [
        'contactus' => 'contact', 'contact' => 'contact', 'getintouch' => 'contact', 'reachus' => 'contact',
        'reachout' => 'contact', 'findus' => 'contact', 'enquir' => 'contact', 'inquir' => 'contact',
        'bookus' => 'contact', 'emailus' => 'contact', 'callus' => 'contact', 'requestquote' => 'contact',
        'requestaquote' => 'contact', 'freequote' => 'contact', 'getquote' => 'contact', 'getaquote' => 'contact',
        'askforaquote' => 'contact', 'kontakt' => 'contact', 'contacto' => 'contact', 'contactenos' => 'contact',
        'contatti' => 'contact', 'contattaci' => 'contact', 'contato' => 'contact', 'contactez' => 'contact',
        'nouscontacter' => 'contact', 'impressum' => 'contact', 'coordonnees' => 'contact',
        'aboutus' => 'about', 'about' => 'about', 'ourteam' => 'about', 'meettheteam' => 'about',
        'ourpeople' => 'about', 'ourstaff' => 'about', 'team' => 'about', 'staff' => 'about',
        'management' => 'about', 'quienessomos' => 'about', 'chisiamo' => 'about', 'ueberuns' => 'about',
        'support' => 'other', 'customerservice' => 'other', 'help' => 'other', 'locations' => 'other', 'ourlocation' => 'other',
    ];

    /** Look-alikes that are not contact pages ("contact lenses" on an optician's site). */
    private const FALSE_FRIENDS = ['contactlens', 'contactless', 'contactform7', 'aboutcookies', 'aboutourservices', 'teambuilding', 'supportgroup'];

    /** Tried when the site links to no recognisable contact page. */
    private const COMMON_PATHS = [
        '/contact', '/contact.html', '/contact-us', '/contact-us.html', '/contactus.html',
        '/contact.php', '/contact.htm', '/contact-us.php', '/contact.aspx', '/about-us.html', '/about.html',
    ];

    public function __construct(private Fetcher $fetcher, private UrlGuard $guard) {}

    /**
     * Fetch the home page. Failures surface as exceptions, so the caller can
     * tell a dead domain from a slow one.
     *
     * @throws BlockedUrl
     * @throws FetchFailed
     */
    public function home(string $website): FetchResult
    {
        $this->fetcher->setBudget((int) config('services.website_check.budget_seconds'));

        return $this->fetcher->fetch($website);
    }

    /**
     * The contact-like pages linked from (or commonly found next to) the home page.
     *
     * @param  (callable(int $current, int $total): void)|null  $onPage  told before each page is read
     * @return list<CrawledPage>
     */
    public function contactPages(FetchResult $home, ?callable $onPage = null): array
    {
        if (! $home->ok() || ! $home->isHtml() || $this->fetcher->outOfTime()) {
            return [];
        }

        $robots = $this->robots($home->finalUrl);
        $pages = [];
        $links = $this->contactLinks($home->body, $home->finalUrl);
        $total = min(count($links), self::MAX_EXTRA_PAGES);
        $current = 0;

        foreach ($links as $url => $kind) {
            if (count($pages) >= self::MAX_EXTRA_PAGES || $this->fetcher->outOfTime()) {
                break;
            }

            if ($onPage !== null) {
                $onPage(++$current, $total);
            }

            if (! $robots->allows((string) (parse_url($url, PHP_URL_PATH) ?: '/'))) {
                continue;
            }

            if ($page = $this->tryFetch($url)) {
                $pages[] = new CrawledPage($kind, $page);
            }
        }

        return $pages === [] ? $this->probe($home->finalUrl, $robots) : $pages;
    }

    /**
     * Internal links that look like contact or about pages, best first.
     *
     * @return array<string, 'contact'|'about'|'other'> absolute URL => page kind
     */
    public function contactLinks(string $html, string $base): array
    {
        if (! preg_match_all('#<a\b([^>]*)href\s*=\s*["\']([^"\']+)["\']([^>]*)>(.*?)</a>#is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $baseHost = strtolower((string) parse_url($base, PHP_URL_HOST));
        $found = [];

        foreach ($matches as [, $before, $href, $after, $inner]) {
            $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript|sms):/i', $href)) {
                continue;
            }

            $absolute = Fetcher::absolutize($href, $base);

            if ($absolute === null || strtolower((string) parse_url($absolute, PHP_URL_HOST)) !== $baseHost || ! $this->guard->looksPublic($absolute)) {
                continue;
            }

            $kind = $this->classify($href, $inner, $before.' '.$after);
            $clean = strtok($absolute, '#') ?: $absolute;

            if ($kind !== null && ! isset($found[$clean])) {
                $found[$clean] = $kind;
            }
        }

        $rank = ['contact' => 0, 'about' => 1, 'other' => 2];
        uasort($found, fn (string $a, string $b): int => $rank[$a] <=> $rank[$b]);

        return $found;
    }

    /**
     * @return list<CrawledPage>
     */
    private function probe(string $base, Robots $robots): array
    {
        $parts = parse_url($base);

        if (! is_array($parts) || empty($parts['host'])) {
            return [];
        }

        $root = ($parts['scheme'] ?? 'https').'://'.$parts['host'];
        $tried = 0;

        foreach (self::COMMON_PATHS as $path) {
            if ($tried >= self::MAX_PROBES || $this->fetcher->outOfTime()) {
                break;
            }

            if (! $robots->allows($path)) {
                continue;
            }

            $tried++;
            $page = $this->tryFetch($root.$path);

            if ($page !== null && rtrim($page->finalUrl, '/') !== rtrim($base, '/')) {
                return [new CrawledPage(str_contains($path, 'about') ? 'about' : 'contact', $page)];
            }
        }

        return [];
    }

    private function robots(string $base): Robots
    {
        $parts = parse_url($base);

        if (! is_array($parts) || empty($parts['host'])) {
            return new Robots;
        }

        $page = $this->tryFetch(($parts['scheme'] ?? 'https').'://'.$parts['host'].'/robots.txt', requireHtml: false);

        return new Robots($page === null ? '' : $page->body);
    }

    private function tryFetch(string $url, bool $requireHtml = true): ?FetchResult
    {
        try {
            $page = $this->fetcher->fetch($url);
        } catch (BlockedUrl|FetchFailed) {
            return null;
        }

        return $page->ok() && (! $requireHtml || $page->isHtml()) ? $page : null;
    }

    /**
     * @return 'contact'|'about'|'other'|null
     */
    private function classify(string $href, string $innerHtml, string $attributes): ?string
    {
        $text = strip_tags($innerHtml);

        if (preg_match_all('/\b(?:alt|title|aria-label)\s*=\s*["\']([^"\']*)["\']/i', $innerHtml.' '.$attributes, $labels)) {
            $text .= ' '.implode(' ', $labels[1]);
        }

        $parts = parse_url($href);
        $path = is_array($parts) ? ($parts['path'] ?? '').' '.($parts['query'] ?? '') : $href;
        $haystack = self::squash(rawurldecode(trim($path)).' '.$text);

        if ($haystack === '') {
            return null;
        }

        foreach (self::FALSE_FRIENDS as $trap) {
            if (str_contains($haystack, $trap)) {
                return null;
            }
        }

        foreach (self::PATTERNS as $needle => $kind) {
            if (str_contains($haystack, $needle)) {
                return $kind;
            }
        }

        return null;
    }

    private static function squash(string $value): string
    {
        $value = mb_strtolower(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = (string) (transliterator_transliterate('Any-Latin; Latin-ASCII', $value) ?: $value);

        return (string) preg_replace('/[^a-z0-9]+/', '', $value);
    }
}
