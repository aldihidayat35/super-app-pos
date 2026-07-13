<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentSequencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.settings.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sequences' => ['required', 'array'],
            'sequences.*.document_type' => ['required', 'string', 'max:50'],
            'sequences.*.prefix' => ['required', 'alpha_dash:ascii', 'max:30'],
            'sequences.*.next_number' => ['required', 'integer', 'min:1'],
            'sequences.*.padding' => ['required', 'integer', 'min:3', 'max:10'],
            'sequences.*.reset_yearly' => ['nullable', 'boolean'],
            'sequences.*.format' => ['required', 'string', 'max:100'],
        ];
    }
}
