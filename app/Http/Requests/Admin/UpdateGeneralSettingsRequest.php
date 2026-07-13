<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.settings.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['nullable', 'string'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', Rule::in(['Asia/Jakarta'])],
            'locale' => ['required', Rule::in(['id'])],
            'currency' => ['required', Rule::in(['IDR'])],
            'upload_limit_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'default_minimum_margin_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'overpricing_tolerance_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_template' => ['required', 'string', 'max:100'],
            'receipt_template' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
