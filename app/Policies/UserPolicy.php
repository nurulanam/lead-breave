<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isNot($model) && $user->can(Permission::ManageUsers->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permission::ManageUsers->value);
    }
}
