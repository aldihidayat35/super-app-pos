<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.check') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_column(AttendanceRequestType::cases(), 'value'))],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['required', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'replacement_employee_id' => ['nullable', 'exists:employees,id'],
        ];
    }
}
