<?php

namespace App\Policies;

use App\Enums\ReturnStatus;
use App\Models\ReturnDocument;
use App\Models\User;

class ReturnDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('returns.view');
    }

    public function view(User $user, ReturnDocument $returnDocument): bool
    {
        return $user->can('returns.view') && $user->canAccessWorkLocation((int) $returnDocument->work_location_id);
    }

    public function inspect(User $user, ReturnDocument $returnDocument): bool
    {
        return $user->can('returns.inspect') && $returnDocument->status === ReturnStatus::SUBMITTED && $this->view($user, $returnDocument);
    }

    public function approve(User $user, ReturnDocument $returnDocument): bool
    {
        return $user->can('returns.approve') && $returnDocument->status === ReturnStatus::PENDING_APPROVAL && $this->view($user, $returnDocument);
    }

    public function settle(User $user, ReturnDocument $returnDocument): bool
    {
        return $user->can('returns.settle') && in_array($returnDocument->status, [ReturnStatus::INSPECTED, ReturnStatus::APPROVED], true) && $this->view($user, $returnDocument);
    }
}
