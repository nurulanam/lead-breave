<?php

namespace App\Enums;

enum SearchStatus: string
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
}
