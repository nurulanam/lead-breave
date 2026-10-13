<?php

use App\Enums\Role;
use App\Enums\WebsiteCheckType;
use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Leads\RelationManagers\WebsiteChecksRelationManager;
use App\Jobs\CheckWebsite;
use App\Models\Lead;
use App\Models\Search;
use App\Models\WebsiteCheck;
use App\Services\Google\Place;
use App\Services\Search\PlaceIngestor;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(userWithRole(Role::Researcher)));

function websiteTab(Lead $lead): Testable
{
    return Livewire::test(WebsiteChecksRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => ViewLead::class]);
}

it('shows each check as a card with readable findings', function () {
    $lead = Lead::factory()->create();
    WebsiteCheck::factory()->for($lead)->create(['type' => WebsiteCheckType::Seo, 'result' => [
        'title' => 'Northside', 'title_length' => 9, 'description_length' => 0, 'h1_count' => 1, 'has_viewport' => false,
        'alt_coverage' => 100, 'image_count' => 0, 'has_schema' => true, 'has_canonical' => true, 'has_open_graph' => true,
        'has_favicon' => true, 'mixed_content' => false, 'copyright_year' => 2019,
    ]]);
    WebsiteCheck::factory()->for($lead)->create(['type' => WebsiteCheckType::Crawl, 'status' => WebsiteCheck::STATUS_FAILED, 'result' => null, 'error' => 'The website took too long to respond.']);

    websiteTab($lead)
        ->assertSee('On-page SEO')
        ->assertSee('Meta description')
        ->assertSee('Works on phones (viewport)')
        ->assertSee('Could not complete')
        ->assertSee('The website took too long to respond.');
});

it('queues a full check from the tab', function () {
    $lead = Lead::factory()->create(['website' => 'https://northsideplumbing.com']);

    websiteTab($lead)
        ->callAction(TestAction::make('checkAll')->table())
        ->assertNotified('Website check queued')
        ->assertSet('waitingForResults', true);

    Queue::assertPushed(CheckWebsite::class, fn (CheckWebsite $job) => $job->leadId === $lead->id && $job->types === []);
});

it('re-checks a single card', function () {
    $lead = Lead::factory()->create(['website' => 'https://northsideplumbing.com']);
    $check = WebsiteCheck::factory()->for($lead)->create(['type' => WebsiteCheckType::Tech, 'result' => ['cms' => 'Wix']]);

    websiteTab($lead)->callAction(TestAction::make('recheck')->table($check));

    Queue::assertPushed(CheckWebsite::class, fn (CheckWebsite $job) => $job->types === ['tech']);
});

it('explains when there is no website to check', function () {
    websiteTab(Lead::factory()->withoutWebsite()->create())
        ->assertSee('No website to check')
        ->assertActionHidden(TestAction::make('checkAll')->table());
});

it('checks the websites of selected leads in bulk', function () {
    $leads = Lead::factory()->count(2)->create();
    $noWebsite = Lead::factory()->withoutWebsite()->create();

    Livewire::test(ListLeads::class)
        ->selectTableRecords([...$leads->modelKeys(), $noWebsite->id])
        ->callAction(TestAction::make('bulkCheckWebsites')->table()->bulk())
        ->assertNotified('Queued 2 website checks');

    Queue::assertPushed(CheckWebsite::class, 2);
});

it('checks websites of leads found by a search or added by hand', function () {
    (new PlaceIngestor)->ingest(Search::factory()->create(), [Place::fromApi(fakePlace(1))]);

    Livewire::test(CreateLead::class)
        ->fillForm(['name' => 'Corner Bakery', 'website' => 'cornerbakery.com', 'country' => 'US'])
        ->call('create');

    Queue::assertPushed(CheckWebsite::class, 2);
});
