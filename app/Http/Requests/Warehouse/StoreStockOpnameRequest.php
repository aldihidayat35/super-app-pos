<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_adjustments.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'category_id' => ['nullable', Rule::exists('product_categories', 'id')->where('is_active', true)],
            'pic_user_id' => ['nullable', 'exists:users,id'],
            'method' => ['required', Rule::in(['manual', 'scan', 'import'])],
            'freeze_stock' => ['nullable', 'boolean'],
            'blind_count' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date'],
            'threshold_qty' => ['nullable', 'numeric', 'min:0'],
            'threshold_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', 'in:draft,start'],
        ];
    }
}
