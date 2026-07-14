<?php

namespace App\Http\Requests\Attendance;

use App\Enums\EmployeeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id;

        return [
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employeeId)],
            'work_location_id' => ['required', 'exists:work_locations,id'],
            'employee_no' => ['required', 'string', 'max:80', Rule::unique('employees', 'employee_no')->ignore($employeeId)],
            'name' => ['required', 'string', 'max:160'],
            'position' => ['nullable', 'string', 'max:120'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'joined_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_column(EmployeeStatus::cases(), 'value'))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
