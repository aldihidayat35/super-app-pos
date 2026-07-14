<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRecipientRequest extends FormRequest
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
            'recipient_type' => ['required', Rule::in(['user', 'role', 'group'])],
            'user_id' => ['nullable', 'exists:users,id'],
            'role_name' => ['nullable', 'string', 'max:120'],
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'channel_type' => ['required', Rule::in(['whatsapp', 'telegram'])],
            'destination' => ['required', 'string', 'max:180'],
            'report_type' => ['required', Rule::in(['daily_report', 'critical_stock', 'receivable_due', 'pending_order', 'overpricing', 'approval', 'closing_difference'])],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'is_verified' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
