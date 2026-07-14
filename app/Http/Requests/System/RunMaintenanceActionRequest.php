<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunMaintenanceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['up', 'down', 'cache_clear', 'optimize', 'queue_restart'])],
            'confirmation' => ['required', 'string', 'in:SAYA MENGERTI'],
            'message' => ['nullable', 'string', 'max:255'],
        ];
    }
}
