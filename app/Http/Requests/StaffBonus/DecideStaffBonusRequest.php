<?php

namespace App\Http\Requests\StaffBonus;

use App\Models\StaffBonusPeriod;
use Illuminate\Foundation\Http\FormRequest;

class DecideStaffBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $period = $this->route('period');
        if (! $period instanceof StaffBonusPeriod || $period->approval_request_id === null) {
            return false;
        }

        return $this->user()?->can('approve', $period->approvalRequest) ?? false;
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
