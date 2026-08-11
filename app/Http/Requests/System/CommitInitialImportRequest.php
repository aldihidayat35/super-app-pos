<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommitInitialImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['opening_stocks'])],
            'confirmation' => ['required', Rule::in(['COMMIT STOK AWAL'])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => 'jenis data',
            'confirmation' => 'konfirmasi commit',
        ];
    }
}
