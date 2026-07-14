<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.view') || $this->user()?->can('dashboard.view') || $this->user()?->can('stock.view') || $this->user()?->can('cash_shifts.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
            'channel' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:80'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
