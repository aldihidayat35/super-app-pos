<?php

namespace App\Policies;

use App\Models\SupplierScore;
use App\Models\User;

class SupplierScorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.view') || $user->can('suppliers.view');
    }

    public function view(User $user, SupplierScore $supplierScore): bool
    {
        return $this->viewAny($user);
    }
}
