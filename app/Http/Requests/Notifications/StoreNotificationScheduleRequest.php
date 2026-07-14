<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'schedule_key' => ['required', 'string', 'max:100'],
            'frequency' => ['required', Rule::in(['daily'])],
            'run_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'max:80'],
            'report_type' => ['required', Rule::in(['daily_report'])],
            'report_period' => ['required', Rule::in(['yesterday', 'today'])],
            'template_id' => ['nullable', 'exists:notification_templates,id'],
            'channel_types' => ['required', 'array', 'min:1'],
            'channel_types.*' => [Rule::in(['whatsapp', 'telegram'])],
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
