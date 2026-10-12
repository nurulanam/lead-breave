<?php

namespace App\Enums;

enum Permission: string
{
    case ManageUsers = 'manage users';
    case ManageSettings = 'manage settings';
    case ViewSystemTools = 'view system tools';
    case RunSearches = 'run searches';
    case EditLeads = 'edit leads';
    case AuditLeads = 'audit leads';
    case SendOutreach = 'send outreach';
}
