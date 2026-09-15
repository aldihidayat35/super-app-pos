<?php

namespace App\Http\Requests\Control;

use App\Models\ApprovalRequest;
use Illuminate\Foundation\Http\FormRequest;

class DecideApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $approval = $this->route('approval');

        return $approval instanceof ApprovalRequest
            && ($this->user()?->can($this->routeIs('approvals.reject') ? 'reject' : 'approve', $approval) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'comments' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
