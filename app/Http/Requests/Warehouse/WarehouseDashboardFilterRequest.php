<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseDashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock.view') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'range' => ['nullable', 'string', 'in:daily,monthly,yearly'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_id' => 'gudang',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal selesai',
            'range' => 'rentang grafik',
        ];
    }
}
