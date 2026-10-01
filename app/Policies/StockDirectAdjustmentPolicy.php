<?php

namespace App\Policies;

use App\Models\StockDirectAdjustment;
use App\Models\User;
use App\Support\ApprovalAuthority;

class StockDirectAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock_direct_adjustments.view');
    }

    public function view(User $user, StockDirectAdjustment $adjustment): bool
    {
        return ! $adjustment->isOpeningBalance() && $user->can('stock_direct_adjustments.view') && $user->canAccessWorkLocation((int) $adjustment->work_location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('stock_direct_adjustments.create') && ($user->hasRole('kepala_gudang') || $user->hasRole('kepala_toko'));
    }

    public function update(User $user, StockDirectAdjustment $adjustment): bool
    {
        return $this->create($user) && $this->view($user, $adjustment) && $adjustment->status === 'draft' && (int) $adjustment->created_by === (int) $user->id;
    }

    public function decide(User $user, StockDirectAdjustment $adjustment): bool
    {
        if (! $user->can('stock_direct_adjustments.approve') || $adjustment->status !== 'pending_approval') {
            return false;
        }
        if ($user->hasRole('super_admin')) {
            return true;
        }
        $location = $adjustment->workLocation;

        return ApprovalAuthority::canApproveAt($user, (int) $location->id, ApprovalAuthority::roleForLocation($location));
    }
}
