<?php

namespace App\Http\Requests\Retail;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('is_active', true)],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'emergency_purchase_id' => ['nullable', 'integer', 'exists:emergency_purchases,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('is_active', true)],
            'items.*.warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.selected_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.selected_stock_batch_ids' => ['nullable', 'array'],
            'items.*.selected_stock_batch_ids.*' => ['integer', 'distinct', 'exists:stock_batches,id'],
            'items.*.batch_selection_mode' => ['nullable', Rule::in(['auto', 'manual'])],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::in(array_keys(PaymentMethod::options()))],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:120'],
            'payments.*.notes' => ['nullable', 'string', 'max:1000'],
            'payments.*.due_date' => ['nullable', 'date'],
            'custom_due_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $payments = (array) $this->input('payments', []);
            $hasCredit = collect($payments)->contains(fn ($p) => is_array($p) && ($p['method'] ?? '') === PaymentMethod::CREDIT->value);
            if ($hasCredit && empty($this->input('customer_id'))) {
                $validator->errors()->add('customer_id', 'Metode pembayaran Tempo / Piutang hanya dapat digunakan untuk pelanggan terdaftar.');
            }
        });
    }
}
