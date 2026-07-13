<?php

namespace App\Http\Requests\Returns;

use App\Enums\ReturnCondition;
use App\Enums\ReturnResolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspectReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('returns.inspect') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'items.*.quantity_good' => ['required', 'numeric', 'min:0'],
            'items.*.quantity_damaged' => ['required', 'numeric', 'min:0'],
            'items.*.quantity_rejected' => ['required', 'numeric', 'min:0'],
            'items.*.condition' => ['required', Rule::in(array_keys(ReturnCondition::options()))],
            'items.*.resolution' => ['nullable', Rule::in(array_keys(ReturnResolution::options()))],
            'items.*.responsible_party' => ['nullable', 'string', 'max:80'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
