<?php

use App\Services\Website\FetchResult;
use App\Services\Website\ParkedPageDetector;
use App\Services\Website\SeoAnalyzer;
use App\Services\Website\TechDetector;

function page(string $html, string $url = 'https://acme.example/', array $headers = []): FetchResult
{
    return new FetchResult($url, $url, 200, $html, $headers, 0.2, 0);
}

it('spots parked, expired and placeholder domains', function (string $html, ?string $reason) {
    expect((new ParkedPageDetector)->reason(page($html)))->toBe($reason);
})->with([
    'for sale' => ['<h1>This domain is for sale!</h1><p>'.str_repeat('Make an offer today. ', 60).'</p>', 'The domain is parked or for sale.'],
    'parking provider' => ['<script src="https://www.sedoparking.com/x.js"></script>', 'The domain is parked or for sale.'],
    'expired' => ['<p>This domain has expired. Renew this domain now.</p>', 'The domain has expired.'],
    'nginx default' => ['<h1>Welcome to nginx!</h1>', 'The server shows a default page, not a website.'],
    'coming soon' => ['<h1>Website coming soon</h1>', 'The site is a placeholder ("coming soon" or under construction).'],
    'real site mentioning construction' => ['<h1>Northside Plumbing</h1><p>'.str_repeat('We fix leaks and install boilers across Brooklyn. ', 20).'Our new showroom is under construction.</p>', null],
    'site linking to jordan.com' => ['<p>'.str_repeat('Family run since 1990. ', 40).'<a href="https://jordan.com">Partner</a></p>', null],
]);

it('reads the on-page SEO basics', function () {
    $html = <<<'HTML'
        <html><head><title>Northside Plumbing | Emergency plumber in Brooklyn</title>
        <meta name="viewport" content="width=device-width"><link rel="icon" href="/f.ico">
        </head><body><h1>Plumbing</h1><img src="a.jpg" alt="Van"><img src="http://cdn.acme.example/b.jpg">
        <footer>© 2019 Northside</footer></body></html>
        HTML;

    expect((new SeoAnalyzer)->analyze(page($html)))->toMatchArray([
        'title_length' => 50,
        'description_length' => 0,
        'h1_count' => 1,
        'alt_coverage' => 50,
        'has_viewport' => true,
        'has_schema' => false,
        'has_favicon' => true,
        'is_https' => true,
        'mixed_content' => true,
        'copyright_year' => 2019,
    ]);
});

it('fingerprints the platform and flags dated technology', function () {
    $html = '<meta name="generator" content="WordPress 5.4.2"><link href="/wp-content/themes/Divi/style.css"><script src="/js/jquery-1.12.4.min.js"></script><table cellpadding="0"><font>Hi</font></table>';

    expect((new TechDetector)->detect(page($html, headers: ['server' => 'Apache'])))->toMatchArray([
        'cms' => 'WordPress',
        'cms_version' => '5.4.2',
        'builder' => 'Divi',
        'server' => 'Apache',
        'jquery_version' => '1.12.4',
        'dated_markers' => ['WordPress 5.4.2', 'jQuery 1.12.4', 'Table-based layout', '<font> tags'],
    ]);
});
