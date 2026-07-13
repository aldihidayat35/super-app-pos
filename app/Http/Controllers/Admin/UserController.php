<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Models\WorkLocation;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('q'));
        $status = $request->query('status');

        $users = User::query()
            ->with(['roles', 'workLocations'])
            ->when($request->filled('role'), function ($query) use ($request): void {
                $query->whereHas('roles', fn ($query) => $query->whereKey((int) $request->query('role')));
            })
            ->when($request->filled('location'), function ($query) use ($request): void {
                $query->whereHas('workLocations', fn ($query) => $query->whereKey((int) $request->query('location')));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
            'roles' => Role::query()->orderBy('name')->get(),
            'locations' => WorkLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'roleFilter' => $request->query('role'),
            'locationFilter' => $request->query('location'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'user' => new User(['is_active' => true]),
            'roles' => Role::query()->orderBy('name')->get(),
            'selectedRoles' => collect(),
            'locations' => WorkLocation::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'selectedLocations' => collect(),
            'defaultLocationId' => null,
            'locationEffectiveFrom' => now()->toDateString(),
            'locationEffectiveUntil' => null,
            'locationIsActive' => true,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($request, $validated): User {
            $roles = $this->integerList($validated['roles'] ?? []);
            $locations = $this->integerList($validated['locations'] ?? []);
            $defaultLocationId = isset($validated['default_location_id']) ? (int) $validated['default_location_id'] : null;
            unset($validated['roles'], $validated['locations'], $validated['default_location_id'], $validated['location_effective_from'], $validated['location_effective_until'], $validated['location_is_active'], $validated['avatar']);

            $validated['is_active'] = $request->boolean('is_active');

            if ($request->hasFile('avatar')) {
                $validated['avatar_path'] = $request->file('avatar')?->store('avatars', 'public');
            }

            $user = User::query()->create($validated);
            $user->syncRoles($roles);
            $this->syncLocations($user, $locations, $defaultLocationId, $request);
            $this->audit($request, $user, 'admin.user.created', ['roles' => $roles, 'locations' => $locations]);

            return $user;
        });

        return redirect()->route('admin.users.show', $user)->with('notification', [
            'type' => 'success',
            'message' => 'Pengguna berhasil dibuat.',
        ]);
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('admin.users.show', [
            'user' => $user->load(['roles.permissions', 'workLocations']),
            'recentActivities' => Activity::query()
                ->where('causer_type', User::class)
                ->where('causer_id', $user->id)
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user->load(['roles', 'workLocations']),
            'roles' => Role::query()->orderBy('name')->get(),
            'selectedRoles' => $user->roles->pluck('id'),
            'locations' => WorkLocation::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'selectedLocations' => $user->workLocations()->pluck('work_locations.id'),
            'defaultLocationId' => $this->defaultLocationId($user),
            'locationEffectiveFrom' => $this->firstLocationPivotValue($user, 'effective_from'),
            'locationEffectiveUntil' => $this->firstLocationPivotValue($user, 'effective_until'),
            'locationIsActive' => (bool) ($this->firstLocationPivotValue($user, 'is_active') ?? true),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->user()?->is($user) && ! $request->boolean('is_active')) {
            throw ValidationException::withMessages([
                'is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.',
            ]);
        }

        DB::transaction(function () use ($request, $user, $validated): void {
            $roles = $this->integerList($validated['roles'] ?? []);
            $locations = $this->integerList($validated['locations'] ?? []);
            $defaultLocationId = isset($validated['default_location_id']) ? (int) $validated['default_location_id'] : null;
            unset($validated['roles'], $validated['locations'], $validated['default_location_id'], $validated['location_effective_from'], $validated['location_effective_until'], $validated['location_is_active'], $validated['avatar']);

            $validated['is_active'] = $request->boolean('is_active');

            if (empty($validated['password'])) {
                unset($validated['password']);
            }

            if ($request->hasFile('avatar')) {
                $validated['avatar_path'] = $request->file('avatar')?->store('avatars', 'public');
            }

            if (($validated['email'] ?? $user->email) !== $user->email) {
                $validated['email_verified_at'] = null;
            }

            $user->fill($validated)->save();
            $user->syncRoles($roles);
            $this->syncLocations($user, $locations, $defaultLocationId, $request);
            $this->audit($request, $user, 'admin.user.updated', ['roles' => $roles, 'locations' => $locations]);
        });

        return redirect()->route('admin.users.show', $user)->with('notification', [
            'type' => 'success',
            'message' => 'Pengguna berhasil diperbarui.',
        ]);
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages([
                'is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.',
            ]);
        }

        DB::transaction(function () use ($request, $user): void {
            $user->forceFill(['is_active' => false])->save();
            $this->audit($request, $user, 'admin.user.deactivated');
        });

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Pengguna berhasil dinonaktifkan.',
        ]);
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->can('admin.users.reset_password'), 403);

        Password::sendResetLink(['email' => $user->email]);
        $this->audit($request, $user, 'admin.user.password_reset_requested');

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Link reset password telah dikirim ke email pengguna.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('admin.users.export'), 403);

        $this->audit($request, $request->user(), 'admin.users.exported');

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['name', 'username', 'email', 'phone_number', 'roles', 'primary_location', 'status', 'last_login_at']);

            User::query()
                ->with(['roles', 'workLocations'])
                ->orderBy('name')
                ->chunk(100, function ($users) use ($handle): void {
                    foreach ($users as $user) {
                        $primaryLocation = $user->workLocations->firstWhere('pivot.is_default', true);
                        $lastLoginAt = $user->getAttribute('last_login_at');
                        fputcsv($handle, [
                            $user->name,
                            $user->username,
                            $user->email,
                            $user->phone_number,
                            $user->roles->pluck('name')->implode('|'),
                            $primaryLocation?->name,
                            $user->is_active ? 'active' : 'inactive',
                            $lastLoginAt instanceof DateTimeInterface
                                ? Carbon::instance($lastLoginAt)->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
                                : null,
                        ]);
                    }
                });

            fclose($handle);
        }, 'users-'.now()->format('YmdHis').'.csv', [
            'Content-Type' => 'text/csv',
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

    /**
     * @param  list<int>  $locations
     */
    private function syncLocations(User $user, array $locations, ?int $defaultLocationId, Request $request): void
    {
        $syncPayload = [];

        foreach (array_values(array_unique($locations)) as $locationId) {
            $syncPayload[$locationId] = [
                'is_default' => $defaultLocationId === $locationId,
                'effective_from' => $request->input('location_effective_from'),
                'effective_until' => $request->input('location_effective_until'),
                'is_active' => $request->boolean('location_is_active', true),
            ];
        }

        $user->workLocations()->sync($syncPayload);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(Request $request, object $subject, string $event, array $properties = []): void
    {
        activity()
            ->causedBy($request->user())
            ->performedOn($subject)
            ->withProperties($properties)
            ->log($event);
    }

    private function defaultLocationId(User $user): ?int
    {
        foreach ($user->workLocations as $location) {
            if ((bool) $location->getRelationValue('pivot')?->getAttribute('is_default')) {
                return (int) $location->id;
            }
        }

        return null;
    }

    private function firstLocationPivotValue(User $user, string $key): mixed
    {
        $location = $user->workLocations->first();

        return $location?->getRelationValue('pivot')?->getAttribute($key);
    }
}
