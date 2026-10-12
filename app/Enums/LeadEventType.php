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
}
