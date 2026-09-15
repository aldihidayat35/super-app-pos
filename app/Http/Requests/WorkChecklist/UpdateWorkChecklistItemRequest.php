<?php

namespace App\Http\Requests\WorkChecklist;

use App\Enums\WorkChecklistItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item !== null && $this->user()?->can('update', $item->checklist);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(WorkChecklistItemStatus::class)],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn (): bool => in_array($this->input('status'), [WorkChecklistItemStatus::BLOCKED->value, WorkChecklistItemStatus::NOT_APPLICABLE->value], true))],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['status' => 'status pekerjaan', 'note' => 'catatan'];
    }
}
