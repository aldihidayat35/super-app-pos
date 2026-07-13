<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncUserLocationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignLocations', $this->route('user')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'locations' => ['nullable', 'array'],
            'locations.*' => ['integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'default_location_id' => ['nullable', 'integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'locations' => 'lokasi kerja',
            'default_location_id' => 'lokasi default',
            'effective_from' => 'tanggal mulai',
            'effective_until' => 'tanggal akhir',
            'is_active' => 'status assignment',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $locations = $this->integerList($this->input('locations', []));
            $default = $this->input('default_location_id');

            if ($default !== null && ! in_array((int) $default, $locations, true)) {
                $validator->errors()->add('default_location_id', 'Lokasi default harus termasuk lokasi kerja yang dipilih.');
            }
        });
    }

    /**
     * @return list<int>
     */
    private function integerList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
