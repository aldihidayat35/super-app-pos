<?php

namespace App\Policies;

use App\Models\PosHold;
use App\Models\User;

class PosHoldPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.create');
    }

    public function view(User $user, PosHold $hold): bool
    {
        return $user->can('pos.create')
            && (int) $hold->cashier_user_id === (int) $user->id
            && $user->canAccessWorkLocation((int) $hold->work_location_id);
    }

    public function update(User $user, PosHold $hold): bool
    {
        return $this->view($user, $hold);
    }
}
