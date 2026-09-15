<?php

namespace App\Http\Requests\Attendance;

use App\Models\WorkShift;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchedulePatternRequest extends FormRequest
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
            'work_location_id' => ['required', 'exists:work_locations,id'],
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
            'days' => ['required', 'array', 'size:7'],
            'days.*' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                if ((string) $value !== 'off' && ! WorkShift::query()->whereKey((int) $value)->exists()) {
                    $fail('Shift yang dipilih tidak valid.');
                }
            }],
        ];
    }

    public function attributes(): array
    {
        return ['employee_id' => 'karyawan', 'work_location_id' => 'lokasi kerja', 'effective_from' => 'tanggal mulai', 'days' => 'pola Senin sampai Minggu'];
    }
}
