<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Permission::class);

        $search = trim((string) $request->query('q'));
        $module = $request->query('module');

        $permissions = Permission::query()
            ->withCount('roles')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(is_string($module) && $module !== '', fn ($query) => $query->where('module', $module))
            ->orderBy('module')
            ->orderBy('action')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.permissions.index', [
            'permissions' => $permissions,
            'search' => $search,
            'module' => $module,
            'modules' => Permission::query()
                ->whereNotNull('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
        ]);
    }
}
