<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::EditLeads->value);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->can(Permission::EditLeads->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::EditLeads->value);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can(Permission::EditLeads->value);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->can(Permission::DeleteLeads->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permission::DeleteLeads->value);
    }
}
