<?php

namespace App\Policies;

use App\Models\StockBatch;
use App\Models\User;

class StockBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock.view');
    }

    public function view(User $user, StockBatch $stockBatch): bool
    {
        if (! $user->can('stock.view')) {
            return false;
        }

        $stock = $stockBatch->stock;

        return $stock === null || $user->canAccessWorkLocation((int) $stock->work_location_id);
    }
}
