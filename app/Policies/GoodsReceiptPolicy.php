<?php

namespace App\Policies;

use App\Enums\GoodsReceiptStatus;
use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('goods_receipts.view');
    }

    public function view(User $user, GoodsReceipt $goodsReceipt): bool
    {
        $locationId = $goodsReceipt->destination_work_location_id ?: $goodsReceipt->warehouse?->work_location_id;

        return $user->can('goods_receipts.view') && $user->canAccessWorkLocation((int) $locationId);
    }

    public function create(User $user): bool
    {
        return $user->can('goods_receipts.create');
    }

    public function update(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->can('goods_receipts.create')
            && $goodsReceipt->status === GoodsReceiptStatus::DRAFT
            && $this->view($user, $goodsReceipt);
    }

    public function post(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $this->update($user, $goodsReceipt);
    }
}
