<?php

namespace App\Http\Requests\StaffBonus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayStaffBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff_bonuses.pay') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['paid_on' => ['required', 'date', 'before_or_equal:today'], 'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'e_wallet', 'other'])], 'reference_no' => ['required', 'string', 'max:120'], 'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'notes' => ['nullable', 'string', 'max:2000']];
    }

    public function attributes(): array
    {
        return ['paid_on' => 'tanggal pembayaran', 'payment_method' => 'metode pembayaran', 'reference_no' => 'referensi pembayaran', 'proof' => 'bukti pembayaran', 'notes' => 'catatan'];
    }
}
