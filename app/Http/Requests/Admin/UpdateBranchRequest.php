<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('branch')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $branch = $this->route('branch');

        return [
            'primary_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'code' => ['required', 'alpha_dash:ascii', 'max:50', Rule::unique('branches', 'code')->ignore($branch?->getKey())],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'manager_user_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'sales_target' => ['nullable', 'numeric', 'min:0'],
            'price_configuration' => ['required', 'string', 'max:100'],
            'closing_configuration' => ['required', 'string', 'max:100'],
            'is_closing_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branch = $this->route('branch');

            if ($branch?->has_transactions && $this->input('code') !== $branch->code) {
                $validator->errors()->add('code', 'Kode cabang tidak boleh diubah setelah dipakai transaksi.');
            }
        });
    }
}
