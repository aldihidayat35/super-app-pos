<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.export') || $this->user()?->can('audit.export');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'report_type' => ['required', Rule::in(['daily', 'warehouse', 'retail', 'b2b', 'pricing', 'suppliers', 'attendance', 'receivables', 'audit_notifications'])],
            'format' => ['required', Rule::in(['xlsx', 'pdf', 'csv'])],
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
