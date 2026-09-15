<?php

namespace App\Policies;

use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\ApprovalAuthority;

class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('approvals.view');
    }

    public function view(User $user, ApprovalRequest $approval): bool
    {
        if (! $user->can('approvals.view')) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'owner_viewer', 'owner_approver'])) {
            return true;
        }

        return (int) $approval->requester_user_id === (int) $user->id
            || $this->canDecide($user, $approval);
    }

    public function approve(User $user, ApprovalRequest $approval): bool
    {
        return ApprovalRequestStatus::from((string) $approval->getRawOriginal('current_status')) === ApprovalRequestStatus::PENDING
            && (int) $approval->requester_user_id !== (int) $user->id
            && $this->canDecide($user, $approval);
    }

    public function reject(User $user, ApprovalRequest $approval): bool
    {
        return $this->approve($user, $approval);
    }

    private function canDecide(User $user, ApprovalRequest $approval): bool
    {
        if (! $user->can('approvals.approve')) {
            return false;
        }

        if ($approval->required_permission !== null && ! $user->can($approval->required_permission)) {
            return false;
        }

        $requiredRole = $approval->required_role ?: ApprovalAuthority::roleForLocation($approval->workLocation);

        return ApprovalAuthority::canApproveAt($user, $approval->work_location_id, $requiredRole);
    }
}
