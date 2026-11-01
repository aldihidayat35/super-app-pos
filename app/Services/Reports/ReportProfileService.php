<?php

namespace App\Services\Reports;

use App\Data\WorkLocationContext;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Support\Collection;

class ReportProfileService
{
    /** @return array<string, mixed> */
    public function profile(string $profile): array
    {
        $definition = config("report_profiles.{$profile}");
        abort_unless(is_array($definition), 404);

        return $definition;
    }

    /** @return array<string, mixed> */
    public function report(string $profile, string $report): array
    {
        $definition = data_get($this->profile($profile), "reports.{$report}");
        abort_unless(is_array($definition), 404);

        return $definition;
    }

    public function authorize(User $user, string $profile): void
    {
        abort_unless($this->canAccess($user, $profile), 403);
    }

    public function canAccess(User $user, string $profile): bool
    {
        $definition = $this->profile($profile);

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasAnyRole($definition['roles']) && $user->can($definition['permission']);
    }

    /** @return Collection<int, WorkLocation> */
    public function locations(User $user, string $profile): Collection
    {
        $definition = $this->profile($profile);
        $type = $definition['location_type'] ?? null;

        if (($definition['location_filter'] ?? true) === false) {
            return collect();
        }

        return WorkLocation::query()
            ->whereIn('id', $user->permittedWorkLocationIds())
            ->where('is_active', true)
            ->when(is_string($type), fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);
    }

    public function selectedLocation(User $user, string $profile, ?int $requestedId = null): ?WorkLocation
    {
        $definition = $this->profile($profile);
        $locations = $this->locations($user, $profile);
        if ($locations->isEmpty()) {
            return null;
        }

        if ($requestedId !== null && ($requested = $locations->firstWhere('id', $requestedId))) {
            return $requested;
        }

        if (($definition['location_type'] ?? null) === null) {
            return null;
        }

        $contextId = app(WorkLocationContext::class)->id;
        if ($contextId !== null && ($context = $locations->firstWhere('id', $contextId))) {
            return $context;
        }

        $defaultId = $user->workLocations()->wherePivot('is_default', true)->value('work_locations.id');

        return $locations->firstWhere('id', $defaultId) ?? $locations->first();
    }

    /** @return list<array<string, mixed>> */
    public function navigationFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $profiles = $user->hasRole('super_admin')
            ? ['system']
            : array_keys(config('report_profiles', []));

        $groups = [];
        foreach ($profiles as $profile) {
            if (! $this->canAccess($user, $profile)) {
                continue;
            }

            $definition = $this->profile($profile);
            $location = $this->selectedLocation($user, $profile, request()->integer('work_location_id') ?: null);
            $label = $definition['label'];
            if ($location !== null && in_array($definition['location_type'] ?? null, ['warehouse', 'branch'], true)) {
                $locationPrefix = $definition['location_type'] === 'branch' ? 'Toko ' : 'Gudang ';
                $locationName = str_starts_with($location->name, $locationPrefix)
                    ? substr($location->name, strlen($locationPrefix))
                    : $location->name;
                $label .= ' '.$locationName;
            }

            $groups[] = [
                'label' => $label,
                'icon' => $definition['icon'],
                'route' => "reports.profiles.{$profile}.show",
                'route_params' => array_filter(['report' => 'overview', 'work_location_id' => $location?->id]),
                'active' => ["reports.profiles.{$profile}.*"],
            ];
        }

        return $groups;
    }
}
