<?php

namespace App\Policies;

use App\Models\User;

class SystemSettingPolicy
{
    public function view(User $user, mixed $model = null): bool
    {
        return $user->can('admin.settings.view');
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->can('admin.settings.update');
    }
}
