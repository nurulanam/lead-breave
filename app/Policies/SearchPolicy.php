<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\SearchStatus;
use App\Models\Search;
use App\Models\User;

class SearchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RunSearches->value);
    }

    public function view(User $user, Search $search): bool
    {
        return $user->can(Permission::RunSearches->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RunSearches->value);
    }

    /**
     * Re-running and stopping count as updates.
     */
    public function update(User $user, Search $search): bool
    {
        return $user->can(Permission::RunSearches->value);
    }

    /**
     * A running search must be stopped first. Its leads are kept.
     */
    public function delete(User $user, Search $search): bool
    {
        return $user->can(Permission::RunSearches->value)
            && ! in_array($search->status, [SearchStatus::Pending, SearchStatus::Running], true);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permission::RunSearches->value);
    }
}
