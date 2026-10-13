<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum SearchStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Stopped = 'stopped';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Stopped, self::Failed], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Stopped => 'Stopped',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'primary',
            self::Completed => 'success',
            self::Stopped => 'warning',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Running => Heroicon::OutlinedArrowPath,
            self::Completed => Heroicon::OutlinedCheckCircle,
            self::Stopped => Heroicon::OutlinedPauseCircle,
            self::Failed => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
