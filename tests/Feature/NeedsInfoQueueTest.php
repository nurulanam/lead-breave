<?php

use App\Enums\DataSource;
use App\Enums\LeadEventType;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Filament\Pages\NeedsInfoQueue;
use App\Models\Lead;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(userWithRole(Role::Researcher)));

it('starts with the best-reviewed lead that is missing details', function () {
    Lead::factory()->withoutWebsite()->create(['review_count' => 10]);
    $best = Lead::factory()->withoutWebsite()->create(['review_count' => 400]);
    Lead::factory()->withEmail()->create(['review_count' => 900]);

    Livewire::test(NeedsInfoQueue::class)
        ->assertSet('leadId', $best->id)
        ->assertSee($best->name)
        ->assertSee('2 left');
});

it('saves what was found and moves to the next lead', function () {
    $first = Lead::factory()->withoutWebsite()->create(['review_count' => 200, 'country' => 'US']);
    $second = Lead::factory()->withoutWebsite()->create(['review_count' => 100]);

    Livewire::test(NeedsInfoQueue::class)
        ->fillForm(['website' => 'firstplumbing.example', 'email' => 'Owner@FirstPlumbing.example', 'social' => 'https://www.facebook.com/firstplumbing'])
        ->call('save')
        ->assertSet('leadId', $second->id);

    $first->refresh();

    expect($first->website)->toBe('https://firstplumbing.example')
        ->and($first->primaryEmail->email)->toBe('owner@firstplumbing.example')
        ->and($first->primaryEmail->source)->toBe(DataSource::Manual)
        ->and($first->socials()->sole()->url)->toBe('https://www.facebook.com/firstplumbing')
        ->and($first->events()->where('type', LeadEventType::Edited)->sole()->data['fields'])->toBe(['website', 'email', 'social link']);
});

it('skips a lead for the rest of the session', function () {
    $first = Lead::factory()->withoutWebsite()->create(['review_count' => 200]);
    $second = Lead::factory()->withoutWebsite()->create(['review_count' => 100]);

    Livewire::test(NeedsInfoQueue::class)
        ->callAction('skip')
        ->assertSet('leadId', $second->id)
        ->assertSet('skipped', [$first->id]);
});

it('takes a lead out of the queue as not a fit', function () {
    $lead = Lead::factory()->withoutWebsite()->create();

    Livewire::test(NeedsInfoQueue::class)
        ->callAction('notAFit')
        ->assertSee('All caught up');

    expect($lead->fresh()->status)->toBe(LeadStatus::NotAFit);
});

it('says when nothing is left', function () {
    Lead::factory()->withEmail()->create();

    Livewire::test(NeedsInfoQueue::class)
        ->assertSet('leadId', null)
        ->assertSee('All caught up');
});
