<?php

namespace App\Http\Requests\Receivables;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('receivables.view') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'receivable_id' => ['nullable', 'exists:receivables,id'],
            'channel' => ['required', 'in:manual,wa,telegram,phone,visit'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'note' => ['required', 'string', 'max:2000'],
            'next_follow_up_date' => ['nullable', 'date'],
            'delivery_status' => ['nullable', 'in:draft,sent,failed,promised,paid'],
        ];
    }
}
