<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can('admin.permissions.view');
    }
}
