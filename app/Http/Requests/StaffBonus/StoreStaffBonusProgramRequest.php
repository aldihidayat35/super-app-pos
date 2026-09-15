<?php

namespace App\Http\Requests\StaffBonus;

use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStaffBonusProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff_bonuses.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = (string) $this->input('role_name');
        /** @var array<string, array{label: string, scope: string, roles: list<string>}> $definitions */
        $definitions = config('staff-bonuses.metrics');
        $metricKeys = collect($definitions)->filter(fn (array $item): bool => in_array($role, $item['roles'], true))->keys()->all();

        return [
            'program_key' => ['required', 'alpha_dash', 'max:100'], 'name' => ['required', 'string', 'max:180'],
            'role_name' => ['required', Rule::in(config('staff-bonuses.eligible_roles'))],
            'work_location_id' => [Rule::requiredIf($role !== 'sales'), 'nullable', 'integer', 'exists:work_locations,id'],
            'month' => ['required', 'integer', 'between:1,12'], 'year' => ['required', 'integer', 'between:2020,2200'],
            'maximum_bonus_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'user_ids' => ['required', 'array', 'min:1'], 'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'metrics' => ['required', 'array', 'min:1'], 'metrics.*.metric_key' => ['required', 'distinct', Rule::in($metricKeys)],
            'metrics.*.target_value' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'metrics.*.weight_percentage' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,4'],
            'user_overrides' => ['sometimes', 'array'], 'user_overrides.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'user_overrides.*.target_override' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'user_overrides.*.maximum_bonus_override' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $metrics = $this->input('metrics', []);
            $weight = is_array($metrics) ? array_sum(array_map(fn ($metric): float => is_array($metric) ? (float) ($metric['weight_percentage'] ?? 0) : 0, $metrics)) : 0;
            if (abs($weight - 100) > 0.0001) {
                $validator->errors()->add('metrics', 'Total bobot KPI wajib tepat 100%.');
            }
            $start = sprintf('%04d-%02d-01', (int) $this->input('year'), (int) $this->input('month'));
            if ($start <= now(config('staff-bonuses.timezone'))->startOfMonth()->toDateString()) {
                $validator->errors()->add('month', 'Target harus dibuat untuk bulan yang belum dimulai.');
            }
            $role = (string) $this->input('role_name');
            $locationId = $this->integer('work_location_id');
            if ($role === 'sales' && $locationId > 0) {
                $validator->errors()->add('work_location_id', 'Program Sales menggunakan lingkup global tanpa lokasi.');
            }
            if ($role !== 'sales' && $locationId > 0) {
                $expected = in_array($role, ['staff_gudang', 'picker_packer'], true) ? 'warehouse' : 'branch';
                if (WorkLocation::query()->whereKey($locationId)->where('type', $expected)->doesntExist()) {
                    $validator->errors()->add('work_location_id', 'Jenis lokasi tidak sesuai dengan role yang dipilih.');
                }
            }
        }];
    }

    public function attributes(): array
    {
        return ['program_key' => 'kode program', 'name' => 'nama program', 'role_name' => 'role', 'work_location_id' => 'lokasi kerja', 'maximum_bonus_amount' => 'bonus maksimum', 'user_ids' => 'akun penerima', 'metrics' => 'KPI'];
    }
}
