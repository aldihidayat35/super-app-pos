<?php

namespace App\Http\Requests\Control;

use App\Enums\AnomalyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveAnomalyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('audit.resolve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_column(AnomalyStatus::cases(), 'value'))],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
