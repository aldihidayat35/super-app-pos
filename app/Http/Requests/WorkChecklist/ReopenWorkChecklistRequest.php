<?php

namespace App\Http\Requests\WorkChecklist;

use Illuminate\Foundation\Http\FormRequest;

class ReopenWorkChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('checklist')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => 'alasan koreksi'];
    }
}
