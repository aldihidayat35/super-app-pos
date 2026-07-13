<?php

namespace App\Http\Requests\Returns;

use App\Enums\ReturnResolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('returns.settle') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(array_keys(ReturnResolution::options()))],
            'document_no' => ['nullable', 'string', 'max:120'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
