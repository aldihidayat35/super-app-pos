<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->route('user')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'alpha_dash:ascii', 'max:50', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
            'locations' => ['nullable', 'array'],
            'locations.*' => ['integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'default_location_id' => ['nullable', 'integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'location_effective_from' => ['nullable', 'date'],
            'location_effective_until' => ['nullable', 'date', 'after_or_equal:location_effective_from'],
            'location_is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'username' => 'username',
            'email' => 'alamat email',
            'phone_number' => 'nomor WhatsApp',
            'avatar' => 'avatar',
            'is_active' => 'status aktif',
            'password' => 'kata sandi',
            'roles' => 'role',
            'locations' => 'lokasi kerja',
            'default_location_id' => 'lokasi utama',
            'location_effective_from' => 'tanggal mulai lokasi',
            'location_effective_until' => 'tanggal akhir lokasi',
            'location_is_active' => 'status lokasi kerja',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $locations = $this->integerList($this->input('locations', []));
            $default = $this->input('default_location_id');

            if ($default !== null && ! in_array((int) $default, $locations, true)) {
                $validator->errors()->add('default_location_id', 'Lokasi utama harus termasuk lokasi kerja yang dipilih.');
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
