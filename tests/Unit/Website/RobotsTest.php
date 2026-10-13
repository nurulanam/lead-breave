<?php

use App\Services\Website\Robots;

it('follows the rules for every bot', function () {
    $robots = new Robots("User-agent: *\nDisallow: /private\nDisallow: /admin # staff only\n");

    expect($robots->allows('/contact'))->toBeTrue()
        ->and($robots->allows('/private/team'))->toBeFalse()
        ->and($robots->allows('/admin'))->toBeFalse();
});

it('prefers rules written for LeadBreave', function () {
    $robots = new Robots("User-agent: *\nDisallow: /\n\nUser-agent: LeadBreaveBot\nDisallow: /secret\n");

    expect($robots->allows('/contact'))->toBeTrue()
        ->and($robots->allows('/secret'))->toBeFalse();
});

it('blocks everything when told to', function () {
    expect((new Robots("User-agent: *\nDisallow: /"))->allows('/contact'))->toBeFalse();
});

it('allows everything with no robots file', function () {
    expect((new Robots(''))->allows('/anything'))->toBeTrue();
});
