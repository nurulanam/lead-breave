<?php

namespace App\Services\Website;

use App\Enums\SocialPlatform;

/**
 * Finds the business's own social profiles: links on the page and JSON-LD
 * "sameAs". Share buttons, intent links and the platforms' own pages are skipped.
 */
class SocialExtractor
{
    /**
     * Host => platform.
     *
     * @var array<string, SocialPlatform>
     */
    private const HOSTS = [
        'facebook.com' => SocialPlatform::Facebook, 'fb.com' => SocialPlatform::Facebook,
        'instagram.com' => SocialPlatform::Instagram, 'linkedin.com' => SocialPlatform::LinkedIn,
        'twitter.com' => SocialPlatform::X, 'x.com' => SocialPlatform::X, 'tiktok.com' => SocialPlatform::TikTok,
        'youtube.com' => SocialPlatform::YouTube, 'youtu.be' => SocialPlatform::YouTube,
        'pinterest.com' => SocialPlatform::Pinterest, 'yelp.com' => SocialPlatform::Yelp,
        'nextdoor.com' => SocialPlatform::Nextdoor,
    ];

    /** Path starts that are never a business profile. */
    private const IGNORED_PATHS = [
        '/sharer', '/share', '/dialog', '/intent', '/plugins', '/tr', '/login', '/signup', '/home',
        '/hashtag', '/search', '/policies', '/legal', '/privacy', '/help',
        '/about', '/pin/create', '/explore', '/p/', '/reel', '/stories', '/events', '/groups',
        '/business', '/ads', '/developers', '/cookies', '/shareArticle', '/feed',
    ];

    /**
     * @param  list<string>  $pages  HTML of each crawled page
     * @return array<string, SocialPlatform> normalized URL => platform
     */
    public function extract(array $pages): array
    {
        $found = [];

        foreach ($pages as $html) {
            $urls = [];

            if (preg_match_all('#\bhref\s*=\s*["\']([^"\']+)["\']#i', $html, $matches)) {
                $urls = $matches[1];
            }

            if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $blocks)) {
                foreach ($blocks[1] as $block) {
                    $data = json_decode(trim($block), true);

                    if (is_array($data)) {
                        array_walk_recursive($data, function (mixed $value, mixed $key) use (&$urls): void {
                            if (is_string($value) && str_starts_with($value, 'http')) {
                                $urls[] = $value;
                            }
                        });
                    }
                }
            }

            foreach ($urls as $url) {
                $normalized = $this->normalize(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($normalized !== null && ! isset($found[$normalized[0]])) {
                    $found[$normalized[0]] = $normalized[1];
                }
            }
        }

        return $found;
    }

    /**
     * @return array{0: string, 1: SocialPlatform}|null
     */
    public function normalize(string $url): ?array
    {
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $host = (string) preg_replace('/^(www\.|m\.|mobile\.|[a-z]{2}\.|[a-z]{2}-[a-z]{2}\.)/', '', strtolower($parts['host']));
        $platform = self::HOSTS[$host] ?? null;
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        if ($platform === null || $path === '') {
            return null;
        }

        if ($platform === SocialPlatform::YouTube) {
            return $this->youtube($host, $path, (string) ($parts['query'] ?? ''));
        }

        foreach (self::IGNORED_PATHS as $ignored) {
            if (str_starts_with(strtolower($path.(isset($parts['query']) ? '?'.$parts['query'] : '')), strtolower($ignored))) {
                return null;
            }
        }

        $keepQuery = $platform === SocialPlatform::Facebook && str_starts_with($path, '/profile.php') && isset($parts['query'])
            ? '?'.$parts['query']
            : '';

        return ['https://www.'.$host.$path.$keepQuery, $platform];
    }

    /**
     * A channel (/@handle, /channel/…, /c/…, /user/…) or a video (watch, embed, youtu.be).
     *
     * @return array{0: string, 1: SocialPlatform}|null
     */
    private function youtube(string $host, string $path, string $query): ?array
    {
        parse_str($query, $params);
        $video = match (true) {
            $host === 'youtu.be' => ltrim($path, '/'),
            $path === '/watch' => is_string($params['v'] ?? null) ? $params['v'] : null,
            str_starts_with($path, '/embed/') => substr($path, 7),
            default => null,
        };

        if ($video !== null) {
            return preg_match('/^[\w-]{6,20}$/', $video) === 1 ? ['https://www.youtube.com/watch?v='.$video, SocialPlatform::YouTube] : null;
        }

        return preg_match('#^/(@[\w.-]+|channel/[\w-]+|c/[\w.-]+|user/[\w.-]+)$#', $path) === 1
            ? ['https://www.youtube.com'.$path, SocialPlatform::YouTube]
            : null;
    }
}
