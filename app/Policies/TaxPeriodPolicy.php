<?php

namespace App\Policies;

use App\Models\TaxPeriod;
use App\Models\User;

class TaxPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax.access');
    }

    public function view(User $user, TaxPeriod $period): bool
    {
        return $user->can('tax.access');
    }

    public function manage(User $user): bool
    {
        return $user->can('tax.manage');
    }

    public function approve(User $user): bool
    {
        return $user->can('tax.approve');
    }

    public function export(User $user): bool
    {
        return $user->can('tax.export');
    }
}
