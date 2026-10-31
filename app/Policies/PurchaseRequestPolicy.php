<?php

namespace App\Policies;

use App\Enums\PurchaseRequestStatus;
use App\Models\Branch;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\ApprovalAuthority;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase_orders.view') || $user->can('purchase_orders.create') || $user->can('stock.create');
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($purchaseRequest->requester_user_id === $user->id) {
            return true;
        }

        $warehouseLocationId = (int) $purchaseRequest->warehouse?->work_location_id;
        if ($warehouseLocationId > 0 && $user->canAccessWorkLocation($warehouseLocationId)) {
            return true;
        }

        $branchWarehouseIds = Branch::query()
            ->whereIn('work_location_id', $user->permittedWorkLocationIds())
            ->whereNotNull('primary_warehouse_id')
            ->pluck('primary_warehouse_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return in_array((int) $purchaseRequest->warehouse_id, $branchWarehouseIds, true);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase_orders.create') || $user->can('stock.create');
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        $locationId = $purchaseRequest->warehouse?->work_location_id;

        return $user->can('purchase_orders.approve')
            && $purchaseRequest->status === PurchaseRequestStatus::SUBMITTED
            && $locationId !== null
            && ApprovalAuthority::canApproveAt($user, (int) $locationId, ApprovalAuthority::WAREHOUSE_HEAD);
    }

    public function convert(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase_orders.create') && $purchaseRequest->status === PurchaseRequestStatus::APPROVED && $this->view($user, $purchaseRequest);
    }
}
