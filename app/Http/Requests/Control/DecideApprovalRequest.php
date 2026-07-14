<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

class DecideApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approvals.approve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'comments' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
