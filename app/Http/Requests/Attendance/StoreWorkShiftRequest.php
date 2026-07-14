<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $shiftId = ($this->route('workShift') ?? $this->route('work_shift'))?->id;

        return [
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'code' => ['required', 'string', 'max:80', Rule::unique('work_shifts', 'code')->ignore($shiftId)],
            'name' => ['required', 'string', 'max:120'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_cross_midnight' => ['nullable', 'boolean'],
            'tolerance_late_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'tolerance_early_leave_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['integer', 'min:0', 'max:6'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
