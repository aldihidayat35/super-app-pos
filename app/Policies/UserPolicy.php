<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('admin.users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('admin.users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('admin.users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('super_admin');
    }

    public function assignLocations(User $user, User $model): bool
    {
        return $user->can('admin.users.assign_locations');
    }
}
