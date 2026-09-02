<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class AssignCustomerSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.customers.assign') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sales_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['sales_user_id' => 'Sales'];
    }
}
