<?php

namespace App\Policies;

use App\Models\ProductRequest;
use App\Models\User;
use App\Support\ApprovalAuthority;

class ProductRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('product_requests.view') || $user->can('product_requests.approve');
    }

    public function view(User $user, ProductRequest $productRequest): bool
    {
        return $user->can('product_requests.view')
            && $user->canAccessWorkLocation((int) $productRequest->branch?->work_location_id);
    }

    public function create(User $user): bool
    {
        return $user->can('product_requests.create');
    }

    public function approve(User $user, ProductRequest $productRequest): bool
    {
        $locationId = $productRequest->branch?->work_location_id;

        return $user->can('product_requests.approve')
            && $productRequest->status === 'pending_approval'
            && $locationId !== null
            && ApprovalAuthority::canApproveAt($user, (int) $locationId, ApprovalAuthority::STORE_HEAD);
    }
}
