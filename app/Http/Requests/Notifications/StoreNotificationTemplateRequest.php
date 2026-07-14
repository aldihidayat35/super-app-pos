<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:160'],
            'channel_type' => ['required', Rule::in(['whatsapp', 'telegram'])],
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'fallback_body' => ['nullable', 'string', 'max:5000'],
            'allowed_variables' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
