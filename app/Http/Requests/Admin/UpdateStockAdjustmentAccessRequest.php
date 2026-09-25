<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockAdjustmentAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'active_from' => ['required', 'date'],
            'active_until' => ['required', 'date', 'after:active_from', 'after:now'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['active_from' => 'waktu mulai', 'active_until' => 'waktu berakhir', 'reason' => 'alasan aktivasi'];
    }
}
