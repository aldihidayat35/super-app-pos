<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseLocation;

class WarehouseLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock.view');
    }

    public function view(User $user, WarehouseLocation $warehouseLocation): bool
    {
        if (! $user->can('stock.view')) {
            return false;
        }

        $workLocationId = $warehouseLocation->warehouse?->work_location_id;

        return $workLocationId === null || $user->canAccessWorkLocation((int) $workLocationId);
    }

    public function create(User $user): bool
    {
        return $user->can('stock.create');
    }

    public function update(User $user, WarehouseLocation $warehouseLocation): bool
    {
        return $user->can('stock.update') && $this->view($user, $warehouseLocation);
    }
}
