<?php

namespace App\Support;

class Domain
{
    /**
     * Hosts many unrelated businesses share, so a match on them is not a duplicate.
     *
     * @var list<string>
     */
    private const SHARED_HOSTS = [
        'facebook.com', 'instagram.com', 'linkedin.com', 'twitter.com', 'x.com', 'yelp.com',
        'google.com', 'business.site', 'sites.google.com', 'wixsite.com', 'weebly.com',
        'blogspot.com', 'wordpress.com', 'linktr.ee', 'youtube.com', 'tiktok.com',
        'squarespace.com', 'godaddysites.com', 'square.site', 'nextdoor.com',
    ];

    /**
     * The registrable host of a URL, lowercased and without "www.", or null
     * when the value is not a usable web address.
     */
    public static function fromUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'http://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! str_contains($host, '.')) {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Whether the domain belongs to a platform many businesses share, such as
     * facebook.com or a free site builder.
     */
    public static function isShared(?string $domain): bool
    {
        if (blank($domain)) {
            return true;
        }

        foreach (self::SHARED_HOSTS as $host) {
            if ($domain === $host || str_ends_with($domain, '.'.$host)) {
                return true;
            }
        }

        return false;
    }
}
