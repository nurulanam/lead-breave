<?php

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\WebsiteCheckType;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Jobs\CheckWebsite;
use App\Livewire\WebsiteCheckProgress;
use App\Models\Lead;
use App\Services\Website\CheckProgress;
use App\Services\Website\WebsiteChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(userWithRole(Role::Researcher));
    fakeDns();
    Http::preventStrayRequests();
});

it('marks a check as queued the moment it is requested', function () {
    $lead = Lead::factory()->create(['website' => 'https://northsideplumbing.com']);

    CheckWebsite::forLead($lead);

    expect(CheckProgress::get($lead->id))->toMatchArray(['state' => CheckProgress::QUEUED, 'percent' => 0]);
});

it('reports each step and finishes with a summary', function () {
    Http::fake([
        'northsideplumbing.com/robots.txt' => Http::response('', 404),
        'northsideplumbing.com/contact' => Http::response('<a href="mailto:jane@northsideplumbing.com">Jane</a>', 200, ['Content-Type' => 'text/html']),
        'northsideplumbing.com' => Http::response('<h1>Northside</h1><p>'.str_repeat('Plumbing across Brooklyn. ', 30).'</p><a href="/contact">Contact</a><a href="https://www.facebook.com/northside">FB</a>', 200, ['Content-Type' => 'text/html']),
    ]);
    $lead = Lead::factory()->create(['website' => 'https://northsideplumbing.com']);
    $steps = [];

    app(WebsiteChecker::class)->check($lead, WebsiteCheckType::cases(), function (int $percent, string $step) use (&$steps): void {
        $steps[] = [$percent, $step];
    });

    expect(array_column($steps, 1))->toContain('Connecting to northsideplumbing.com', 'Reading the home page', 'Reading contact page 1 of 1', 'Finding emails and social profiles')
        ->and(array_column($steps, 0))->toBe(collect(array_column($steps, 0))->sort()->values()->all());

    (new CheckWebsite($lead->id))->handle(app(WebsiteChecker::class));

    expect(CheckProgress::get($lead->id))->toMatchArray([
        'state' => CheckProgress::DONE,
        'step' => 'Check finished: found 1 email and 1 social profile.',
    ])->and($lead->fresh()->status)->toBe(LeadStatus::Enriched);
});

it('says when it is waiting to be polite to a busy domain', function () {
    $lead = Lead::factory()->create(['website' => 'https://northsideplumbing.com']);

    foreach (range(1, (int) config('services.website_check.per_domain_per_minute')) as $attempt) {
        RateLimiter::hit('website-check:northsideplumbing.com', 60);
    }

    (new CheckWebsite($lead->id))->handle(app(WebsiteChecker::class));

    expect(CheckProgress::get($lead->id)['step'])->toContain('Waiting a minute to be polite');
});

it('shows live progress and polls while a check runs', function () {
    $lead = Lead::factory()->create();
    CheckProgress::step($lead->id, 45, 'Reading contact page 2 of 3');

    Livewire::test(WebsiteCheckProgress::class, ['leadId' => $lead->id])
        ->assertSee('Checking the website')
        ->assertSee('Reading contact page 2 of 3')
        ->assertSee('45%')
        ->assertSeeHtml('wire:poll.1500ms="tick"');
});

it('tells the page to refresh the moment the check finishes', function () {
    $lead = Lead::factory()->create();
    CheckProgress::step($lead->id, 80, 'Finding emails and social profiles');

    $component = Livewire::test(WebsiteCheckProgress::class, ['leadId' => $lead->id]);

    $component->call('tick')->assertNotDispatched('lead-updated');

    CheckProgress::done($lead->id, 'Check finished: found 2 emails and 1 social profile.');

    $component->call('tick')
        ->assertDispatched('lead-updated')
        ->assertSee('Website check finished')
        ->assertSee('found 2 emails')
        ->assertSeeHtml('wire:poll.5s="tick"');
});

it('picks up a check started somewhere else while the page is open', function () {
    $lead = Lead::factory()->create();

    $component = Livewire::test(WebsiteCheckProgress::class, ['leadId' => $lead->id])
        ->assertDontSee('Checking the website')
        ->assertSeeHtml('wire:poll.5s="tick"');

    CheckProgress::step($lead->id, 30, 'Looking for contact pages');

    $component->call('tick')
        ->assertSee('Looking for contact pages')
        ->assertSeeHtml('wire:poll.1500ms="tick"');
});

it('does not show an old result to someone who was not watching', function () {
    $lead = Lead::factory()->create();
    CheckProgress::done($lead->id, 'Check finished: found 2 emails and 1 social profile.');

    Livewire::test(WebsiteCheckProgress::class, ['leadId' => $lead->id])
        ->assertDontSee('Website check finished');
});

it('reloads the lead page when told the lead was updated', function () {
    $lead = Lead::factory()->status(LeadStatus::Enriching)->create();

    $page = Livewire::test(ViewLead::class, ['record' => $lead->getRouteKey()])
        ->assertSeeLivewire(WebsiteCheckProgress::class)
        ->assertSee('Enriching');

    $lead->transitionTo(LeadStatus::Enriched);

    $page->dispatch('lead-updated')->assertSee('Enriched')->assertDontSee('Enriching');
});

it('shows progress in the leads list and keeps refreshing while leads are enriching', function () {
    $lead = Lead::factory()->status(LeadStatus::Enriching)->create();
    CheckProgress::step($lead->id, 60, 'Reading contact page 1 of 2');

    Livewire::test(ListLeads::class)
        ->assertSee('60% · Reading contact page 1 of 2')
        ->assertSeeHtml('wire:poll.2s');
});

it('starts refreshing the leads list as soon as a check is queued', function () {
    $lead = Lead::factory()->create();

    Livewire::test(ListLeads::class)->assertDontSeeHtml('wire:poll');

    CheckWebsite::forLead($lead);

    Livewire::test(ListLeads::class)
        ->assertSee('Website check queued')
        ->assertSeeHtml('wire:poll.2s');

    $this->travel(3)->minutes();

    Livewire::test(ListLeads::class)->assertDontSeeHtml('wire:poll');
});
