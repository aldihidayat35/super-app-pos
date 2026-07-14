<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attendance_id' => ['required', 'exists:attendances,id'],
            'proposed_check_in_at' => ['nullable', 'date'],
            'proposed_check_out_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }
}
