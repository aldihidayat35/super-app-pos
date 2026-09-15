<?php

namespace App\Http\Requests\Pricing;

use App\Models\PriceApprovalRequest;
use Illuminate\Foundation\Http\FormRequest;

class DecidePriceApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $approval = $this->route('approval');

        return $approval instanceof PriceApprovalRequest
            && ($this->user()?->can('approve', $approval) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
