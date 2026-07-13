<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('warehouse')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        return [
            'code' => ['required', 'alpha_dash:ascii', 'max:50', Rule::unique('warehouses', 'code')->ignore($warehouse?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'manager_user_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'service_area' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $warehouse = $this->route('warehouse');

            if ($warehouse?->has_transactions && $this->input('code') !== $warehouse->code) {
                $validator->errors()->add('code', 'Kode gudang tidak boleh diubah setelah dipakai transaksi.');
            }
        });
    }
}
