<?php

namespace App\Policies;

use App\Enums\RestockRequestStatus;
use App\Models\RestockRequest;
use App\Models\User;

class RestockRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock_transfers.view') || $user->can('stock_transfers.create');
    }

    public function view(User $user, RestockRequest $restockRequest): bool
    {
        $branchLocationId = (int) $restockRequest->branch?->work_location_id;
        $warehouseLocationId = (int) $restockRequest->sourceWarehouse?->work_location_id;

        return $this->viewAny($user)
            && ($user->canAccessWorkLocation($branchLocationId) || $user->canAccessWorkLocation($warehouseLocationId));
    }

    public function create(User $user): bool
    {
        return $user->can('stock_transfers.create');
    }

    public function update(User $user, RestockRequest $restockRequest): bool
    {
        return $user->can('stock_transfers.create')
            && $restockRequest->status === RestockRequestStatus::DRAFT
            && $this->view($user, $restockRequest);
    }

    public function approve(User $user, RestockRequest $restockRequest): bool
    {
        return $user->can('stock_transfers.approve') && $this->view($user, $restockRequest);
    }
}
