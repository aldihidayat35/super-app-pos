<?php

namespace App\Policies;

use App\Models\ProductPrice;
use App\Models\User;

class ProductPricePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prices.view');
    }

    public function update(User $user, ?ProductPrice $price = null): bool
    {
        return $user->can('prices.update');
    }
}
