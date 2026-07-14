<?php

namespace App\Policies;

use App\Enums\CashShiftStatus;
use App\Models\CashShift;
use App\Models\User;

class CashShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cash_shifts.view');
    }

    public function view(User $user, CashShift $shift): bool
    {
        return $user->can('cash_shifts.view') && $user->canAccessWorkLocation((int) $shift->work_location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('cash_shifts.create');
    }

    public function expense(User $user, CashShift $shift): bool
    {
        return $user->can('cash_shifts.create')
            && $this->view($user, $shift)
            && $shift->status === CashShiftStatus::OPEN;
    }

    public function close(User $user, CashShift $shift): bool
    {
        return $user->can('cash_shifts.create')
            && $this->view($user, $shift)
            && (int) $shift->cashier_user_id === (int) $user->id
            && in_array($shift->status, [CashShiftStatus::OPEN, CashShiftStatus::REJECTED], true);
    }

    public function approve(User $user, CashShift $shift): bool
    {
        return $user->can('cash_shifts.approve')
            && $this->view($user, $shift)
            && $shift->status === CashShiftStatus::CLOSING_SUBMITTED;
    }
}
