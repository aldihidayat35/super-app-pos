<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view') || $user->can('receivables.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (($user->can('invoices.view') || $user->can('receivables.view')) && ! $user->hasOnlyB2bPortalRoles()) {
            return true;
        }

        return $user->customers()
            ->where('customers.id', $invoice->customer_id)
            ->wherePivot('is_active', true)
            ->whereNull('customer_users.blocked_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create') || $user->can('b2b_orders.approve');
    }
}
