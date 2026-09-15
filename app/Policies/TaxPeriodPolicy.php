<?php

namespace App\Policies;

use App\Models\TaxPeriod;
use App\Models\User;
use App\Support\ApprovalAuthority;

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
        return $user->can('tax.approve')
            && ($user->hasRole(ApprovalAuthority::SYSTEM_HEAD) || $user->hasRole('super_admin'));
    }

    public function export(User $user): bool
    {
        return $user->can('tax.export');
    }
}
