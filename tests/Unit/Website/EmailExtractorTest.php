<?php

use App\Services\Website\EmailExtractor;

it('finds addresses in links, text, JSON-LD and spelled-out form', function () {
    $html = <<<'HTML'
        <a href="mailto:owner@acme.example?subject=Hi">Email us</a>
        <p>Or write to bookings@acme.example any time.</p>
        <script type="application/ld+json">{"@type":"LocalBusiness","email":"mailto:office@acme.example"}</script>
        <p>Jane: jane [at] acme [dot] example</p>
        <script>var x = "hidden@acme.example";</script>
        HTML;

    $found = (new EmailExtractor)->extract($html, 'acme.example', 'contact', 'https://acme.example/contact');

    expect(array_keys($found))->toEqualCanonicalizing(['owner@acme.example', 'bookings@acme.example', 'office@acme.example', 'jane@acme.example'])
        ->and($found['owner@acme.example']->source)->toBe('mailto')
        ->and($found['jane@acme.example']->source)->toBe('obfuscated')
        ->and($found['bookings@acme.example']->isRole)->toBeTrue();
});

it('decodes Cloudflare-protected addresses', function () {
    $key = 0x42;
    $hex = sprintf('%02x', $key).implode('', array_map(fn (string $char) => sprintf('%02x', ord($char) ^ $key), str_split('sam@acme.example')));

    expect(EmailExtractor::decodeCloudflare($hex))->toBe('sam@acme.example');

    $found = (new EmailExtractor)->extract("<a class=\"__cf_email__\" data-cfemail=\"{$hex}\">[email protected]</a>", 'acme.example', 'home', 'https://acme.example');

    expect($found)->toHaveKey('sam@acme.example')
        ->and($found['sam@acme.example']->source)->toBe('cloudflare');
});

it('ignores placeholders, tooling and image file names', function () {
    $html = 'you@example.com noreply@acme.example logo@2x.png abc123@sentry.io 0123456789abcdef0123@acme.example';

    expect((new EmailExtractor)->extract($html, 'acme.example', 'home', 'https://acme.example'))->toBe([]);
});

it('scores a named address on the business domain above everything else', function () {
    $extractor = new EmailExtractor;

    $named = $extractor->score('jane@acme.example', 'mailto', 'acme.example', 'contact', false);
    $role = $extractor->score('info@acme.example', 'mailto', 'acme.example', 'contact', true);
    $gmail = $extractor->score('acmeplumbing@gmail.com', 'text', 'acme.example', 'home', false);
    $agency = $extractor->score('hello@webagency.example', 'text', 'acme.example', 'home', false);

    expect($named)->toBe(100)
        ->and($named)->toBeGreaterThan($role)
        ->and($role)->toBeGreaterThan($gmail)
        ->and($gmail)->toBeGreaterThan($agency);
});

it('merges pages keeping the strongest evidence for each address', function () {
    $extractor = new EmailExtractor;
    $home = $extractor->extract('jane@acme.example', 'acme.example', 'home', 'https://acme.example');
    $contact = $extractor->extract('<a href="mailto:jane@acme.example">x</a> sales@acme.example', 'acme.example', 'contact', 'https://acme.example/contact');

    $merged = $extractor->merge([$home, $contact]);

    expect(array_map(fn ($candidate) => $candidate->email, $merged))->toBe(['jane@acme.example', 'sales@acme.example'])
        ->and($merged[0]->foundOnUrl)->toBe('https://acme.example/contact');
});
