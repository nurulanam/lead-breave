<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a lead is in the workflow. Transitions are only allowed along the
 * edges in transitions(); use Lead::transitionTo() rather than setting the
 * column directly, so every change is checked and logged.
 */
enum LeadStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Enriching = 'enriching';
    case Enriched = 'enriched';
    case NeedsInfo = 'needs_info';
    case Triaged = 'triaged';
    case Audited = 'audited';
    case NotAFit = 'not_a_fit';
    case Ready = 'ready';
    case Contacted = 'contacted';
    case FollowedUp = 'followed_up';
    case Replied = 'replied';
    case MeetingBooked = 'meeting_booked';
    case Won = 'won';
    case Lost = 'lost';
    case Bounced = 'bounced';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Enriching => 'Enriching',
            self::Enriched => 'Enriched',
            self::NeedsInfo => 'Needs info',
            self::Triaged => 'Triaged',
            self::Audited => 'Audited',
            self::NotAFit => 'Not a fit',
            self::Ready => 'Ready to send',
            self::Contacted => 'Contacted',
            self::FollowedUp => 'Followed up',
            self::Replied => 'Replied',
            self::MeetingBooked => 'Meeting booked',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Bounced => 'Bounced',
            self::Unsubscribed => 'Unsubscribed',
        };
    }

    /**
     * The statuses this status may move to.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::New => [self::Enriching, self::Enriched, self::NeedsInfo, self::NotAFit],
            self::Enriching => [self::Enriched, self::NeedsInfo],
            self::Enriched => [self::Enriching, self::NeedsInfo, self::Triaged, self::Audited, self::NotAFit],
            self::NeedsInfo => [self::Enriching, self::Enriched, self::NotAFit],
            self::Triaged => [self::Enriching, self::Audited, self::NotAFit],
            self::Audited => [self::Ready, self::NotAFit, self::Triaged],
            self::Ready => [self::Contacted, self::Audited, self::NotAFit],
            self::Contacted => [self::FollowedUp, self::Replied, self::Bounced, self::Unsubscribed, self::Lost],
            self::FollowedUp => [self::FollowedUp, self::Replied, self::Bounced, self::Unsubscribed, self::Lost],
            self::Replied => [self::MeetingBooked, self::Won, self::Lost, self::Unsubscribed],
            self::MeetingBooked => [self::Won, self::Lost, self::Replied],
            self::Bounced => [self::NeedsInfo],
            self::NotAFit, self::Lost => [self::New],
            self::Won, self::Unsubscribed => [],
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New, self::Enriching => 'gray',
            self::Enriched, self::Triaged => 'info',
            self::NeedsInfo => 'warning',
            self::Audited, self::Ready => 'primary',
            self::Contacted, self::FollowedUp => 'info',
            self::Replied, self::MeetingBooked, self::Won => 'success',
            self::NotAFit, self::Lost, self::Unsubscribed => 'gray',
            self::Bounced => 'danger',
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->transitions(), true);
    }

    /**
     * Statuses where the lead no longer moves without a deliberate reopen.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::NotAFit, self::Won, self::Lost, self::Unsubscribed], true);
    }
}
