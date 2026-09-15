<?php

namespace App\Http\Requests\StaffBonus;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffBonusAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff_bonuses.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['target_result_id' => ['required', 'integer', 'exists:staff_bonus_results,id'], 'amount' => ['required', 'numeric', 'not_in:0', 'decimal:0,2'], 'reason' => ['required', 'string', 'min:10', 'max:2000']];
    }

    public function attributes(): array
    {
        return ['target_result_id' => 'periode tujuan', 'amount' => 'nilai penyesuaian', 'reason' => 'alasan penyesuaian'];
    }
}
