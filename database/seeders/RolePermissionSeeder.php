<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = $this->permissions();

        $permissionModels = collect();

        foreach ($permissions as $name => $metadata) {
            $permission = Permission::findOrCreate($name);
            $permission->forceFill([
                'label' => $metadata['label'],
                'module' => $metadata['module'],
                'action' => $metadata['action'],
                'description' => $metadata['description'],
                'is_system' => true,
            ])->save();
            $permissionModels->put($name, $permission);
        }

        foreach ($this->roles() as $name => $metadata) {
            $role = Role::findOrCreate($name);
            $role->forceFill([
                'label' => $metadata['label'],
                'description' => $metadata['description'],
                'is_system' => true,
            ])->save();

            $role->syncPermissions($this->expandPermissions($metadata['permissions'], $permissionModels));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return Collection<string, array{label: string, module: string, action: string, description: string}>
     */
    private function permissions(): Collection
    {
        return collect(config('rbac.permissions'));
    }

    /**
     * @return Collection<string, array{label: string, description: string, permissions: list<string>}>
     */
    private function roles(): Collection
    {
        return collect(config('rbac.roles'));
    }

    /**
     * @param  list<string>  $patterns
     * @param  Collection<string, Permission>  $permissions
     * @return Collection<int, Permission>
     */
    private function expandPermissions(array $patterns, Collection $permissions): Collection
    {
        if (in_array('*', $patterns, true)) {
            return $permissions->values();
        }

        $expanded = [];

        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                foreach ($permissions->keys() as $permission) {
                    if (str_starts_with($permission, $prefix)) {
                        $expanded[] = $permission;
                    }
                }

                continue;
            }

            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1);
                foreach ($permissions->keys() as $permission) {
                    if (str_ends_with($permission, $suffix)) {
                        $expanded[] = $permission;
                    }
                }

                continue;
            }

            if ($permissions->has($pattern)) {
                $expanded[] = $pattern;
            }
        }

        return $permissions->only(array_values(array_unique($expanded)))->values();
    }
}
