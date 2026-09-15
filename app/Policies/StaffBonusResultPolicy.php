<?php

namespace App\Policies;

use App\Models\StaffBonusResult;
use App\Models\User;

class StaffBonusResultPolicy
{
    public function view(User $user, StaffBonusResult $result): bool
    {
        if ($user->can('staff_bonuses.view_all')) {
            return true;
        }
        if ($user->can('staff_bonuses.view_own') && (int) $result->user_id === (int) $user->id) {
            return true;
        }
        if (! $user->can('staff_bonuses.view_team') || $result->work_location_id === null || ! $user->canAccessWorkLocation((int) $result->work_location_id)) {
            return false;
        }
        /** @var array<string, list<string>> $teamRoles */
        $teamRoles = config('staff-bonuses.team_roles');
        $allowed = collect($teamRoles)->only($user->roles->pluck('name')->all())->flatten()->unique();

        return $allowed->contains($result->role_name);
    }
}
