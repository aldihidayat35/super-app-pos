<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\SyncRolePermissionsRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $search = trim((string) $request->query('q'));

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.roles.index', compact('roles', 'search'));
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', [
            'role' => new Role(['guard_name' => 'web', 'is_system' => false]),
            'permissions' => $this->groupedPermissions(),
            'selectedPermissions' => collect(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $permissionIds = $this->integerList($validated['permissions'] ?? []);

        $role = DB::transaction(function () use ($request, $validated, $permissionIds): Role {
            $role = Role::query()->create([
                'name' => $validated['name'],
                'label' => $validated['label'],
                'guard_name' => 'web',
                'description' => $validated['description'] ?? null,
                'is_system' => false,
            ]);

            $role->syncPermissions($permissionIds);
            $this->audit($request, $role, 'admin.role.created', ['permissions' => $permissionIds]);

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.show', $role)->with('notification', [
            'type' => 'success',
            'message' => 'Role berhasil dibuat.',
        ]);
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);

        return view('admin.roles.show', [
            'role' => $role->load(['permissions', 'users']),
            'permissions' => $this->groupedPermissions(),
            'selectedPermissions' => $role->permissions->pluck('id'),
        ]);
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => $this->groupedPermissions(),
            'selectedPermissions' => $role->permissions->pluck('id'),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();
        $permissionIds = $this->integerList($validated['permissions'] ?? []);

        DB::transaction(function () use ($request, $role, $validated, $permissionIds): void {
            $role->forceFill([
                'name' => $validated['name'],
                'label' => $validated['label'],
                'description' => $validated['description'] ?? null,
            ])->save();

            $role->syncPermissions($permissionIds);
            $this->audit($request, $role, 'admin.role.updated', ['permissions' => $permissionIds]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.show', $role)->with('notification', [
            'type' => 'success',
            'message' => 'Role berhasil diperbarui.',
        ]);
    }

    public function duplicate(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $copy = DB::transaction(function () use ($request, $role): Role {
            $copy = Role::query()->create([
                'name' => $this->uniqueRoleName($role->name.'_copy'),
                'label' => trim(((string) ($role->getAttribute('label') ?: $role->name)).' Salinan'),
                'guard_name' => $role->guard_name,
                'description' => $role->getAttribute('description'),
                'is_system' => false,
            ]);

            $copy->syncPermissions($role->permissions);
            $this->audit($request, $copy, 'admin.role.duplicated', ['source_role_id' => $role->id]);

            return $copy;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.edit', $copy)->with('notification', [
            'type' => 'success',
            'message' => 'Role berhasil disalin. Silakan sesuaikan sebelum digunakan.',
        ]);
    }

    public function updatePermissions(SyncRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        $permissionIds = $this->integerList($request->validated('permissions', []));

        DB::transaction(function () use ($role, $permissionIds): void {
            $role->syncPermissions($permissionIds);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.show', $role)->with('notification', [
            'type' => 'success',
            'message' => 'Matriks permission role berhasil diperbarui.',
        ]);
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);
        abort_if((bool) $role->getAttribute('is_system'), 403, 'Role sistem tidak boleh dihapus.');
        abort_if($role->users()->count() > 0, 403, 'Role yang masih dipakai pengguna tidak boleh dihapus.');

        DB::transaction(function () use ($request, $role): void {
            $this->audit($request, $role, 'admin.role.deleted', [
                'role_name' => $role->name,
                'role_label' => $role->getAttribute('label'),
            ]);

            $role->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')->with('notification', [
            'type' => 'success',
            'message' => 'Role berhasil dihapus.',
        ]);
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

    private function groupedPermissions(): mixed
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('action')
            ->orderBy('name')
            ->get()
            ->each(fn (Permission $permission) => $permission->setAttribute('help_text', $this->permissionHelpText($permission)))
            ->groupBy(fn (Permission $permission): string => (string) ($permission->getAttribute('module') ?: explode('.', $permission->name)[0]));
    }

    private function permissionHelpText(Permission $permission): string
    {
        $label = (string) ($permission->getAttribute('label') ?: $permission->name);
        $name = (string) $permission->name;
        $action = str($name)->afterLast('.')->replace('_', ' ')->lower()->value();
        $module = str((string) ($permission->getAttribute('module') ?: str($name)->before('.')->value()))->replace(['_', '-'], ' ')->title()->value();
        $actionText = [
            'view' => 'melihat data',
            'create' => 'menambah data baru',
            'update' => 'mengubah data',
            'delete' => 'menghapus data',
            'approve' => 'menyetujui proses',
            'void' => 'membatalkan transaksi dengan jejak audit',
            'export' => 'mengunduh atau mengekspor data',
            'manage settings' => 'mengatur konfigurasi',
            'view sensitive' => 'melihat informasi sensitif',
        ][$action] ?? 'menggunakan fitur ini';

        return "Memberi izin untuk {$actionText} pada area {$module}. Centang hanya jika role ini benar-benar membutuhkan akses tersebut. ({$label})";
    }

    private function uniqueRoleName(string $baseName): string
    {
        $name = $baseName;
        $counter = 2;

        while (Role::query()->where('name', $name)->exists()) {
            $name = $baseName.'_'.$counter;
            $counter++;
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(Request $request, Role $role, string $event, array $properties = []): void
    {
        activity()
            ->causedBy($request->user())
            ->performedOn($role)
            ->withProperties($properties)
            ->log($event);
    }
}
