<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class CheckAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.check') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['nullable', 'in:login,pin,qr,supervisor'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'device_info' => ['nullable', 'string', 'max:120'],
            'location_note' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
