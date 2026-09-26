<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseLocation;

class WarehouseLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock.view') || $user->can('retail.catalog.view');
    }

    public function view(User $user, WarehouseLocation $warehouseLocation): bool
    {
        if (! ($user->can('stock.view') || $user->can('retail.catalog.view'))) {
            return false;
        }

        $workLocationId = $warehouseLocation->workLocationId();

        return $workLocationId === null || $user->canAccessWorkLocation((int) $workLocationId);
    }

    public function create(User $user): bool
    {
        return $user->can('stock.create') || $user->can('retail.catalog.manage');
    }

    public function update(User $user, WarehouseLocation $warehouseLocation): bool
    {
        return ($user->can('stock.update') || $user->can('retail.catalog.manage')) && $this->view($user, $warehouseLocation);
    }
}
