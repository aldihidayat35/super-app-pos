<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var list<string> */
    private array $affectedRoles = [
        'owner_viewer',
        'owner_approver',
        'admin_config',
        'kepala_gudang',
        'kepala_toko',
    ];

    public function up(): void
    {
        $this->syncConfiguredRoles();
        $this->normalizePriceApprovalLocations();

        if (DB::getSchemaBuilder()->hasTable('emergency_purchase_rules')) {
            DB::table('emergency_purchase_rules')->update(['required_role' => 'kepala_toko', 'updated_at' => now()]);
        }

        if (DB::getSchemaBuilder()->hasTable('approval_requests')) {
            DB::table('approval_requests')->where('current_status', 'pending')->orderBy('id')->eachById(function (object $approval): void {
                [$requiredRole, $workLocationId] = $this->authorityFor($approval);
                DB::table('approval_requests')->where('id', $approval->id)->update([
                    'required_role' => $requiredRole,
                    'work_location_id' => $workLocationId,
                    'updated_at' => now(),
                ]);
                DB::table('approval_steps')->where('approval_request_id', $approval->id)->where('status', 'pending')->update([
                    'required_role' => $requiredRole,
                    'updated_at' => now(),
                ]);
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $owner = Role::query()->where('name', 'owner_approver')->first();
        if ($owner !== null) {
            $owner->givePermissionTo(Permission::query()->where('action', 'approve')->pluck('name')->all());
            $owner->givePermissionTo(Permission::query()->whereIn('name', ['tax.manage'])->pluck('name')->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncConfiguredRoles(): void
    {
        /** @var Collection<string, Permission> $permissions */
        $permissions = collect();
        foreach (config('rbac.permissions', []) as $name => $metadata) {
            $permission = Permission::findOrCreate($name);
            $permission->forceFill([...$metadata, 'is_system' => true])->save();
            $permissions->put($name, $permission);
        }

        foreach ($this->affectedRoles as $roleName) {
            $definition = config("rbac.roles.{$roleName}");
            if (! is_array($definition)) {
                continue;
            }
            $role = Role::findOrCreate($roleName);
            $role->forceFill([
                'label' => $definition['label'],
                'description' => $definition['description'],
                'is_system' => true,
            ])->save();
            $role->syncPermissions($this->expandPermissions($definition['permissions'], $permissions));
        }
    }

    /**
     * @param  list<string>  $patterns
     * @param  Collection<string, Permission>  $permissions
     * @return Collection<int, Permission>
     */
    private function expandPermissions(array $patterns, Collection $permissions): Collection
    {
        $expanded = [];
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                foreach ($permissions->keys() as $permission) {
                    if (str_starts_with($permission, $prefix)) {
                        $expanded[] = $permission;
                    }
                }
            } elseif (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1);
                foreach ($permissions->keys() as $permission) {
                    if (str_ends_with($permission, $suffix)) {
                        $expanded[] = $permission;
                    }
                }
            } elseif ($permissions->has($pattern)) {
                $expanded[] = $pattern;
            }
        }

        return $permissions->only(array_values(array_unique($expanded)))->values();
    }

    private function normalizePriceApprovalLocations(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('price_approval_requests')) {
            return;
        }

        DB::table('price_approval_requests')->whereNull('branch_id')->orderBy('id')->eachById(function (object $approval): void {
            $table = $approval->document_type === 'product_price' ? 'product_prices' : 'customer_price_overrides';
            $branchId = DB::table($table)->where('id', $approval->document_id)->value('branch_id');
            if ($branchId !== null) {
                DB::table('price_approval_requests')->where('id', $approval->id)->update(['branch_id' => $branchId, 'updated_at' => now()]);
            }
        });
    }

    /** @return array{string, int|null} */
    private function authorityFor(object $approval): array
    {
        $workLocationId = $approval->work_location_id === null ? null : (int) $approval->work_location_id;

        if ($approval->module === 'pricing') {
            $branchId = DB::table('price_approval_requests')->where('id', $approval->subject_id)->value('branch_id');
            if ($branchId !== null) {
                $workLocationId = DB::table('branches')->where('id', $branchId)->value('work_location_id');
            }
        }

        $locationType = $workLocationId === null
            ? null
            : DB::table('work_locations')->where('id', $workLocationId)->value('type');
        if ($locationType === 'branch') {
            return ['kepala_toko', $workLocationId];
        }
        if ($locationType === 'warehouse') {
            return ['kepala_gudang', $workLocationId];
        }
        if ($approval->module === 'retail') {
            return ['kepala_toko', $workLocationId];
        }
        if ($approval->module === 'tax') {
            return ['admin_config', $workLocationId];
        }
        if ($approval->module === 'staff_bonuses') {
            $roleName = DB::table('staff_bonus_periods')
                ->join('staff_bonus_programs', 'staff_bonus_programs.id', '=', 'staff_bonus_periods.staff_bonus_program_id')
                ->where('staff_bonus_periods.id', $approval->subject_id)
                ->value('staff_bonus_programs.role_name');

            return [in_array($roleName, ['staf_toko', 'kasir'], true) ? 'kepala_toko' : 'kepala_gudang', $workLocationId];
        }

        return ['kepala_gudang', $workLocationId];
    }
};
