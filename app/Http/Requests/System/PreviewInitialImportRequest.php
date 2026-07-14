<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewInitialImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['suppliers', 'customers', 'products', 'opening_stocks', 'users', 'locations'])],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }
}
