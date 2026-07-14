<?php

namespace App\Policies;

use App\Models\B2bOrder;
use App\Models\User;

class B2bOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('b2b_orders.view');
    }

    public function view(User $user, B2bOrder $order): bool
    {
        if (! $user->can('b2b_orders.view')) {
            return false;
        }

        return $user->customers()
            ->where('customers.id', $order->customer_id)
            ->wherePivot('is_active', true)
            ->whereNull('customer_users.blocked_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('b2b_orders.create');
    }
}
