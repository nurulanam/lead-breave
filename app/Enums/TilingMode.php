<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TilingMode: string implements HasDescription, HasIcon, HasLabel
{
    case Grid = 'grid';
    case Single = 'single';

    public function getLabel(): string
    {
        return match ($this) {
            self::Grid => 'Full coverage',
            self::Single => 'Quick look',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Grid => 'Splits the area into cells to get past Google\'s 60-result limit.',
            self::Single => 'One query for the whole area. Up to 60 results.',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Grid => Heroicon::OutlinedSquares2x2,
            self::Single => Heroicon::OutlinedStop,
        };
    }
}
