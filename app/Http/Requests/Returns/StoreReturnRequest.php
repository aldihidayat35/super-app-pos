<?php

namespace App\Http\Requests\Returns;

use App\Enums\ReturnCondition;
use App\Enums\ReturnResolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = [];
        foreach ((array) $this->input('items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (filled($item['product_id'] ?? null) || filled($item['quantity_requested'] ?? null)) {
                $items[] = $item;
            }
        }

        $this->merge(['items' => $items]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('returns.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'source_type' => ['required', 'in:supplier,branch,b2b,pos,transfer,manual'],
            'source_id' => ['nullable', 'integer'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'destination_type' => ['nullable', 'string', 'max:80'],
            'destination_id' => ['nullable', 'integer'],
            'destination_name' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'reference_id' => ['nullable', 'integer'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:80'],
            'requested_resolution' => ['required', Rule::in(array_keys(ReturnResolution::options()))],
            'return_date' => ['required', 'date'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', 'in:draft,submit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('is_active', true)],
            'items.*.warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'items.*.source_item_type' => ['nullable', 'string', 'max:80'],
            'items.*.source_item_id' => ['nullable', 'integer'],
            'items.*.source_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_requested' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost_snapshot' => ['nullable', 'numeric', 'min:0'],
            'items.*.condition' => ['required', Rule::in(array_keys(ReturnCondition::options()))],
            'items.*.reason' => ['nullable', 'string', 'max:80'],
            'items.*.resolution' => ['nullable', Rule::in(array_keys(ReturnResolution::options()))],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
