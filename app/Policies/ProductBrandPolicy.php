<?php

namespace App\Policies;

use App\Models\ProductBrand;
use App\Models\User;

class ProductBrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, ProductBrand $brand): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, ProductBrand $brand): bool
    {
        return $user->can('products.update');
    }
}
