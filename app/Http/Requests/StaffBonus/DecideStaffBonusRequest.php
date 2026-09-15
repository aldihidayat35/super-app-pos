<?php

namespace App\Http\Requests\StaffBonus;

use Illuminate\Foundation\Http\FormRequest;

class DecideStaffBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff_bonuses.approve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:2000']];
    }

    public function attributes(): array
    {
        return ['note' => 'catatan keputusan'];
    }
}
