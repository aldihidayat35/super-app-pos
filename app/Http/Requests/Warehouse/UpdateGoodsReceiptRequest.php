<?php

namespace App\Http\Requests\Warehouse;

class UpdateGoodsReceiptRequest extends StoreGoodsReceiptRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['purchase_order_id'] = ['nullable', 'exists:purchase_orders,id'];

        return $rules;
    }
}
