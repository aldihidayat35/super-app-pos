<?php

namespace App\Policies;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock_transfers.view');
    }

    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock_transfers.view')
            && ($user->canAccessWorkLocation((int) $stockTransfer->source_work_location_id)
                || $user->canAccessWorkLocation((int) $stockTransfer->destination_work_location_id));
    }

    public function create(User $user): bool
    {
        return $user->can('stock_transfers.create');
    }

    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock_transfers.create')
            && $stockTransfer->status->canEditItems()
            && $this->view($user, $stockTransfer);
    }

    public function approve(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock_transfers.approve')
            && $stockTransfer->status === StockTransferStatus::PENDING_APPROVAL
            && $this->view($user, $stockTransfer);
    }

    public function pack(User $user, StockTransfer $stockTransfer): bool
    {
        return ($user->can('stock_transfers.pack') || $user->can('stock_transfers.create'))
            && in_array($stockTransfer->status, [StockTransferStatus::APPROVED, StockTransferStatus::PACKING], true)
            && $user->canAccessWorkLocation((int) $stockTransfer->source_work_location_id);
    }

    public function ship(User $user, StockTransfer $stockTransfer): bool
    {
        return ($user->can('stock_transfers.ship') || $user->can('stock_transfers.create'))
            && in_array($stockTransfer->status, [StockTransferStatus::APPROVED, StockTransferStatus::PACKING], true)
            && $user->canAccessWorkLocation((int) $stockTransfer->source_work_location_id);
    }

    public function receive(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock_transfers.receive')
            && $stockTransfer->status->canReceive()
            && $user->canAccessWorkLocation((int) $stockTransfer->destination_work_location_id);
    }

    public function complete(User $user, StockTransfer $stockTransfer): bool
    {
        return ($user->can('stock_transfers.receive') || $user->can('stock_transfers.approve'))
            && $stockTransfer->status === StockTransferStatus::FULLY_RECEIVED
            && $this->view($user, $stockTransfer);
    }

    public function cancel(User $user, StockTransfer $stockTransfer): bool
    {
        return ($user->can('stock_transfers.create') || $user->can('stock_transfers.approve'))
            && $stockTransfer->status->canCancel()
            && $this->view($user, $stockTransfer);
    }
}
