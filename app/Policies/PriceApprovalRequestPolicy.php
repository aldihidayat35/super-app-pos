<?php

namespace App\Policies;

use App\Enums\PriceApprovalStatus;
use App\Models\PriceApprovalRequest;
use App\Models\User;

class PriceApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prices.approve') || $user->can('prices.view');
    }

    public function approve(User $user, PriceApprovalRequest $approval): bool
    {
        return $user->can('prices.approve') && $approval->status === PriceApprovalStatus::PENDING;
    }
}
