<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        if (! $user->can('admin.branches.view')) {
            return false;
        }

        return $branch->work_location_id === null || $user->canAccessWorkLocation((int) $branch->work_location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('admin.branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('admin.branches.update');
    }
}
