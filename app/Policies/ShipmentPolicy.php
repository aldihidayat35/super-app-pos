<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('shipments.view') || $user->can('b2b_orders.view');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        if (($user->can('shipments.view') || $user->can('b2b_orders.view')) && ! $user->hasOnlyB2bPortalRoles()) {
            return true;
        }

        return $user->customers()
            ->where('customers.id', $shipment->customer_id)
            ->wherePivot('is_active', true)
            ->whereNull('customer_users.blocked_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('shipments.create') || $user->can('b2b_orders.approve');
    }

    public function update(User $user): bool
    {
        return $user->can('shipments.update') || $user->can('b2b_orders.approve');
    }
}
