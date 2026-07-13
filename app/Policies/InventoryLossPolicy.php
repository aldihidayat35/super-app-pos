<?php

namespace App\Policies;

use App\Enums\InventoryLossStatus;
use App\Models\InventoryLoss;
use App\Models\User;

class InventoryLossPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('losses.view');
    }

    public function view(User $user, InventoryLoss $inventoryLoss): bool
    {
        return $user->can('losses.view') && $user->canAccessWorkLocation((int) $inventoryLoss->work_location_id);
    }

    public function approve(User $user, InventoryLoss $inventoryLoss): bool
    {
        return $user->can('returns.approve') && $inventoryLoss->status === InventoryLossStatus::PENDING_APPROVAL && $this->view($user, $inventoryLoss);
    }
}
