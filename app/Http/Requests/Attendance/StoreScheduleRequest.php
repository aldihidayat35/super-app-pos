<?php

namespace App\Http\Requests\Attendance;

use App\Enums\EmployeeScheduleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'work_shift_id' => ['required', 'exists:work_shifts,id'],
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'scheduled_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(array_column(EmployeeScheduleStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
