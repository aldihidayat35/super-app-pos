<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $user->can('products.update');
    }

    public function import(User $user): bool
    {
        return $user->can('products.import') || $user->can('products.create');
    }

    public function export(User $user): bool
    {
        return $user->can('products.export') || $user->can('products.view');
    }
}
