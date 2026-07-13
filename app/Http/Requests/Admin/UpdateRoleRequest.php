<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('role')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $roleId = $this->route('role')?->getKey();

        return [
            'name' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique('roles', 'name')->ignore($roleId)],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'kode role',
            'label' => 'label role',
            'description' => 'deskripsi role',
            'permissions' => 'permission',
        ];
    }
}
