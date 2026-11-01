<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ProfileReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
            'channel' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:80'],
            'range' => ['nullable', 'string', 'in:daily,monthly,yearly'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal akhir',
            'work_location_id' => 'lokasi kerja',
            'range' => 'rentang grafik',
        ];
    }
}
