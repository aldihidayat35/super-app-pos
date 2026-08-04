<?php

namespace App\Policies;

use App\Models\CustomerPriceOverride;
use App\Models\User;

class CustomerPriceOverridePolicy
{
    public function delete(User $user, CustomerPriceOverride $override): bool
    {
        return $user->hasRole('super_admin');
    }
}
