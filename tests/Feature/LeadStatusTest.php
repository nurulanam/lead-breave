<?php

use App\Enums\LeadEventType;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Exceptions\InvalidLeadTransition;
use App\Models\Lead;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('moves a lead along an allowed transition and logs it', function () {
    $user = userWithRole(Role::Researcher);
    $lead = Lead::factory()->status(LeadStatus::Enriched)->create();

    $lead->transitionTo(LeadStatus::Triaged, $user, ['reason' => 'Looks dated']);

    expect($lead->fresh()->status)->toBe(LeadStatus::Triaged);

    $event = $lead->events()->where('type', LeadEventType::StatusChanged)->sole();

    expect($event->user_id)->toBe($user->id)
        ->and($event->data)->toBe(['from' => 'enriched', 'to' => 'triaged', 'reason' => 'Looks dated']);
});

it('refuses an illegal transition and changes nothing', function () {
    $lead = Lead::factory()->status(LeadStatus::New)->create();

    expect(fn () => $lead->transitionTo(LeadStatus::Contacted))
        ->toThrow(InvalidLeadTransition::class, 'A lead cannot move from "New" to "Contacted".');

    expect($lead->fresh()->status)->toBe(LeadStatus::New)
        ->and($lead->events()->where('type', LeadEventType::StatusChanged)->exists())->toBeFalse();
});

it('checks the stored status, not a stale copy in memory', function () {
    $lead = Lead::factory()->status(LeadStatus::Ready)->create();
    $staleCopy = Lead::find($lead->id);

    $lead->transitionTo(LeadStatus::Contacted);

    expect(fn () => $staleCopy->transitionTo(LeadStatus::Contacted))
        ->toThrow(InvalidLeadTransition::class);
});

it('can reach every status from a new lead', function () {
    $reached = [LeadStatus::New];
    $queue = [LeadStatus::New];

    while ($status = array_shift($queue)) {
        foreach ($status->transitions() as $next) {
            if (! in_array($next, $reached, true)) {
                $reached[] = $next;
                $queue[] = $next;
            }
        }
    }

    expect($reached)->toHaveCount(count(LeadStatus::cases()));
});

it('lets closed leads reopen only where intended', function (LeadStatus $status, array $expected) {
    expect($status->isClosed())->toBeTrue()
        ->and($status->transitions())->toBe($expected);
})->with([
    'not a fit reopens' => [LeadStatus::NotAFit, [LeadStatus::New]],
    'lost reopens' => [LeadStatus::Lost, [LeadStatus::New]],
    'won is final' => [LeadStatus::Won, []],
    'unsubscribed is final' => [LeadStatus::Unsubscribed, []],
]);

it('does not allow status to be mass assigned', function () {
    $lead = Lead::factory()->create();

    expect(fn () => $lead->update(['status' => LeadStatus::Won]))
        ->toThrow(MassAssignmentException::class);

    expect($lead->fresh()->status)->toBe(LeadStatus::New);
});
