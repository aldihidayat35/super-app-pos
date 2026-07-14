<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    public function view(User $user, Payment $payment): bool
    {
        if (($user->can('payments.verify') || $user->can('receivables.view')) && ! $user->hasOnlyB2bPortalRoles()) {
            return true;
        }

        return $user->customers()
            ->where('customers.id', $payment->customer_id)
            ->wherePivot('is_active', true)
            ->whereNull('customer_users.blocked_at')
            ->exists();
    }

    public function verify(User $user): bool
    {
        return $user->can('payments.verify') || $user->can('approvals.approve');
    }
}
