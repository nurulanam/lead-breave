<?php

namespace App\Services\Website;

/**
 * Finds email addresses on a page and scores each 0–100 by how likely it is
 * to reach someone at the business. Ported from LeadMap's Email_Extractor.
 */
class EmailExtractor
{
    /** Inboxes that reach a desk, not a person. Usable, but ranked lower. */
    private const ROLE_ACCOUNTS = [
        'info', 'contact', 'hello', 'hi', 'sales', 'support', 'help', 'admin', 'office', 'enquiries',
        'inquiries', 'mail', 'team', 'service', 'services', 'reception', 'bookings', 'appointments',
        'general', 'ask', 'webmaster',
    ];

    /** Addresses belonging to tools embedded in the page, never to the business. */
    private const JUNK_DOMAINS = [
        'example.com', 'example.org', 'example.net', 'domain.com', 'yourdomain.com', 'email.com',
        'sentry.io', 'wixpress.com', 'wix.com', 'squarespace.com', 'godaddy.com', 'wordpress.org',
        'w3.org', 'schema.org', 'sentry-cdn.com', 'test.com', 'company.com', 'site.com',
    ];

    private const JUNK_LOCALS = [
        'email', 'youremail', 'your-email', 'name', 'yourname', 'user', 'username', 'someone',
        'sample', 'test', 'noreply', 'no-reply', 'donotreply', 'do-not-reply',
    ];

    private const FILE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'css', 'js', 'woff', 'woff2', 'ttf'];

    private const FREE_MAILBOXES = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'outlook.com', 'live.com',
        'msn.com', 'aol.com', 'icloud.com', 'me.com', 'mail.com', 'gmx.com', 'protonmail.com', 'proton.me', 'yandex.com',
    ];

    /**
     * @return array<string, EmailCandidate> keyed by address
     */
    public function extract(string $html, string $siteDomain, string $pageKind, string $pageUrl): array
    {
        $found = [];
        $text = self::toText($html);

        $sources = [
            'mailto' => $this->fromMailto($html),
            'json_ld' => $this->fromJsonLd($html),
            'cloudflare' => $this->fromCloudflare($html),
            'text' => $this->fromText($text),
            'obfuscated' => $this->fromObfuscated($text),
        ];

        foreach ($sources as $source => $emails) {
            foreach ($emails as $email) {
                $email = $this->normalize($email);

                if ($email === '' || ! $this->isPlausible($email)) {
                    continue;
                }

                $local = strstr($email, '@', true) ?: '';
                $isRole = in_array($local, self::ROLE_ACCOUNTS, true);
                $candidate = new EmailCandidate($email, $source, $this->score($email, $source, $siteDomain, $pageKind, $isRole), $isRole, $pageUrl);

                if (! isset($found[$email]) || $candidate->confidence > $found[$email]->confidence) {
                    $found[$email] = $candidate;
                }
            }
        }

        return $found;
    }

    /**
     * Combine the candidates from several pages, keeping the best evidence for each address.
     *
     * @param  list<array<string, EmailCandidate>>  $sets
     * @return list<EmailCandidate> best first
     */
    public function merge(array $sets): array
    {
        $merged = [];

        foreach ($sets as $set) {
            foreach ($set as $email => $candidate) {
                if (! isset($merged[$email]) || $candidate->confidence > $merged[$email]->confidence) {
                    $merged[$email] = $candidate;
                }
            }
        }

        usort($merged, fn (EmailCandidate $a, EmailCandidate $b): int => $b->confidence <=> $a->confidence ?: strcmp($a->email, $b->email));

        return $merged;
    }

    /**
     * The dominant signal is whether the address is on the site's own domain:
     * a Gmail address in a footer is more often the web designer's. A shared
     * inbox always ranks below a named person, even on the contact page.
     */
    public function score(string $email, string $source, string $siteDomain, string $pageKind, bool $isRole): int
    {
        $domain = substr((string) strrchr($email, '@'), 1);
        $score = 35;

        $score += match (true) {
            $siteDomain !== '' && $this->sameSite($domain, $siteDomain) => 40,
            in_array($domain, self::FREE_MAILBOXES, true) => 5,
            default => -15,
        };

        $score += match ($source) {
            'mailto', 'cloudflare' => 25,
            'json_ld' => 20,
            'obfuscated' => 10,
            default => 0,
        };

        $score += match ($pageKind) {
            'contact' => 15,
            'about' => 8,
            'home' => 4,
            default => 0,
        };

        return max(0, min(100, $score - ($isRole ? 30 : 0)));
    }

    /**
     * Cloudflare's email obfuscation: a hex string XOR-ed with its first byte.
     */
    public static function decodeCloudflare(string $hex): string
    {
        if (strlen($hex) < 8 || strlen($hex) % 2 !== 0 || ! ctype_xdigit($hex)) {
            return '';
        }

        $bytes = (string) hex2bin($hex);
        $key = ord($bytes[0]);
        $out = '';

        for ($i = 1, $count = strlen($bytes); $i < $count; $i++) {
            $char = ord($bytes[$i]) ^ $key;

            if ($char < 32 || $char > 126) {
                return '';
            }

            $out .= chr($char);
        }

        return $out;
    }

    /**
     * Visible text only: scripts, styles and tags removed.
     */
    public static function toText(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = (string) preg_replace('#<[^>]+>#', ' ', $html);

        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private function fromMailto(string $html): array
    {
        return preg_match_all('/mailto:\s*([^"\'>\s?&]+)/i', $html, $matches) ? array_map('rawurldecode', $matches[1]) : [];
    }

    /**
     * @return list<string>
     */
    private function fromJsonLd(string $html): array
    {
        if (! preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $matches)) {
            return [];
        }

        $emails = [];

        foreach ($matches[1] as $block) {
            $data = json_decode(trim($block), true);

            if (is_array($data)) {
                array_walk_recursive($data, function (mixed $value, mixed $key) use (&$emails): void {
                    if (is_string($value) && strtolower((string) $key) === 'email') {
                        $emails[] = str_replace('mailto:', '', $value);
                    }
                });
            }
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    private function fromCloudflare(string $html): array
    {
        preg_match_all('/data-cfemail\s*=\s*["\']([0-9a-f]+)["\']/i', $html, $attributes);
        preg_match_all('#/cdn-cgi/l/email-protection\#([0-9a-f]+)#i', $html, $links);

        return array_values(array_filter(array_map(self::decodeCloudflare(...), array_unique([...$attributes[1], ...$links[1]]))));
    }

    /**
     * @return list<string>
     */
    private function fromText(string $text): array
    {
        return preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,24}/i', $text, $matches) ? $matches[0] : [];
    }

    /**
     * "name [at] domain [dot] com" and similar hand-rolled disguises.
     *
     * @return list<string>
     */
    private function fromObfuscated(string $text): array
    {
        $decoded = $text;

        foreach ([
            '/\s*[\(\[\{<]\s*at\s*[\)\]\}>]\s*/i' => '@',
            '/\s*[\(\[\{<]\s*dot\s*[\)\]\}>]\s*/i' => '.',
            '/\s+at\s+/i' => '@',
            '/\s+dot\s+/i' => '.',
            '/\s*%40\s*/i' => '@',
        ] as $pattern => $replacement) {
            $decoded = (string) preg_replace($pattern, $replacement, $decoded);
        }

        return $decoded === $text ? [] : $this->fromText($decoded);
    }

    private function normalize(string $email): string
    {
        $email = html_entity_decode(trim($email), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_strtolower(trim($email, " \t\n\r\x0B.,;:<>()[]{}\"'"));
    }

    private function isPlausible(string $email): bool
    {
        if (strlen($email) > 191 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        [$local, $domain] = explode('@', $email, 2);

        if (in_array($local, self::JUNK_LOCALS, true) || preg_match('/^[0-9a-f]{16,}$/i', $local)) {
            return false;
        }

        foreach (self::JUNK_DOMAINS as $junk) {
            if ($domain === $junk || str_ends_with($domain, '.'.$junk)) {
                return false;
            }
        }

        return ! in_array(substr((string) strrchr($domain, '.'), 1), self::FILE_EXTENSIONS, true);
    }

    private function sameSite(string $emailDomain, string $siteDomain): bool
    {
        $emailDomain = (string) preg_replace('/^www\./', '', strtolower($emailDomain));
        $siteDomain = (string) preg_replace('/^www\./', '', strtolower($siteDomain));

        return $emailDomain === $siteDomain
            || str_ends_with($siteDomain, '.'.$emailDomain)
            || str_ends_with($emailDomain, '.'.$siteDomain);
    }
}
