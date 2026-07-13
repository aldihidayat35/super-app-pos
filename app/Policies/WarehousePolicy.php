<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin.warehouses.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        if (! $user->can('admin.warehouses.view')) {
            return false;
        }

        return $warehouse->work_location_id === null || $user->canAccessWorkLocation((int) $warehouse->work_location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('admin.warehouses.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('admin.warehouses.update');
    }
}
