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
            'file' => ['required', 'file', 'mimes:xlsx', 'max:4096'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => 'jenis data',
            'file' => 'file Excel XLSX',
            'dry_run' => 'mode dry-run',
        ];
    }
}
