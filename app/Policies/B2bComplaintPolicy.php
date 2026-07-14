<?php

namespace App\Policies;

use App\Models\B2bComplaint;
use App\Models\User;

class B2bComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('complaints.view') || $user->can('b2b_orders.view');
    }

    public function view(User $user, B2bComplaint $complaint): bool
    {
        if ($user->can('complaints.view') && ! $user->hasOnlyB2bPortalRoles()) {
            return true;
        }

        return $user->customers()
            ->where('customers.id', $complaint->customer_id)
            ->wherePivot('is_active', true)
            ->whereNull('customer_users.blocked_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('complaints.create') || $user->can('b2b_orders.create');
    }
}
