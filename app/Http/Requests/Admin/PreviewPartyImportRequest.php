<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PreviewPartyImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('type');

        return $type === 'suppliers'
            ? ($this->user()?->can('suppliers.import') ?? false)
            : ($this->user()?->can('customers.import') ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120']];
    }
}
