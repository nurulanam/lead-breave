<?php

use App\Enums\SocialPlatform;
use App\Services\Website\SocialExtractor;

it('normalizes profile links and rejects share and platform links', function (string $url, ?string $expected, ?SocialPlatform $platform) {
    $result = (new SocialExtractor)->normalize($url);

    expect($result)->toBe($expected === null ? null : [$expected, $platform]);
})->with([
    'facebook page' => ['https://m.facebook.com/northside.plumbing/', 'https://www.facebook.com/northside.plumbing', SocialPlatform::Facebook],
    'facebook profile id' => ['https://www.facebook.com/profile.php?id=123', 'https://www.facebook.com/profile.php?id=123', SocialPlatform::Facebook],
    'instagram' => ['https://instagram.com/northside', 'https://www.instagram.com/northside', SocialPlatform::Instagram],
    'linkedin company' => ['https://uk.linkedin.com/company/northside', 'https://www.linkedin.com/company/northside', SocialPlatform::LinkedIn],
    'twitter becomes x' => ['https://twitter.com/northside', 'https://www.twitter.com/northside', SocialPlatform::X],
    'youtube handle' => ['https://www.youtube.com/@northside', 'https://www.youtube.com/@northside', SocialPlatform::YouTube],
    'youtube channel' => ['https://youtube.com/channel/UC123abc', 'https://www.youtube.com/channel/UC123abc', SocialPlatform::YouTube],
    'youtube video' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', SocialPlatform::YouTube],
    'youtube embed' => ['//www.youtube.com/embed/dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', SocialPlatform::YouTube],
    'share button' => ['https://www.facebook.com/sharer/sharer.php?u=x', null, null],
    'tweet intent' => ['https://twitter.com/intent/tweet?text=x', null, null],
    'platform home' => ['https://www.facebook.com/', null, null],
    'not social' => ['https://example.org/about', null, null],
]);

it('reads links and JSON-LD sameAs across pages without duplicates', function () {
    $pages = [
        '<a href="https://www.facebook.com/northside">FB</a><a href="https://facebook.com/northside/">again</a>',
        '<script type="application/ld+json">{"sameAs":["https://www.instagram.com/northside"]}</script>',
    ];

    expect((new SocialExtractor)->extract($pages))->toBe([
        'https://www.facebook.com/northside' => SocialPlatform::Facebook,
        'https://www.instagram.com/northside' => SocialPlatform::Instagram,
    ]);
});
