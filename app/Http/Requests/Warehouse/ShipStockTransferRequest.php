<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ShipStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('stock_transfers.ship') || $user->can('stock_transfers.create'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'carrier' => ['nullable', 'string', 'max:120'],
            'vehicle_number' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'proof_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
