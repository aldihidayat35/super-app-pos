<?php

namespace App\Policies;

use App\Enums\PurchaseRequestStatus;
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
        return $this->viewAny($user) && $user->canAccessWorkLocation((int) $purchaseRequest->warehouse?->work_location_id);
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
