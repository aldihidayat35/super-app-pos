@php
    $user = auth()->user();
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

    $avatarUrl = $user?->avatar_url ?? asset('assets/media/avatars/default-avatar.png');
    $workChecklistPending = $user?->can('work_checklists.view_own')
        ? app(\App\Services\WorkChecklist\WorkChecklistService::class)->pendingCount($user)
        : 0;
@endphp

<style>
.user-dropdown {
    position: relative;
}

/* Hilangkan panah default bootstrap */
.user-dropdown-chip::after {
    display: none !important;
}

/* Chip profil tunggal (responsif desktop & mobile): pas dengan header 44px */
.user-dropdown-chip {
    height: 32px !important;
    max-height: 32px !important;
    padding: 2px 8px 2px 3px !important;
    border-radius: 999px !important;
    background-color: var(--bs-body-bg, #ffffff);
    border: 1px solid var(--bs-border-color, #e4e6ef);
    transition: all 0.2s ease;
    cursor: pointer;
    display: inline-flex !important;
    align-items: center !important;
    gap: 7px !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    line-height: 1 !important;
}

.user-dropdown-chip:hover,
.user-dropdown-chip[aria-expanded="true"] {
    background-color: var(--bs-gray-100);
    border-color: var(--bs-primary);
}

[data-bs-theme="dark"] .user-dropdown-chip {
    background-color: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.12);
}

[data-bs-theme="dark"] .user-dropdown-chip:hover,
[data-bs-theme="dark"] .user-dropdown-chip[aria-expanded="true"] {
    background-color: rgba(255, 255, 255, 0.1);
    border-color: var(--bs-primary);
}

@media (max-width: 575.98px) {
    .user-dropdown-chip {
        padding: 3px !important;
        gap: 0 !important;
        width: 32px !important;
        justify-content: center !important;
    }
}

/* Container & Gambar Avatar Ukuran 24px di Navbar */
.user-avatar-container {
    width: 24px !important;
    height: 24px !important;
    min-width: 24px !important;
    min-height: 24px !important;
    max-width: 24px !important;
    max-height: 24px !important;
    border-radius: 50% !important;
    position: relative !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
}

.user-avatar-img {
    width: 24px !important;
    height: 24px !important;
    min-width: 24px !important;
    min-height: 24px !important;
    max-width: 24px !important;
    max-height: 24px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    display: block !important;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

[data-bs-theme="dark"] .user-avatar-img {
    border-color: rgba(255, 255, 255, 0.15);
}

.user-avatar-status {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 6px;
    height: 6px;
    background-color: #50cd89;
    border: 1px solid var(--bs-body-bg, #ffffff);
    border-radius: 50%;
}

.user-chip-info {
    max-width: 120px;
}

/* Dropdown menu container */
.user-dropdown-menu {
    width: 320px;
    max-width: calc(100vw - 20px);
    margin-top: 6px !important;
    border-radius: 14px;
    background-color: var(--bs-body-bg, #ffffff) !important;
    border: 1px solid var(--bs-border-color, #e4e6ef) !important;
    box-shadow:
        0 10px 30px rgba(0, 0, 0, 0.1),
        0 2px 8px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    z-index: 1080;
}

[data-bs-theme="dark"] .user-dropdown-menu {
    background-color: #1e1e2d !important;
    border-color: #2b2b40 !important;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.5) !important;
}

/* Dropdown Header */
.user-dropdown-header {
    padding: 16px;
    background: linear-gradient(
        135deg,
        rgba(var(--bs-primary-rgb), 0.1),
        rgba(var(--bs-primary-rgb), 0.02)
    );
}

[data-bs-theme="dark"] .user-dropdown-header {
    background: linear-gradient(
        135deg,
        rgba(var(--bs-primary-rgb), 0.18),
        rgba(var(--bs-primary-rgb), 0.04)
    );
}

.user-dropdown-info {
    min-width: 0;
    flex: 1;
}

.user-dropdown-actions {
    padding: 8px;
}

.user-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 9px 10px;
    border-radius: 9px;
    color: inherit;
    background-color: transparent;
    text-decoration: none;
    transition:
        background-color 0.18s ease,
        transform 0.18s ease;
}

.user-dropdown-item:hover,
.user-dropdown-item:focus {
    background-color: var(--bs-gray-100);
    color: inherit;
    transform: translateX(2px);
}

[data-bs-theme="dark"] .user-dropdown-item:hover,
[data-bs-theme="dark"] .user-dropdown-item:focus {
    background-color: #2b2b40;
}

.user-dropdown-logout:hover,
.user-dropdown-logout:focus {
    background-color: rgba(var(--bs-danger-rgb), 0.08) !important;
}

.user-dropdown-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    flex-shrink: 0;
}

.fs-8 {
    font-size: 0.75rem;
}

.fs-9 {
    font-size: 0.6875rem;
}

#kt_app_header,
#kt_app_header_container,
.app-header,
.app-header-container,
.app-navbar {
    overflow: visible !important;
}
</style>

<div class="dropdown user-dropdown ms-2 ms-lg-3 d-flex align-items-center">
    {{-- Trigger: Satu chip profil terpadu & responsif (tanpa duplikasi) --}}
    <button
        type="button"
        class="btn btn-custom user-dropdown-chip"
        data-bs-toggle="dropdown"
        data-bs-boundary="viewport"
        data-bs-display="dynamic"
        data-bs-offset="0,6"
        aria-expanded="false"
        aria-label="Buka menu pengguna"
    >
        {{-- Avatar Bulat Proporsional (24px) --}}
        <div class="user-avatar-container">
            <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="user-avatar-img">
            <span class="user-avatar-status"></span>
        </div>

        {{-- Nama & Role (hanya tampil di layar yang cukup lebar) --}}
        <div class="d-none d-sm-flex flex-column text-start lh-1 min-w-0 user-chip-info">
            <span class="fw-bold text-gray-800 fs-7 text-truncate" title="{{ $displayName }}">
                {{ $displayName }}
            </span>
            <span class="text-muted fs-9 text-truncate mt-0.5" title="{{ $rolesString }}">
                {{ $primaryRoleLabel }}
            </span>
        </div>

        <i class="ki-outline ki-down fs-9 text-gray-500 ms-0.5 d-none d-sm-inline-block"></i>
    </button>

    {{-- Dropdown Menu --}}
    <div class="dropdown-menu dropdown-menu-end user-dropdown-menu border-0 p-0 shadow-lg">
        {{-- Header info pengguna --}}
        <div class="user-dropdown-header">
            <div class="d-flex align-items-center gap-3">
                <div class="position-relative flex-shrink-0" style="width: 48px; height: 48px;">
                    <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="w-100 h-100 rounded-circle object-fit-cover border border-2 border-body shadow-sm" style="display: block;">
                    <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-body rounded-circle" style="width: 10px; height: 10px;" title="Online"></span>
                </div>

                <div class="user-dropdown-info">
                    <div class="fw-bolder text-gray-900 fs-6 text-truncate" title="{{ $displayName }}">
                        {{ $displayName }}
                    </div>

                    <div class="text-muted fs-8 text-truncate mt-0.5" title="{{ $user?->email }}">
                        {{ $user?->email ?? '-' }}
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-1 mt-1.5">
                        <span class="badge badge-light-primary fw-semibold fs-9 px-2 py-0.5 text-truncate" style="max-width: 150px;" title="{{ $rolesString }}">
                            {{ $primaryRoleLabel }}
                        </span>
                        @if ($userRoles->count() > 1)
                            <span class="badge badge-light-secondary text-gray-600 fw-bold fs-9 px-1" title="{{ $rolesString }}">
                                +{{ $userRoles->count() - 1 }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Lokasi kerja aktif jika ada --}}
            @if (!empty($workLocationName))
                <div class="d-flex align-items-center gap-2 mt-3 px-2.5 py-1.5 rounded-2 bg-body bg-opacity-70 border border-gray-200 border-opacity-60 text-gray-700 fs-8">
                    <i class="{{ $workLocationIcon }} fs-7 text-primary"></i>
                    <span class="text-truncate flex-grow-1" title="{{ $workLocationName }}">
                        {{ $workLocationName }}
                    </span>
                    <span class="badge badge-light-success fs-9 py-0.5 px-1.5">Aktif</span>
                </div>
            @endif
        </div>

        <div class="separator separator-dashed my-1"></div>

        {{-- Menu tindakan --}}
        <div class="user-dropdown-actions">
            {{-- Profil Saya --}}
            <a href="{{ route('profile.edit') }}" class="user-dropdown-item">
                <span class="user-dropdown-icon bg-light-primary text-primary">
                    <i class="ki-outline ki-user fs-4"></i>
                </span>
                <span class="flex-grow-1">
                    <span class="d-block fw-semibold text-gray-800 fs-7">Profil Saya</span>
                    <span class="d-block text-muted fs-9">Pengaturan akun & sandi</span>
                </span>
                <i class="ki-outline ki-right fs-5 text-gray-400"></i>
            </a>

            {{-- Checklist Harian --}}
            @if (Route::has('work-checklists.index') && ($user?->can('work_checklists.view_own') || $user?->can('work_checklists.view_all')))
                <a href="{{ route('work-checklists.index') }}" class="user-dropdown-item">
                    <span class="user-dropdown-icon bg-light-info text-info">
                        <i class="ki-outline ki-check-square fs-4"></i>
                    </span>
                    <span class="flex-grow-1">
                        <span class="d-block fw-semibold text-gray-800 fs-7 d-flex align-items-center justify-content-between">
                            Checklist Harian
                            @if ($workChecklistPending > 0)
                                <span class="badge badge-warning fs-9 px-1.5 py-0.5">{{ $workChecklistPending }}</span>
                            @endif
                        </span>
                        <span class="d-block text-muted fs-9">Tugas & SOP kerja tim</span>
                    </span>
                    <i class="ki-outline ki-right fs-5 text-gray-400"></i>
                </a>
            @endif

            {{-- Panduan Pengguna --}}
            @if (Route::has('guides.index'))
                <a href="{{ route('guides.index') }}" class="user-dropdown-item">
                    <span class="user-dropdown-icon bg-light-warning text-warning">
                        <i class="ki-outline ki-book-open fs-4"></i>
                    </span>
                    <span class="flex-grow-1">
                        <span class="d-block fw-semibold text-gray-800 fs-7">Panduan Pengguna</span>
                        <span class="d-block text-muted fs-9">Petunjuk alur & modul</span>
                    </span>
                    <i class="ki-outline ki-right fs-5 text-gray-400"></i>
                </a>
            @endif

            <div class="separator separator-dashed my-1"></div>

            {{-- Keluar / Logout --}}
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="user-dropdown-item user-dropdown-logout w-100 border-0">
                    <span class="user-dropdown-icon bg-light-danger text-danger">
                        <i class="ki-outline ki-exit-right fs-4"></i>
                    </span>
                    <span class="flex-grow-1 text-start">
                        <span class="d-block fw-semibold text-danger fs-7">Keluar</span>
                        <span class="d-block text-muted fs-9">Akhiri sesi aplikasi</span>
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>
