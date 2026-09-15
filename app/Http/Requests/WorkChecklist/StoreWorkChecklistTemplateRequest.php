<?php

namespace App\Http\Requests\WorkChecklist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkChecklistTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('work_checklists.manage_templates') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template_key' => ['required_without:source_template_id', 'nullable', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(['daily', 'weekly'])],
            'scope' => ['required', Rule::in(['global', 'location'])],
            'location_type' => ['nullable', Rule::requiredIf(fn (): bool => $this->input('scope') === 'location'), Rule::in(['warehouse', 'branch'])],
            'effective_from' => ['required', 'date', 'after:today'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::in((array) config('work-checklists.internal_roles'))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9._-]+$/', 'distinct'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.guidance' => ['nullable', 'string', 'max:2000'],
            'items.*.is_required' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'template_key' => 'kode template', 'name' => 'nama template', 'frequency' => 'frekuensi',
            'scope' => 'lingkup', 'location_type' => 'jenis lokasi', 'effective_from' => 'tanggal mulai berlaku',
            'roles' => 'role', 'items' => 'poin checklist', 'items.*.item_key' => 'kode poin',
            'items.*.label' => 'uraian poin', 'items.*.guidance' => 'panduan poin',
        ];
    }
}
