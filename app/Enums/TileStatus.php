<?php

namespace App\Enums;

enum TileStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Split = 'split';
    case Failed = 'failed';
}
