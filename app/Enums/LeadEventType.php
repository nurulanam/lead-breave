<?php

namespace App\Enums;

/**
 * Entries on a lead's activity timeline. Later phases add their own cases.
 */
enum LeadEventType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case LinkedToSearch = 'linked_to_search';
    case Edited = 'edited';
    case OwnerChanged = 'owner_changed';
    case Merged = 'merged';
    case Imported = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Lead created',
            self::StatusChanged => 'Status changed',
            self::LinkedToSearch => 'Found by another search',
            self::Edited => 'Details edited',
            self::OwnerChanged => 'Owner changed',
            self::Merged => 'Duplicate merged in',
            self::Imported => 'Updated by a CSV import',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Created => 'heroicon-m-sparkles',
            self::StatusChanged => 'heroicon-m-arrow-right-circle',
            self::LinkedToSearch => 'heroicon-m-magnifying-glass',
            self::Edited => 'heroicon-m-pencil-square',
            self::OwnerChanged => 'heroicon-m-user-circle',
            self::Merged => 'heroicon-m-arrows-pointing-in',
            self::Imported => 'heroicon-m-arrow-up-tray',
        };
    }
}
