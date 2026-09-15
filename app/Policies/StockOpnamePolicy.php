<?php

namespace App\Policies;

use App\Enums\StockOpnameStatus;
use App\Models\StockOpname;
use App\Models\User;
use App\Support\ApprovalAuthority;

class StockOpnamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock_adjustments.view');
    }

    public function view(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('stock_adjustments.view') && $user->canAccessWorkLocation((int) $stockOpname->work_location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('stock_adjustments.create');
    }

    public function count(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('stock_adjustments.create')
            && $stockOpname->status === StockOpnameStatus::COUNTING
            && $user->canAccessWorkLocation((int) $stockOpname->work_location_id);
    }

    public function start(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('stock_adjustments.create')
            && $stockOpname->status === StockOpnameStatus::DRAFT
            && $user->canAccessWorkLocation((int) $stockOpname->work_location_id);
    }

    public function submit(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('stock_adjustments.create')
            && $stockOpname->status === StockOpnameStatus::COUNTING
            && $this->view($user, $stockOpname);
    }

    public function approve(User $user, StockOpname $stockOpname): bool
    {
        if (! $user->can('stock_adjustments.approve') || ! $this->view($user, $stockOpname)) {
            return false;
        }

        if ($stockOpname->status !== StockOpnameStatus::PENDING_APPROVAL) {
            return false;
        }

        $location = $stockOpname->workLocation;

        return $location !== null
            && ApprovalAuthority::canApproveAt($user, (int) $location->id, ApprovalAuthority::roleForLocation($location));
    }

    public function complete(User $user, StockOpname $stockOpname): bool
    {
        return $user->can('stock_adjustments.approve')
            && $stockOpname->status === StockOpnameStatus::APPROVED
            && $this->view($user, $stockOpname);
    }
}
