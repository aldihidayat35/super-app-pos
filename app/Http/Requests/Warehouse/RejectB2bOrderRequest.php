<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class RejectB2bOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('b2b_orders.approve') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => 'alasan penolakan'];
    }
}
