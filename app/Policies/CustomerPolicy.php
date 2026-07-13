<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view') || $user->can('customers.view_own');
    }

    public function view(User $user, Customer $customer): bool
    {
        if ($user->can('customers.view')) {
            return true;
        }

        return $user->can('customers.view_own')
            && $customer->users()->where('users.id', $user->id)->wherePivot('is_active', true)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update');
    }

    public function manageAccess(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage_access');
    }

    public function manageSettings(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage_settings') || $user->hasRole('owner_approver');
    }

    public function export(User $user): bool
    {
        return $user->can('customers.export');
    }

    public function import(User $user): bool
    {
        return $user->can('customers.import');
    }
}
