<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkChecklist;

class WorkChecklistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('work_checklists.view_own') || $user->can('work_checklists.view_team') || $user->can('work_checklists.view_all');
    }

    public function view(User $user, WorkChecklist $checklist): bool
    {
        if ($user->can('work_checklists.view_all')) {
            return true;
        }

        if ((int) $checklist->user_id === (int) $user->id) {
            return $user->can('work_checklists.view_own');
        }

        if (! $user->can('work_checklists.view_team') || $checklist->work_location_id === null || ! $user->canAccessWorkLocation((int) $checklist->work_location_id)) {
            return false;
        }

        $targetRoles = collect($checklist->role_snapshot);
        foreach ((array) config('work-checklists.team_roles') as $managerRole => $subordinateRoles) {
            if (! is_string($managerRole) || ! is_array($subordinateRoles)) {
                continue;
            }
            $allowedRoles = array_values(array_filter($subordinateRoles, is_string(...)));
            if ($user->hasRole($managerRole) && $targetRoles->intersect($allowedRoles)->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    public function update(User $user, WorkChecklist $checklist): bool
    {
        return (int) $checklist->user_id === (int) $user->id && $user->can('work_checklists.update_own');
    }
}
