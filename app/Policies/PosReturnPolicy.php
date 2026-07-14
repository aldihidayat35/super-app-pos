<?php

namespace App\Policies;

use App\Models\PosReturn;
use App\Models\User;

class PosReturnPolicy
{
    public function view(User $user, PosReturn $return): bool
    {
        return $user->can('pos.view') && $user->canAccessWorkLocation((int) $return->work_location_id);
    }
}
