@php
    $user = auth()->user();
    $hiddenRoutes = config('ui_visibility.hidden_navigation_routes', []);
    $canOpen = fn (?string $permission): bool => empty($permission)
        || collect(explode('|', $permission))->contains(fn (string $name): bool => $user?->can($name) === true);
    $accessCache = [];
    $canAccess = function (?string $access) use ($user, &$accessCache): bool {
        if ($access === null || $access === '') {
            return true;
        }

        return $accessCache[$access] ??= match ($access) {
            'b2b.customer' => $user !== null && app(\App\Services\B2B\B2bPortalService::class)->canAccessPortal($user),
            'retail.location' => $user?->hasPermittedWorkLocationType('branch') === true,
            'warehouse.location' => $user?->hasPermittedWorkLocationType('warehouse') === true,
            default => false,
        };
    };
    $isVisible = fn (array $item): bool => !in_array($item['route'] ?? null, $hiddenRoutes, true)
        && $canOpen($item['permission'] ?? null)
        && $canAccess($item['access'] ?? null);

    $regularMenuItems = collect(config('navigation'))->filter(function (array $item) use ($isVisible): bool {
        if (!$isVisible($item)) {
            return false;
        }
        if (empty($item['children'])) {
            return true;
        }
        return collect($item['children'])->contains($isVisible);
    });

    $reportMenuItems = collect(app(\App\Services\Reports\ReportProfileService::class)->navigationFor($user))
        ->filter($isVisible);

    // Get work location and role info
    $displayName = $user?->name ?? 'Pengguna';
    $roleDefinitions = config('rbac.roles', []);
    $userRoles = $user?->roles ?? collect();
    $primaryRole = $userRoles->first()?->name;
    $primaryRoleLabel = $roleDefinitions[$primaryRole]['label'] ?? ucwords(str_replace('_', ' ', (string) ($primaryRole ?? 'Pengguna')));
    $rolesString = $userRoles->map(fn($r) => $roleDefinitions[$r->name]['label'] ?? ucwords(str_replace('_', ' ', $r->name)))->implode(' • ');

    $workLocationName = '';
    $workLocationIcon = 'ki-outline ki-geolocation';
    if ($user?->workLocations?->isNotEmpty()) {
        $wl = $user->workLocations->first();
        $workLocationName = $wl->name;
        $workLocationIcon = match ($wl->type) {
            'warehouse' => 'ki-outline ki-delivery-3',
            'branch', 'internal_store' => 'ki-outline ki-shop',
            'customer' => 'ki-outline ki-profile-user',
            default => 'ki-outline ki-geolocation',
        };
    } elseif ($user?->hasUnrestrictedLocationScope()) {
        $workLocationName = 'Semua Lokasi';
        $workLocationIcon = 'ki-outline ki-global';
    }

    $initial = strtoupper(substr($displayName, 0, 1));
    $avatarUrl = $user?->avatar_url ?? asset('assets/media/avatars/default-avatar.png');

@endphp

<aside id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar"
    data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true"
    data-kt-drawer-width="260px" data-kt-drawer-direction="start"
    data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle" aria-label="Navigasi utama">

    {{-- Header Sidebar: Profil Pengguna (Fixed / Tidak ikut scroll) --}}
    <div class="app-sidebar-header d-flex flex-column flex-shrink-0 px-2 pt-3 pb-1" id="kt_app_sidebar_header">
        <div class="sidebar-user-card mx-1 my-1 p-3 rounded-3 bg-light-primary bg-opacity-20 border border-primary border-opacity-10 shadow-xs">
            <div class="d-flex align-items-center gap-3 sidebar-user-row">
                {{-- Avatar --}}
                <a href="{{ route('profile.edit') }}" class="sidebar-user-avatar position-relative flex-shrink-0 text-decoration-none" style="width: 40px; height: 40px;" title="Lihat Profil">
                    <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="w-100 h-100 rounded-circle object-fit-cover border border-2 border-body shadow-xs" style="display: block;">
                    <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-body rounded-circle" style="width: 10px; height: 10px;" title="Online"></span>
                </a>

                {{-- User detail --}}
                <div class="sidebar-user-details flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <a href="{{ route('profile.edit') }}" class="fw-bold text-gray-900 text-hover-primary fs-6 text-truncate pe-1 text-decoration-none" title="{{ $displayName }}">
                            {{ $displayName }}
                        </a>
                        <a href="{{ route('profile.edit') }}" class="btn btn-icon btn-custom btn-active-color-primary w-20px h-20px text-gray-400 text-hover-primary" title="Pengaturan Akun" aria-label="Pengaturan Akun">
                            <i class="ki-outline ki-setting-3 fs-6"></i>
                        </a>
                    </div>

                    <div class="d-flex align-items-center gap-1 mt-1">
                        <span class="badge badge-light-primary fw-semibold fs-9 px-2 py-0.5 text-truncate" style="max-width: 130px;" title="{{ $rolesString }}">
                            {{ $primaryRoleLabel }}
                        </span>
                        @if ($userRoles->count() > 1)
                            <span class="badge badge-light-secondary text-gray-600 fw-bold fs-9 px-1" title="{{ $rolesString }}">
                                +{{ $userRoles->count() - 1 }}
                            </span>
                        @endif
                    </div>

                    <div class="d-flex align-items-center gap-1 text-gray-600 fs-9 mt-1 text-truncate" title="{{ $workLocationName ?: 'Semua Lokasi' }}">
                        <i class="{{ $workLocationIcon }} fs-8 text-primary"></i>
                        <span class="text-truncate">{{ $workLocationName ?: 'Semua Lokasi' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="separator separator-dashed my-2 mx-1 opacity-75 sidebar-menu-separator"></div>
    </div>

    {{-- Body Sidebar: Menu Navigasi (Scrollable) --}}
    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div class="app-sidebar-wrapper hover-scroll-overlay-y my-2 px-2" data-kt-scroll="true"
            data-kt-scroll-height="100%" data-kt-scroll-save-state="true">
            <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold" data-kt-menu="true" data-kt-menu-expand="false">
                <div class="menu-item pt-1 pb-1 sidebar-menu-header">
                    <div class="menu-content">
                        <span class="menu-heading fw-bold text-uppercase fs-7 text-gray-500">Menu Utama</span>
                    </div>
                </div>

                @forelse ($regularMenuItems as $item)
                    @php
                        $children = collect($item['children'] ?? [])->filter($isVisible);
                        $isOpen =
                            collect($item['active'] ?? [])->contains(
                                fn(string $pattern): bool => request()->routeIs($pattern),
                            ) ||
                            $children->contains(
                                fn(array $child): bool => collect($child['active'] ?? [])->contains(
                                    fn(string $pattern): bool => request()->routeIs($pattern),
                                ),
                            );
                    @endphp

                    @if ($children->isNotEmpty())
                        <div class="menu-item menu-accordion {{ $isOpen ? 'here show' : '' }}"
                            data-kt-menu-trigger="click">
                            <span class="menu-link {{ $isOpen ? 'active' : '' }}" title="{{ $item['label'] }}">
                                <span class="menu-icon">
                                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                                </span>
                                <span class="menu-title">{{ $item['label'] }}</span>
                                <span class="menu-arrow"></span>
                            </span>
                            <div class="menu-sub menu-sub-accordion {{ $isOpen ? 'show' : '' }}">
                                <div class="sidebar-submenu-grid">
                                    @foreach ($children as $child)
                                        @php
                                            $childActive = request()->routeIs(...$child['active'])
                                                && (!isset($child['active_report']) || request()->route('report') === $child['active_report']);
                                            $isLastOdd = $loop->last && ($loop->count % 2 !== 0);
                                        @endphp
                                        <div class="menu-item {{ $isLastOdd ? 'is-full-width' : '' }}">
                                            <a class="menu-link sidebar-submenu-card {{ $childActive ? 'active' : '' }} {{ $isLastOdd ? 'is-full-width' : '' }}"
                                                href="{{ route($child['route'], $child['route_params'] ?? []) }}" title="{{ $child['label'] }}"
                                                {{ $child['route'] === 'retail.pos.index' ? 'data-no-spa' : '' }}>
                                                <i class="submenu-card-icon {{ $child['icon'] ?? 'ki-outline ki-package' }}"></i>
                                                <span class="menu-title submenu-card-title">{{ $child['label'] }}</span>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="menu-item">
                            <a class="menu-link {{ $isOpen ? 'active' : '' }}" href="{{ route($item['route'], $item['route_params'] ?? []) }}" title="{{ $item['label'] }}"
                                {{ ($item['route'] ?? null) === 'retail.pos.index' ? 'data-no-spa' : '' }}>
                                <span class="menu-icon">
                                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                                </span>
                                <span class="menu-title">{{ $item['label'] }}</span>
                            </a>
                        </div>
                    @endif
                @empty
                    @if ($reportMenuItems->isEmpty())
                        <div class="menu-item">
                            <div class="menu-content text-muted fs-7">Tidak ada menu yang dapat diakses.</div>
                        </div>
                    @endif
                @endforelse

                {{-- Bagian Paling Bawah: Pusat Laporan Berbasis Peran dengan Warna Kontras --}}
                @if ($reportMenuItems->isNotEmpty())
                    <div class="sidebar-report-section mt-4 pt-1">
                        <div class="separator separator-dashed mb-2 opacity-75 sidebar-report-separator"></div>
                        <div class="menu-item pt-1 pb-1 sidebar-menu-header sidebar-report-header">
                            <div class="menu-content px-2">
                                <span class="menu-heading fw-bold text-uppercase fs-8 text-primary">Pusat Laporan</span>
                            </div>
                        </div>

                        @foreach ($reportMenuItems as $item)
                            @php
                                $children = collect($item['children'] ?? [])->filter($isVisible);
                                $isOpen = collect($item['active'] ?? [])->contains(
                                    fn(string $pattern): bool => request()->routeIs($pattern),
                                );
                                $tone = $item['tone'] ?? 'default';
                            @endphp

                            @if ($children->isNotEmpty())
                                <div class="menu-item menu-accordion {{ $isOpen ? 'here show' : '' }} sidebar-report-item sidebar-report-tone-{{ $tone }} mb-1"
                                    data-kt-menu-trigger="click">
                                    <span class="menu-link sidebar-report-link {{ $isOpen ? 'active' : '' }}" title="{{ $item['label'] }}">
                                        <span class="menu-icon sidebar-report-icon">
                                            <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="menu-title sidebar-report-title">{{ $item['label'] }}</span>
                                        <span class="menu-arrow"></span>
                                    </span>
                                    <div class="menu-sub menu-sub-accordion {{ $isOpen ? 'show' : '' }}">
                                        <div class="sidebar-submenu-grid">
                                            @foreach ($children as $child)
                                                @php
                                                    $childActive = request()->routeIs(...$child['active'])
                                                        && (!isset($child['active_report']) || request()->route('report') === $child['active_report']);
                                                    $isLastOdd = $loop->last && ($loop->count % 2 !== 0);
                                                @endphp
                                                <div class="menu-item {{ $isLastOdd ? 'is-full-width' : '' }}">
                                                    <a class="menu-link sidebar-submenu-card {{ $childActive ? 'active' : '' }} {{ $isLastOdd ? 'is-full-width' : '' }}"
                                                        href="{{ route($child['route'], $child['route_params'] ?? []) }}" title="{{ $child['label'] }}">
                                                        <i class="submenu-card-icon {{ $child['icon'] ?? 'ki-outline ki-package' }}"></i>
                                                        <span class="menu-title submenu-card-title">{{ $child['label'] }}</span>
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="menu-item sidebar-report-item sidebar-report-tone-{{ $tone }} mb-1">
                                    <a class="menu-link sidebar-report-link {{ $isOpen ? 'active' : '' }}"
                                        href="{{ route($item['route'], $item['route_params'] ?? []) }}"
                                        title="{{ $item['label'] }}">
                                        <span class="menu-icon sidebar-report-icon">
                                            <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="menu-title sidebar-report-title">{{ $item['label'] }}</span>
                                        <span class="sidebar-report-pill">Laporan</span>
                                    </a>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Footer Sidebar: Logout + Pin/Unpin --}}
    <div class="app-sidebar-footer d-flex flex-column flex-shrink-0 px-2 py-3" id="kt_app_sidebar_footer">
        <div class="separator separator-dashed mb-3 opacity-75 sidebar-footer-separator"></div>
        <div class="app-sidebar-footer-actions d-flex align-items-center gap-2">
            {{-- Tombol Logout --}}
            <form method="POST" action="{{ route('logout') }}" class="m-0 flex-grow-1 sidebar-logout-form">
                @csrf
                <button type="submit" class="sidebar-action-btn sidebar-logout-btn" title="Keluar dari akun">
                    <span class="sidebar-action-icon sidebar-logout-icon">
                        <i class="ki-outline ki-exit-right"></i>
                    </span>
                    <span class="sidebar-action-label ms-2 fw-semibold">Keluar</span>
                </button>
            </form>

            {{-- Tombol Pin / Unpin --}}
            <button type="button" id="kt_app_sidebar_pin_btn" class="sidebar-action-btn sidebar-pin-btn" title="Pin / Unpin Sidebar" aria-label="Pin atau Unpin Sidebar">
                <span class="sidebar-action-icon sidebar-pin-icon-wrap">
                    <i class="ki-outline ki-pin sidebar-pin-icon"></i>
                </span>
                <span class="sidebar-action-label sidebar-pin-label ms-2 fw-semibold">Pin</span>
            </button>
        </div>
    </div>
</aside>
