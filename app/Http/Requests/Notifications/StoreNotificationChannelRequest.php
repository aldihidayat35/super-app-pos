<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'channel_type' => ['required', Rule::in(['whatsapp', 'telegram'])],
            'endpoint' => ['nullable', 'url', 'max:255'],
            'auth_type' => ['required', Rule::in(['bearer', 'query', 'none'])],
            'token' => ['nullable', 'string', 'max:500'],
            'bot_token' => ['nullable', 'string', 'max:500'],
            'sender' => ['nullable', 'string', 'max:120'],
            'default_destination' => ['nullable', 'string', 'max:180'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:60'],
            'retry_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'test_destination' => ['nullable', 'string', 'max:180'],
            'test_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
