<?php

namespace App\Http\Requests\Finance;

use App\Services\Finance\AnnualFinanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance_annual.manage') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['account_code' => ['required', Rule::in(array_keys(AnnualFinanceService::ACCOUNTS))], 'amount' => ['required', 'numeric'], 'reason' => ['required', 'string', 'min:5'], 'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']];
    }

    public function attributes(): array
    {
        return ['account_code' => 'akun laporan', 'amount' => 'nominal', 'reason' => 'alasan perubahan', 'proof' => 'lampiran bukti'];
    }
}
