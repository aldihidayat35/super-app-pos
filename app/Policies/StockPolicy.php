<?php

namespace App\Policies;

use App\Models\Stock;
use App\Models\User;

class StockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock.view');
    }

    public function view(User $user, Stock $stock): bool
    {
        return $user->can('stock.view') && $user->canAccessWorkLocation((int) $stock->work_location_id);
    }
}
