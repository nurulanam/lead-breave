<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Researcher = 'researcher';
    case Sender = 'sender';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Researcher => 'Researcher',
            self::Sender => 'Sender',
        };
    }

    /**
     * The permissions granted to this role.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Researcher => [
                Permission::RunSearches,
                Permission::EditLeads,
                Permission::AuditLeads,
            ],
            self::Sender => [
                Permission::EditLeads,
                Permission::AuditLeads,
                Permission::SendOutreach,
            ],
        };
    }
}
