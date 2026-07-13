<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkLocation;

class WorkLocationPolicy
{
    public function view(User $user, WorkLocation $workLocation): bool
    {
        return $user->canAccessWorkLocation((int) $workLocation->getKey());
    }
}
