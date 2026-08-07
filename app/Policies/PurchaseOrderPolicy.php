<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase_orders.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.view') && $user->canAccessWorkLocation((int) $purchaseOrder->warehouse?->work_location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase_orders.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.create') && $purchaseOrder->status->canEditItems() && $this->view($user, $purchaseOrder);
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.approve') && $purchaseOrder->status === PurchaseOrderStatus::SUBMITTED && $this->view($user, $purchaseOrder);
    }

    public function send(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.create') && $purchaseOrder->status === PurchaseOrderStatus::APPROVED && $this->view($user, $purchaseOrder);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.create')
            && in_array($purchaseOrder->status, [PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::SUBMITTED, PurchaseOrderStatus::APPROVED], true)
            && ! $purchaseOrder->items()->where('quantity_received', '>', 0)->exists()
            && $this->view($user, $purchaseOrder);
    }

    public function print(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.view') && $this->view($user, $purchaseOrder);
    }

    public function export(User $user): bool
    {
        return $user->can('purchase_orders.export') || $user->can('reports.export') || $user->can('purchase_orders.view');
    }
}
