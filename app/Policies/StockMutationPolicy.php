<?php

namespace App\Policies;

use App\Models\StockMutation;
use App\Models\User;

class StockMutationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock.view');
    }

    public function view(User $user, StockMutation $stockMutation): bool
    {
        return $user->can('stock.view') && $user->canAccessWorkLocation((int) $stockMutation->work_location_id);
    }
}
