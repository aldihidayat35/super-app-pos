<?php

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceRuleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $method = $this->input('margin_method');
        $this->merge([
            'branch_id' => $this->filled('branch_id') ? $this->input('branch_id') : null,
            'customer_category' => $this->filled('customer_category') ? trim((string) $this->input('customer_category')) : null,
            'minimum_margin_percent' => $method === 'percent' ? $this->input('minimum_margin_percent') : null,
            'minimum_margin_amount' => $method === 'nominal' ? $this->input('minimum_margin_amount') : null,
            'starts_at' => $this->filled('starts_at') ? $this->input('starts_at') : null,
            'ends_at' => $this->filled('ends_at') ? $this->input('ends_at') : null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('prices.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'exists:price_rules,id'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:all,retail,b2b,pos'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('is_active', true)],
            'customer_category' => ['nullable', 'string', 'max:60'],
            'margin_method' => ['required', 'in:percent,nominal'],
            'minimum_margin_percent' => ['nullable', 'required_if:margin_method,percent', 'numeric', 'min:0', 'max:1000'],
            'minimum_margin_amount' => ['nullable', 'required_if:margin_method,nominal', 'numeric', 'min:0'],
            'overpricing_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'max_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama aturan',
            'channel' => 'channel',
            'branch_id' => 'cabang',
            'customer_category' => 'kategori pelanggan',
            'margin_method' => 'metode margin',
            'minimum_margin_percent' => 'margin minimum persentase',
            'minimum_margin_amount' => 'margin minimum nominal',
            'overpricing_tolerance_percent' => 'toleransi overpricing',
            'max_discount_percent' => 'diskon maksimum',
            'priority' => 'prioritas',
            'starts_at' => 'tanggal mulai',
            'ends_at' => 'tanggal selesai',
            'is_active' => 'status aktif',
            'notes' => 'catatan',
        ];
    }
}
