<?php

namespace App\Policies;

use App\Models\ProductCostHistory;
use App\Models\User;

class ProductCostHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('goods_receipts.view') || $user->can('stock.view') || $user->can('purchase_orders.view');
    }

    public function view(User $user, ProductCostHistory $history): bool
    {
        return $this->viewAny($user);
    }
}
