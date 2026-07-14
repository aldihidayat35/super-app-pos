<?php

namespace App\Policies;

use App\Models\PosSale;
use App\Models\User;

class PosSalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.view');
    }

    public function view(User $user, PosSale $sale): bool
    {
        return $user->can('pos.view') && $user->canAccessWorkLocation((int) $sale->work_location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('pos.create');
    }

    public function void(User $user, PosSale $sale): bool
    {
        return $user->can('pos.void') && $sale->status->canVoid() && $this->view($user, $sale);
    }

    public function return(User $user, PosSale $sale): bool
    {
        return ($user->can('returns.create') || $user->can('pos.void')) && $this->view($user, $sale);
    }
}
