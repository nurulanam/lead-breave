<?php

use App\Enums\DataSource;
use App\Enums\LeadStatus;
use App\Enums\SocialPlatform;
use App\Enums\WebsiteCheckType;
use App\Jobs\CheckWebsite;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\WebsiteCheck;
use App\Services\Website\WebsiteChecker;
use App\Services\Website\WebsiteFindings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    fakeDns();
    Http::preventStrayRequests();
});

function homePage(): string
{
    return <<<'HTML'
        <html><head><title>Northside Plumbing | Emergency plumber in Brooklyn</title>
        <meta name="description" content="Family plumbers fixing leaks, boilers and drains across Brooklyn since 1990, 24 hours a day.">
        <meta name="viewport" content="width=device-width"><meta name="generator" content="WordPress 6.4">
        <link rel="stylesheet" href="/wp-content/themes/x/style.css"></head>
        <body><h1>Northside Plumbing</h1><p>We fix leaks and install boilers across Brooklyn. Call us any time.</p>
        <nav><a href="/contact-us/">Contact us</a> <a href="/about">About</a></nav>
        <footer><a href="https://www.facebook.com/northsideplumbing">Facebook</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=x">Share</a> © 2026 Northside</footer></body></html>
        HTML;
}

function fakeSite(array $overrides = []): void
{
    Http::fake([
        'northsideplumbing.com/robots.txt' => Http::response("User-agent: *\nDisallow: /wp-admin", 200, ['Content-Type' => 'text/plain']),
        'northsideplumbing.com/contact-us/' => Http::response('<p>Email <a href="mailto:jane@northsideplumbing.com">Jane</a> or info@northsideplumbing.com</p><a href="https://www.instagram.com/northside">IG</a>', 200, ['Content-Type' => 'text/html']),
        'northsideplumbing.com/about' => Http::response('<p>About us. Our team.</p>', 200, ['Content-Type' => 'text/html']),
        'northsideplumbing.com' => Http::response(homePage(), 200, ['Content-Type' => 'text/html; charset=utf-8', 'Server' => 'nginx']),
        ...$overrides,
    ]);
}

/**
 * Run the job in this process. Website checks are faked on the queue for the
 * whole suite, so dispatching would only record them.
 *
 * @param  list<string>  $types
 */
function runWebsiteCheck(Lead $lead, array $types = []): void
{
    (new CheckWebsite($lead->id, $types))->handle(app(WebsiteChecker::class));
}

function northside(array $attributes = []): Lead
{
    return Lead::factory()->create(['website' => 'https://northsideplumbing.com', ...$attributes]);
}

it('checks a new lead: emails, socials, SEO, tech and HTTPS', function () {
    fakeSite();
    $lead = northside();

    runWebsiteCheck($lead);
    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Enriched)
        ->and($lead->websiteChecks()->pluck('status', 'type')->all())->toEqualCanonicalizing(['crawl' => 'ok', 'ssl' => 'ok', 'seo' => 'ok', 'tech' => 'ok'])
        ->and($lead->primaryEmail->email)->toBe('jane@northsideplumbing.com')
        ->and($lead->primaryEmail->source)->toBe(DataSource::Crawl)
        ->and($lead->primaryEmail->found_on_url)->toBe('https://northsideplumbing.com/contact-us/')
        ->and($lead->emails()->where('email', 'info@northsideplumbing.com')->sole()->is_role)->toBeTrue()
        ->and($lead->socials()->pluck('platform')->all())->toEqualCanonicalizing([SocialPlatform::Facebook, SocialPlatform::Instagram]);

    $crawl = $lead->websiteChecks()->where('type', WebsiteCheckType::Crawl)->sole()->result;

    expect($crawl['emails_found'])->toBe(2)
        ->and(array_column($crawl['pages'], 'kind'))->toBe(['home', 'contact', 'about'])
        ->and($lead->websiteChecks()->where('type', WebsiteCheckType::Tech)->sole()->result['cms'])->toBe('WordPress')
        ->and($lead->websiteChecks()->where('type', WebsiteCheckType::Ssl)->sole()->result['certificate_valid'])->toBeTrue();

    Http::assertSent(fn (Request $request) => str_contains($request->header('User-Agent')[0] ?? '', 'LeadBreaveBot'));
});

it('moves a lead with no email found to needs info', function () {
    fakeSite([
        'northsideplumbing.com/contact-us/' => Http::response('<p>Call us.</p>', 200, ['Content-Type' => 'text/html']),
        'northsideplumbing.com/about' => Http::response('<p>About.</p>', 200, ['Content-Type' => 'text/html']),
    ]);
    $lead = northside();

    runWebsiteCheck($lead);

    expect($lead->fresh()->status)->toBe(LeadStatus::NeedsInfo);
});

it('never touches an email entered by hand', function () {
    fakeSite();
    $lead = northside();
    $manual = LeadEmail::factory()->for($lead)->manual()->primary()->create(['email' => 'jane@northsideplumbing.com', 'confidence' => 100]);

    runWebsiteCheck($lead);

    expect($manual->fresh())
        ->source->toBe(DataSource::Manual)
        ->confidence->toBe(100)
        ->is_primary->toBeTrue()
        ->and($lead->emails()->count())->toBe(2);
});

it('re-checks one part without touching the others', function () {
    fakeSite();
    $lead = northside();
    runWebsiteCheck($lead);
    $before = $lead->websiteChecks()->pluck('checked_at', 'type')->map->toIso8601String()->all();

    $this->travel(5)->minutes();
    runWebsiteCheck($lead, [WebsiteCheckType::Seo->value]);
    $after = $lead->websiteChecks()->pluck('checked_at', 'type')->map->toIso8601String()->all();

    expect($after['seo'])->not->toBe($before['seo'])
        ->and(array_diff_key($after, ['seo' => 1]))->toBe(array_diff_key($before, ['seo' => 1]));
});

it('reports a domain that no longer exists', function () {
    fakeDns(['northsideplumbing.com' => []]);
    $lead = northside();

    runWebsiteCheck($lead);

    $crawl = $lead->websiteChecks()->where('type', WebsiteCheckType::Crawl)->sole();

    expect($crawl->status)->toBe(WebsiteCheck::STATUS_FAILED)
        ->and($crawl->error)->toContain('does not resolve')
        ->and($crawl->result['dead'])->toBeTrue()
        ->and($lead->fresh()->status)->toBe(LeadStatus::NeedsInfo);

    Http::assertNothingSent();
});

it('refuses to fetch a site that resolves to a private address', function () {
    fakeDns(['northsideplumbing.com' => ['10.0.0.8']]);
    $lead = northside();

    runWebsiteCheck($lead);

    expect($lead->websiteChecks()->where('type', WebsiteCheckType::Crawl)->sole()->error)->toContain('private or reserved');
    Http::assertNothingSent();
});

it('refuses to follow a redirect into a private network', function () {
    fakeDns(['intranet-portal.com' => ['192.168.0.10']]);
    Http::fake(['northsideplumbing.com' => Http::response('', 302, ['Location' => 'http://intranet-portal.com/admin'])]);
    $lead = northside();

    runWebsiteCheck($lead);

    expect($lead->websiteChecks()->where('type', WebsiteCheckType::Crawl)->sole()->error)->toContain('private or reserved');
    Http::assertSentCount(1);
});

it('records a broken certificate but still reads the site', function () {
    Http::fake([
        'northsideplumbing.com/*' => Http::response('', 404),
        'northsideplumbing.com' => Http::sequence()
            ->pushFailedConnection('cURL error 60: SSL certificate problem: certificate has expired')
            ->push(homePage(), 200, ['Content-Type' => 'text/html']),
    ]);
    $lead = northside();

    runWebsiteCheck($lead, [WebsiteCheckType::Ssl->value, WebsiteCheckType::Seo->value]);

    $ssl = $lead->websiteChecks()->where('type', WebsiteCheckType::Ssl)->sole()->result;

    expect($ssl['certificate_valid'])->toBeFalse()
        ->and($ssl['certificate_error'])->toBe('The SSL certificate has expired.')
        ->and($lead->websiteChecks()->where('type', WebsiteCheckType::Seo)->sole()->status)->toBe(WebsiteCheck::STATUS_OK);
});

it('flags a parked domain and does not crawl it further', function () {
    Http::fake(['northsideplumbing.com' => Http::response('<h1>This domain is for sale!</h1>', 200, ['Content-Type' => 'text/html'])]);
    $lead = northside();

    runWebsiteCheck($lead);

    expect($lead->websiteChecks()->where('type', WebsiteCheckType::Crawl)->sole()->result['parked'])->toBe('The domain is parked or for sale.')
        ->and($lead->websiteChecks()->where('type', WebsiteCheckType::Seo)->sole()->error)->toBe('The domain is parked or for sale.');

    Http::assertSentCount(1);
});

it('keeps the status of a lead further along the pipeline', function () {
    fakeSite();
    $lead = northside(['status' => LeadStatus::Audited]);

    runWebsiteCheck($lead);

    expect($lead->fresh()->status)->toBe(LeadStatus::Audited)
        ->and($lead->emails()->count())->toBe(2);
});

it('waits when the domain has been checked too often this minute', function () {
    fakeSite();
    $lead = northside();

    foreach (range(1, (int) config('services.website_check.per_domain_per_minute')) as $attempt) {
        RateLimiter::hit('website-check:northsideplumbing.com', 60);
    }

    (new CheckWebsite($lead->id))->handle(app(WebsiteChecker::class));

    expect($lead->websiteChecks()->exists())->toBeFalse();
    Http::assertNothingSent();
});

it('queues a check for leads that have a website', function () {
    $withWebsite = northside();
    $withoutWebsite = Lead::factory()->withoutWebsite()->create();

    CheckWebsite::forLead($withWebsite);
    CheckWebsite::forLead($withoutWebsite);

    Queue::assertPushed(CheckWebsite::class, 1);
    Queue::assertPushed(CheckWebsite::class, fn (CheckWebsite $job) => $job->leadId === $withWebsite->id && $job->queue === 'enrich');
});

it('explains a site behind bot protection instead of reporting an error', function () {
    Http::fake(['northsideplumbing.com' => Http::response(
        '<!DOCTYPE html><html><head><title>Just a moment...</title></head><body><script src="/cdn-cgi/challenge-platform/h/b/orchestrate/chl_page/v1"></script></body></html>',
        403,
        ['Content-Type' => 'text/html', 'Server' => 'cloudflare', 'cf-mitigated' => 'challenge'],
    )]);
    $lead = northside();

    runWebsiteCheck($lead);

    $crawl = $lead->websiteChecks()->where('type', WebsiteCheckType::Crawl)->sole();
    $findings = WebsiteFindings::for($crawl);

    expect($crawl->status)->toBe(WebsiteCheck::STATUS_OK)
        ->and($crawl->result['blocked_by'])->toBe('Cloudflare')
        ->and($findings[0]['label'])->toBe('Blocks automated checks (Cloudflare)')
        ->and($lead->websiteChecks()->where('type', WebsiteCheckType::Seo)->sole()->error)->toStartWith('Cloudflare bot protection blocks automated visits.')
        ->and($lead->websiteChecks()->where('type', WebsiteCheckType::Ssl)->sole()->status)->toBe(WebsiteCheck::STATUS_OK)
        ->and($lead->fresh()->status)->toBe(LeadStatus::NeedsInfo);

    Http::assertSentCount(1);
});
