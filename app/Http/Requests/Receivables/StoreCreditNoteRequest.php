<?php

namespace App\Http\Requests\Receivables;

use App\Models\Receivable;
use Illuminate\Foundation\Http\FormRequest;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjust', Receivable::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
