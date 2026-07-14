<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Shipment::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'b2b_order_id' => ['required', 'exists:b2b_orders,id'],
            'delivery_method' => ['required', 'in:courier,pickup,expedition,internal'],
            'courier_name' => ['nullable', 'string', 'max:120'],
            'driver_name' => ['nullable', 'string', 'max:120'],
            'vehicle_no' => ['nullable', 'string', 'max:80'],
            'tracking_no' => ['nullable', 'string', 'max:120'],
            'scheduled_date' => ['nullable', 'date'],
            'shipping_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'planned_quantities' => ['required', 'array', 'min:1'],
            'planned_quantities.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
