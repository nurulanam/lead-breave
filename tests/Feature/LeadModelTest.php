<?php

use App\Enums\LeadEventType;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\LeadEvent;
use App\Models\LeadSocial;
use App\Models\Search;
use Illuminate\Database\UniqueConstraintViolationException;

it('logs a created event with the lead source', function () {
    $lead = Lead::factory()->manual()->create();

    $event = $lead->events()->sole();

    expect($event->type)->toBe(LeadEventType::Created)
        ->and($event->data)->toBe(['source' => 'manual']);
});

it('keeps the domain in step with the website', function () {
    $lead = Lead::factory()->create(['website' => 'https://www.Northside-Plumbing.com/']);

    expect($lead->domain)->toBe('northside-plumbing.com');

    $lead->update(['website' => '']);

    expect($lead->fresh())
        ->website->toBeNull()
        ->domain->toBeNull();
});

it('keeps exactly one primary email per lead', function () {
    $lead = Lead::factory()->create();
    $first = LeadEmail::factory()->for($lead)->primary()->create();
    $second = LeadEmail::factory()->for($lead)->create();

    $second->makePrimary();

    expect($lead->emails()->where('is_primary', true)->pluck('id')->all())->toBe([$second->id])
        ->and($lead->primaryEmail->is($second))->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse();
});

it('rejects the same email twice on one lead, ignoring case', function () {
    $lead = Lead::factory()->create();
    LeadEmail::factory()->for($lead)->create(['email' => 'office@example.com']);

    expect(fn () => LeadEmail::factory()->for($lead)->create(['email' => ' Office@Example.com ']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rejects a second lead with the same Google place id', function () {
    Lead::factory()->create(['place_id' => 'ChIJabc']);

    expect(fn () => Lead::factory()->create(['place_id' => 'ChIJabc']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('links a lead found again to the new search', function () {
    $lead = Lead::factory()->create();
    $first = Search::factory()->create();
    $second = Search::factory()->create();

    $first->leads()->attach($lead, ['rank' => 3, 'was_new' => true]);
    $second->leads()->attach($lead, ['rank' => 11, 'was_new' => false]);

    $links = $lead->searches()->orderBy('searches.id')->get();

    expect($links->pluck('pivot.was_new')->all())->toBe([1, 0])
        ->and($links->pluck('pivot.rank')->all())->toBe([3, 11]);
});

it('finds leads by name or city', function () {
    Lead::factory()->create(['name' => 'Northside Plumbing', 'city' => 'Queens']);
    Lead::factory()->create(['name' => 'Harbor Dental', 'city' => 'Brooklyn']);

    expect(Lead::search('northside')->pluck('name')->all())->toBe(['Northside Plumbing'])
        ->and(Lead::search('Brooklyn')->pluck('name')->all())->toBe(['Harbor Dental'])
        ->and(Lead::search('  ')->count())->toBe(2);
});

it('removes emails, socials and events with the lead', function () {
    $lead = Lead::factory()->withEmail()->withSocials()->create();

    $lead->delete();

    expect(LeadEmail::count())->toBe(0)
        ->and(LeadSocial::count())->toBe(0)
        ->and(LeadEvent::count())->toBe(0);
});
