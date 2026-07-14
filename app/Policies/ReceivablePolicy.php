<?php

namespace App\Policies;

use App\Models\Receivable;
use App\Models\User;

class ReceivablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('receivables.view');
    }

    public function view(User $user, Receivable $receivable): bool
    {
        if (! $user->can('receivables.view')) {
            return false;
        }

        if ($user->hasOnlyB2bPortalRoles()) {
            return $user->customers()
                ->where('customers.id', $receivable->customer_id)
                ->wherePivot('is_active', true)
                ->whereNull('customer_users.blocked_at')
                ->exists();
        }

        return $receivable->work_location_id === null || $user->canAccessWorkLocation((int) $receivable->work_location_id) || $user->hasUnrestrictedLocationScope();
    }

    public function pay(User $user): bool
    {
        return $user->can('receivables.pay') || $user->can('payments.create');
    }

    public function adjust(User $user): bool
    {
        return $user->can('receivables.adjust') || $user->can('approvals.approve');
    }

    public function manageLimit(User $user): bool
    {
        return $user->can('receivables.approve') || $user->can('customers.manage_settings');
    }
}
