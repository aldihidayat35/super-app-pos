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

            $children = [];
            if ($profile === 'owner') {
                $children = [
                    [
                        'label' => 'Ringkasan Eksekutif',
                        'icon' => 'ki-outline ki-home-2',
                        'route' => 'reports.profiles.owner.show',
                        'route_params' => array_filter(['report' => 'overview', 'work_location_id' => $location?->id]),
                        'active' => ['reports.profiles.owner.show'],
                        'active_report' => 'overview',
                    ],
                    [
                        'label' => 'Laba Rugi & Finansial',
                        'icon' => 'ki-outline ki-chart-line-up',
                        'route' => 'reports.profiles.owner.show',
                        'route_params' => array_filter(['report' => 'income', 'work_location_id' => $location?->id]),
                        'active' => ['reports.profiles.owner.show'],
                        'active_report' => 'income',
                    ],
                    [
                        'label' => 'Rekapitulasi Pajak',
                        'icon' => 'ki-outline ki-shield-tick',
                        'route' => 'reports.profiles.owner.show',
                        'route_params' => array_filter(['report' => 'tax', 'work_location_id' => $location?->id]),
                        'active' => ['reports.profiles.owner.show'],
                        'active_report' => 'tax',
                    ],
                    [
                        'label' => 'Performa Cabang',
                        'icon' => 'ki-outline ki-shop',
                        'route' => 'reports.profiles.owner.show',
                        'route_params' => array_filter(['report' => 'locations', 'work_location_id' => $location?->id]),
                        'active' => ['reports.profiles.owner.show'],
                        'active_report' => 'locations',
                    ],
                    [
                        'label' => 'Piutang & Aging',
                        'icon' => 'ki-outline ki-wallet',
                        'route' => 'reports.profiles.owner.show',
                        'route_params' => array_filter(['report' => 'receivables', 'work_location_id' => $location?->id]),
                        'active' => ['reports.profiles.owner.show'],
                        'active_report' => 'receivables',
                    ],
                ];
            }

            $groups[] = [
                'label' => $label,
                'icon' => $definition['icon'],
                'route' => "reports.profiles.{$profile}.show",
                'route_params' => array_filter(['report' => 'overview', 'work_location_id' => $location?->id]),
                'active' => ["reports.profiles.{$profile}.*"],
                'is_report' => true,
                'profile' => $profile,
                'tone' => $definition['tone'] ?? 'default',
                'children' => $children,
            ];
        }

        if (empty($groups)) {
            if ($user->hasRole('kasir') || $user->hasRole('supervisor_shift')) {
                $groups[] = [
                    'label' => 'Laporan Toko',
                    'icon' => 'ki-outline ki-shop',
                    'route' => 'reports.retail.index',
                    'route_params' => [],
                    'active' => ['reports.retail.*'],
                    'is_report' => true,
                    'profile' => 'retail',
                    'tone' => 'retail',
                    'children' => [],
                ];
            } elseif ($user->hasAnyRole(['staff_gudang', 'picker_packer', 'purchasing'])) {
                $groups[] = [
                    'label' => 'Laporan Gudang',
                    'icon' => 'ki-outline ki-delivery-3',
                    'route' => 'reports.warehouse.index',
                    'route_params' => [],
                    'active' => ['reports.warehouse.*'],
                    'is_report' => true,
                    'profile' => 'warehouse',
                    'tone' => 'warehouse',
                    'children' => [],
                ];
            } elseif ($user->hasRole('sales')) {
                $groups[] = [
                    'label' => 'Laporan Penjualan',
                    'icon' => 'ki-outline ki-chart-line-up',
                    'route' => 'reports.b2b.index',
                    'route_params' => [],
                    'active' => ['reports.b2b.*'],
                    'is_report' => true,
                    'profile' => 'b2b',
                    'tone' => 'default',
                    'children' => [],
                ];
            } elseif ($user->can('reports.view')) {
                $groups[] = [
                    'label' => 'Laporan Harian',
                    'icon' => 'ki-outline ki-chart-line-up',
                    'route' => 'reports.daily.index',
                    'route_params' => [],
                    'active' => ['reports.daily.*'],
                    'is_report' => true,
                    'profile' => 'daily',
                    'tone' => 'default',
                    'children' => [],
                ];
            }
        }

        return $groups;
    }

    public function defaultReportUrlFor(User $user): string
    {
        if ($user->hasAnyRole(['owner', 'owner_viewer', 'owner_approver'])) {
            return route('reports.profiles.owner.show', ['report' => 'overview']);
        }

        if ($user->hasRole('super_admin')) {
            return route('reports.profiles.system.show', ['report' => 'overview']);
        }

        if ($user->hasRole('kepala_toko')) {
            return route('reports.profiles.retail.show', ['report' => 'overview']);
        }

        if ($user->hasRole('kepala_gudang')) {
            return route('reports.profiles.warehouse.show', ['report' => 'overview']);
        }

        if ($user->hasRole('admin_user')) {
            return route('reports.profiles.users.show', ['report' => 'overview']);
        }

        if ($user->hasRole('kasir') || $user->hasRole('supervisor_shift')) {
            return route('reports.retail.index');
        }

        if ($user->hasAnyRole(['staff_gudang', 'picker_packer', 'purchasing'])) {
            return route('reports.warehouse.index');
        }

        if ($user->hasRole('sales')) {
            return route('reports.b2b.index');
        }

        $navigation = $this->navigationFor($user);
        if (! empty($navigation)) {
            $first = $navigation[0];
            $route = $first['route'];
            $params = $first['route_params'] ?? [];

            if (! empty($first['children'])) {
                $firstChild = $first['children'][0];
                $route = $firstChild['route'];
                $params = $firstChild['route_params'] ?? [];
            }

            return route($route, $params);
        }

        if ($user->can('reports.view')) {
            return route('reports.daily.index');
        }

        return route('reports.daily.index');
    }
}
