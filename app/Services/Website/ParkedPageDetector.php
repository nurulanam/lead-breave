<?php

namespace App\Services\Website;

/**
 * Spots domains with no real business site: parked pages, "for sale"
 * notices, expired or suspended hosting, and placeholders.
 */
class ParkedPageDetector
{
    /**
     * Strong signs, trusted wherever they appear on the page.
     *
     * @var array<string, string> pattern => reason
     */
    private const STRONG = [
        '/\b(this|the) domain (name )?(is|may be) for sale\b|\bbuy this domain\b|\bdomain parking\b|\bthis web page is parked\b|\bparked free\b/' => 'The domain is parked or for sale.',
        '/\b(sedoparking|parkingcrew|bodis|afternic|hugedomains)\.com\b|\/\/(www\.)?dan\.com\b/' => 'The domain is parked or for sale.',
        '/\b(this )?domain (name )?has expired\b|\brenew this domain\b/' => 'The domain has expired.',
        '/\b(this )?account has been suspended\b|\baccount suspended\b/' => 'The hosting account is suspended.',
        '/\bapache2 (ubuntu|debian) default page\b|\bwelcome to nginx!?\b|\bdefault web site page\b|\bit works!\s*$/' => 'The server shows a default page, not a website.',
    ];

    /**
     * Weak signs, only trusted on a page with almost no other text.
     *
     * @var array<string, string>
     */
    private const WEAK = [
        '/\b(website |site )?coming soon\b|\bunder construction\b|\bfuture home of\b|\blaunching soon\b/' => 'The site is a placeholder ("coming soon" or under construction).',
    ];

    private const NEARLY_EMPTY_CHARS = 600;

    /**
     * The reason the page is not a real site, or null when it looks genuine.
     */
    public function reason(FetchResult $page): ?string
    {
        $text = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', EmailExtractor::toText($page->body))));
        $html = mb_strtolower($page->body);

        foreach (self::STRONG as $pattern => $reason) {
            if (preg_match($pattern, $text) || preg_match($pattern, $html)) {
                return $reason;
            }
        }

        if (mb_strlen($text) > self::NEARLY_EMPTY_CHARS) {
            return null;
        }

        foreach (self::WEAK as $pattern => $reason) {
            if (preg_match($pattern, $text)) {
                return $reason;
            }
        }

        return mb_strlen($text) < 40 && ! preg_match('#<script[^>]+src#i', $page->body) ? 'The home page is nearly empty.' : null;
    }
}
