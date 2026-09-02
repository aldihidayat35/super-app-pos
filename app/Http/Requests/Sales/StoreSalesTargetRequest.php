<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.targets.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sales_user_id' => ['required', 'integer', 'exists:users,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'target_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'bonus_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'sales_user_id' => 'Sales',
            'month' => 'bulan',
            'year' => 'tahun',
            'target_amount' => 'target penjualan',
            'bonus_percentage' => 'persentase bonus',
        ];
    }
}
