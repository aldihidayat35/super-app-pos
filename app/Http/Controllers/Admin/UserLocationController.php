<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SyncUserLocationsRequest;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UserLocationController extends Controller
{
    public function edit(User $user): View
    {
        $this->authorize('assignLocations', $user);

        $locations = WorkLocation::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('admin.users.locations', [
            'user' => $user->load('workLocations'),
            'locations' => $locations,
            'selectedLocations' => $user->workLocations->pluck('id'),
            'defaultLocationId' => $this->defaultLocationId($user),
            'effectiveFrom' => $this->firstLocationPivotValue($user, 'effective_from'),
            'effectiveUntil' => $this->firstLocationPivotValue($user, 'effective_until'),
            'assignmentIsActive' => (bool) ($this->firstLocationPivotValue($user, 'is_active') ?? true),
        ]);
    }

    public function update(SyncUserLocationsRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $selected = array_values(array_unique($this->integerList($validated['locations'] ?? [])));
        $defaultLocationId = isset($validated['default_location_id']) ? (int) $validated['default_location_id'] : null;

        DB::transaction(function () use ($request, $user, $selected, $defaultLocationId): void {
            $syncPayload = [];

            foreach ($selected as $id) {
                $syncPayload[$id] = [
                    'is_default' => $defaultLocationId === $id,
                    'effective_from' => $request->input('effective_from'),
                    'effective_until' => $request->input('effective_until'),
                    'is_active' => $request->boolean('is_active', true),
                ];
            }

            $user->workLocations()->sync($syncPayload);
            activity()
                ->causedBy($request->user())
                ->performedOn($user)
                ->withProperties(['locations' => $selected, 'default_location_id' => $defaultLocationId])
                ->log('admin.user.locations_updated');
        });

        return redirect()->route('admin.users.show', $user)->with('notification', [
            'type' => 'success',
            'message' => 'Lokasi kerja pengguna berhasil diperbarui.',
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
