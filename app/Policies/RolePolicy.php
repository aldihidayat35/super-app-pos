<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('admin.roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('admin.roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('admin.roles.update');
    }

    public function updatePermissions(User $user, Role $role): bool
    {
        return $user->can('admin.roles.update');
    }
}
