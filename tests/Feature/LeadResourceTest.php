<?php

use App\Enums\DataSource;
use App\Enums\LeadEventType;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\SocialPlatform;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Leads\RelationManagers\EmailsRelationManager;
use App\Filament\Resources\Leads\RelationManagers\SocialsRelationManager;
use App\Models\Lead;
use App\Models\LeadEmail;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('lets every role open leads but only admins delete them', function (Role $role, bool $canDelete) {
    $lead = Lead::factory()->create();
    $this->actingAs(userWithRole($role));

    $this->get(LeadResource::getUrl())->assertOk();

    $page = Livewire::test(ViewLead::class, ['record' => $lead->getRouteKey()]);
    $canDelete ? $page->assertActionVisible(DeleteAction::class) : $page->assertActionHidden(DeleteAction::class);
})->with([
    'admin' => [Role::Admin, true],
    'researcher' => [Role::Researcher, false],
    'sender' => [Role::Sender, false],
]);

it('finds a lead by name, website, city, phone or email from one box', function (string $search) {
    $match = Lead::factory()->create(['name' => 'Northside Plumbing', 'website' => 'https://northside.example', 'city' => 'Brooklyn', 'phone_e164' => '+17185550123']);
    LeadEmail::factory()->for($match)->create(['email' => 'office@northside.example']);
    $other = Lead::factory()->create(['name' => 'Harbor Dental', 'website' => null, 'city' => 'Queens', 'phone_e164' => '+12125559999']);
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(ListLeads::class)
        ->searchTable($search)
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
})->with(['name' => ['northside'], 'website' => ['northside.example'], 'city' => ['Brooklyn'], 'phone' => ['718 555 0123'], 'email' => ['office@']]);

it('shows leads missing a website or email on the needs info tab', function () {
    $noWebsite = Lead::factory()->withoutWebsite()->create();
    $noEmail = Lead::factory()->create();
    $complete = Lead::factory()->withEmail()->create();
    $closed = Lead::factory()->withoutWebsite()->status(LeadStatus::NotAFit)->create();
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(ListLeads::class)
        ->set('activeTab', 'needs_info')
        ->assertCanSeeTableRecords([$noWebsite, $noEmail])
        ->assertCanNotSeeTableRecords([$complete, $closed]);
});

it('assigns leads in bulk and logs it on each timeline', function () {
    $leads = Lead::factory()->count(2)->create();
    $owner = userWithRole(Role::Sender);
    $this->actingAs(userWithRole(Role::Admin));

    Livewire::test(ListLeads::class)
        ->selectTableRecords($leads->modelKeys())
        ->callAction(TestAction::make('bulkAssignOwner')->table()->bulk(), ['owner_id' => $owner->id])
        ->assertNotified();

    foreach ($leads as $lead) {
        expect($lead->fresh()->owner_id)->toBe($owner->id)
            ->and($lead->events()->where('type', LeadEventType::OwnerChanged)->sole()->data['to_name'])->toBe($owner->name);
    }
});

it('changes status in bulk only where the move is allowed', function () {
    $canMove = Lead::factory()->status(LeadStatus::Enriched)->create();
    $cannotMove = Lead::factory()->status(LeadStatus::New)->create();
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(ListLeads::class)
        ->selectTableRecords([$canMove->id, $cannotMove->id])
        ->callAction(TestAction::make('bulkChangeStatus')->table()->bulk(), ['status' => LeadStatus::Triaged->value])
        ->assertNotified('Moved 1 lead to Triaged');

    expect($canMove->fresh()->status)->toBe(LeadStatus::Triaged)
        ->and($cannotMove->fresh()->status)->toBe(LeadStatus::New);
});

it('adds a lead by hand with its email', function () {
    $this->actingAs($user = userWithRole(Role::Researcher));

    Livewire::test(CreateLead::class)
        ->fillForm(['name' => 'Corner Bakery', 'website' => 'cornerbakery.example', 'phone' => '(512) 555-0142', 'country' => 'US', 'email' => 'Hello@CornerBakery.example'])
        ->call('create')
        ->assertHasNoFormErrors();

    $lead = Lead::sole();

    expect($lead)
        ->source->toBe(LeadSource::Manual)
        ->website->toBe('https://cornerbakery.example')
        ->phone_e164->toBe('+15125550142')
        ->owner_id->toBe($user->id)
        ->and($lead->primaryEmail->email)->toBe('hello@cornerbakery.example')
        ->and($lead->primaryEmail->source)->toBe(DataSource::Manual);
});

it('warns about a likely duplicate, then creates it on the second press', function () {
    $existing = Lead::factory()->create(['website' => 'https://www.northside.example']);
    $this->actingAs(userWithRole(Role::Researcher));

    $page = Livewire::test(CreateLead::class)
        ->fillForm(['name' => 'Northside Plumbing Inc', 'website' => 'northside.example', 'country' => 'US'])
        ->call('create')
        ->assertNotified('This looks like a lead you already have');

    expect(Lead::count())->toBe(1)
        ->and($page->get('acknowledgedDuplicateId'))->toBe($existing->id);

    $page->call('create')->assertHasNoFormErrors();

    expect(Lead::count())->toBe(2);
});

it('logs which fields were edited and who now owns the lead', function () {
    $lead = Lead::factory()->create(['category' => 'Plumber', 'owner_id' => null]);
    $owner = userWithRole(Role::Sender);
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
        ->fillForm(['category' => 'Emergency plumber', 'owner_id' => $owner->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($lead->events()->where('type', LeadEventType::Edited)->sole()->data['fields'])->toBe(['category'])
        ->and($lead->fresh()->owner_id)->toBe($owner->id)
        ->and($lead->events()->where('type', LeadEventType::OwnerChanged)->exists())->toBeTrue();
});

it('adds an email by hand and makes it the main address', function () {
    $lead = Lead::factory()->create();
    $old = LeadEmail::factory()->for($lead)->primary()->create();
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(EmailsRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => ViewLead::class])
        ->callAction(TestAction::make('create')->table(), ['email' => 'owner@lead.example', 'is_primary' => true])
        ->assertHasNoFormErrors();

    expect($lead->primaryEmail()->first()->email)->toBe('owner@lead.example')
        ->and($lead->primaryEmail()->first()->source)->toBe(DataSource::Manual)
        ->and($old->fresh()->is_primary)->toBeFalse();
});

it('rejects an email the lead already has', function () {
    $lead = Lead::factory()->create();
    LeadEmail::factory()->for($lead)->create(['email' => 'office@lead.example']);
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(EmailsRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => ViewLead::class])
        ->callAction(TestAction::make('create')->table(), ['email' => 'office@lead.example'])
        ->assertHasFormErrors(['email' => 'unique']);
});

it('recognises the platform of a pasted social link', function (string $url, SocialPlatform $platform) {
    expect(SocialsRelationManager::platformFor($url))->toBe($platform);
})->with([
    ['https://www.facebook.com/northside', SocialPlatform::Facebook],
    ['https://instagram.com/northside', SocialPlatform::Instagram],
    ['https://youtu.be/abc123', SocialPlatform::YouTube],
    ['https://x.com/northside', SocialPlatform::X],
]);

it('merges a duplicate from the lead page', function () {
    $lead = Lead::factory()->create(['name' => 'Keep Me']);
    $duplicate = Lead::factory()->create(['name' => 'Drop Me']);
    $this->actingAs(userWithRole(Role::Researcher));

    Livewire::test(ViewLead::class, ['record' => $lead->getRouteKey()])
        ->callAction('merge', ['other_id' => $duplicate->id, 'keep' => 'this'])
        ->assertNotified('Leads merged');

    expect(Lead::find($duplicate->id))->toBeNull()
        ->and($lead->events()->where('type', LeadEventType::Merged)->exists())->toBeTrue();
});
