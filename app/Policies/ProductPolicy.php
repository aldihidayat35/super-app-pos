<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update');
    }

    public function export(User $user): bool
    {
        return $user->can('products.export');
    }

    public function import(User $user): bool
    {
        return $user->can('products.import');
    }

    public function printBarcode(User $user): bool
    {
        return $user->can('products.print_barcode');
    }

    public function viewSensitiveMargin(User $user): bool
    {
        return $user->can('margins.view_sensitive');
    }
}
