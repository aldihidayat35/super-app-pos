<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class SupervisorCheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.approve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:1000']];
    }

    public function attributes(): array
    {
        return ['reason' => 'alasan pencatatan pulang darurat'];
    }
}
