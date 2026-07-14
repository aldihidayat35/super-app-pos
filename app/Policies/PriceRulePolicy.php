<?php

namespace App\Policies;

use App\Models\PriceRule;
use App\Models\User;

class PriceRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prices.view');
    }

    public function update(User $user, ?PriceRule $rule = null): bool
    {
        return $user->can('prices.update');
    }
}
