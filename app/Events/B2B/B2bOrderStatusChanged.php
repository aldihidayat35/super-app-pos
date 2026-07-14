<?php

namespace App\Events\B2B;

use App\Enums\B2bOrderStatus;
use App\Models\B2bOrder;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class B2bOrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly B2bOrder $order,
        public readonly ?B2bOrderStatus $fromStatus,
        public readonly B2bOrderStatus $toStatus,
        public readonly ?User $actor,
        public readonly ?string $note = null,
    ) {}
}
