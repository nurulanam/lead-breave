<?php

use App\Enums\LeadEventType;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\LeadSocial;
use App\Models\Search;
use App\Services\Leads\LeadMatcher;
use App\Services\Leads\LeadMerger;

it('moves everything onto the kept lead and deletes the duplicate', function () {
    $keep = Lead::factory()->status(LeadStatus::Audited)->create(['website' => 'https://keep.example', 'phone' => null, 'phone_e164' => null, 'place_id' => null]);
    $duplicate = Lead::factory()->create(['name' => 'Dup Plumbing', 'website' => 'https://other.example', 'phone' => '(212) 555-0100', 'phone_e164' => '+12125550100', 'place_id' => 'ChIJdup']);

    LeadEmail::factory()->for($keep)->primary()->create(['email' => 'shared@keep.example']);
    LeadEmail::factory()->for($duplicate)->primary()->create(['email' => 'shared@keep.example']);
    LeadEmail::factory()->for($duplicate)->create(['email' => 'owner@other.example']);
    LeadSocial::factory()->for($duplicate)->create(['url' => 'https://www.facebook.com/dup']);
    $search = Search::factory()->create();
    $search->leads()->attach($duplicate, ['rank' => 4, 'was_new' => true]);

    $kept = app(LeadMerger::class)->merge($keep, $duplicate, userWithRole(Role::Researcher));

    expect(Lead::find($duplicate->id))->toBeNull()
        ->and($kept->website)->toBe('https://keep.example')
        ->and($kept->phone_e164)->toBe('+12125550100')
        ->and($kept->place_id)->toBe('ChIJdup')
        ->and($kept->status)->toBe(LeadStatus::Audited)
        ->and($kept->emails()->pluck('email')->sort()->values()->all())->toBe(['owner@other.example', 'shared@keep.example'])
        ->and($kept->emails()->where('is_primary', true)->count())->toBe(1)
        ->and($kept->socials()->count())->toBe(1)
        ->and($kept->searches()->sole()->is($search))->toBeTrue();

    $merged = $kept->events()->where('type', LeadEventType::Merged)->sole();

    expect($merged->data['duplicate']['name'])->toBe('Dup Plumbing')
        ->and($kept->events()->where('type', LeadEventType::Created)->count())->toBe(2);
});

it('refuses to merge a lead into itself', function () {
    $lead = Lead::factory()->create();

    expect(fn () => app(LeadMerger::class)->merge($lead, $lead))->toThrow(InvalidArgumentException::class);
});

it('suggests possible duplicates by website, phone, email, or name and city', function () {
    $lead = Lead::factory()->create(['name' => 'Harbor Dental', 'city' => 'Austin', 'website' => 'https://harbor.example', 'phone_e164' => '+15125550000']);
    LeadEmail::factory()->for($lead)->create(['email' => 'hi@harbor.example']);

    $sameDomain = Lead::factory()->create(['website' => 'http://www.harbor.example/contact', 'phone_e164' => null]);
    $samePhone = Lead::factory()->create(['website' => null, 'phone_e164' => '+15125550000']);
    $sameEmail = Lead::factory()->create(['website' => null, 'phone_e164' => null]);
    LeadEmail::factory()->for($sameEmail)->create(['email' => 'hi@harbor.example']);
    $sameName = Lead::factory()->create(['name' => 'HARBOR DENTAL', 'city' => 'austin', 'website' => null, 'phone_e164' => null]);
    Lead::factory()->create(['name' => 'Harbor Dental', 'city' => 'Dallas', 'website' => null, 'phone_e164' => null]);

    expect(LeadMatcher::possibleDuplicates($lead)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$sameDomain, $samePhone, $sameEmail, $sameName])->pluck('id')->sort()->values()->all());
});
