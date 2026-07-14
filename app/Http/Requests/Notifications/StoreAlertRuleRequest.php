<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('notifications.update') ?? false) || ($this->user()?->can('audit.resolve') ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rule_key' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:160'],
            'alert_type' => ['required', Rule::in(['critical_stock', 'receivable_due', 'pending_order', 'overpricing', 'margin', 'closing_difference', 'void'])],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'threshold_value' => ['nullable', 'numeric', 'min:0'],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'channel_types' => ['required', 'array', 'min:1'],
            'channel_types.*' => [Rule::in(['whatsapp', 'telegram'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
