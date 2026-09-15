<?php

namespace App\Policies;

use App\Enums\PriceApprovalStatus;
use App\Models\PriceApprovalRequest;
use App\Models\User;
use App\Support\ApprovalAuthority;

class PriceApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prices.approve') || $user->can('prices.view');
    }

    public function approve(User $user, PriceApprovalRequest $approval): bool
    {
        $locationId = $approval->branch?->work_location_id;
        $requiredRole = $locationId === null ? ApprovalAuthority::WAREHOUSE_HEAD : ApprovalAuthority::STORE_HEAD;

        return $user->can('prices.approve')
            && $approval->status === PriceApprovalStatus::PENDING
            && ApprovalAuthority::canApproveAt($user, $locationId === null ? null : (int) $locationId, $requiredRole);
    }
}
