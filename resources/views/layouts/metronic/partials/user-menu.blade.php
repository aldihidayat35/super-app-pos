<style>
.user-dropdown {
    position: relative;
}

/* Menghilangkan ikon panah bawaan Bootstrap */
.user-dropdown-toggle::after {
    display: none !important;
}

.user-dropdown-toggle {
    width: 44px;
    height: 44px;
    padding: 0;
    border-radius: 10px;
}

.user-dropdown-menu {
    width: 350px;
    max-width: calc(100vw - 24px);
    margin-top: 8px !important;
    border-radius: 14px;
    background-color: #ffffff;
    box-shadow:
        0 10px 30px rgba(0, 0, 0, 0.12),
        0 2px 8px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    z-index: 1080;
}

.user-dropdown-header {
    padding: 20px;
    background:
        linear-gradient(
            135deg,
            rgba(var(--bs-primary-rgb), 0.08),
            rgba(var(--bs-primary-rgb), 0.02)
        );
}

.user-dropdown-info {
    min-width: 0;
    flex: 1;
}

.user-dropdown-actions {
    padding: 10px;
}

.user-dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 11px 12px;
    border-radius: 10px;
    color: inherit;
    background-color: transparent;
    text-decoration: none;
    transition:
        background-color 0.2s ease,
        transform 0.2s ease;
}

.user-dropdown-item:hover,
.user-dropdown-item:focus {
    background-color: var(--bs-gray-100);
    color: inherit;
    transform: translateX(2px);
}

.user-dropdown-logout:hover,
.user-dropdown-logout:focus {
    background-color: rgba(var(--bs-danger-rgb), 0.08);
}

.user-dropdown-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 9px;
    flex-shrink: 0;
}

.fs-8 {
    font-size: 0.75rem;
}

/*
|--------------------------------------------------------------------------
| Mencegah dropdown terpotong oleh container header Metronic
|--------------------------------------------------------------------------
*/
#kt_app_header,
#kt_app_header_container,
.app-header,
.app-header-container,
.app-navbar {
    overflow: visible !important;
}

/* Penyesuaian layar kecil */
@media (max-width: 576px) {
    .user-dropdown-menu {
        width: calc(100vw - 24px);
        max-width: 350px;
    }

    .user-dropdown-header {
        padding: 16px;
    }

    .user-dropdown-actions {
        padding: 8px;
    }
}
</style>

<div class="dropdown user-dropdown ms-3 ">
    {{-- Trigger --}}
    <button
        type="button"
        class="btn btn-icon btn-active-light-primary user-dropdown-toggle"
        data-bs-toggle="dropdown"
        data-bs-boundary="viewport"
        data-bs-display="dynamic"
        data-bs-offset="0,12"
        aria-expanded="false"
        aria-label="Buka menu pengguna"
    >
        <span class="symbol symbol-40px symbol-circle">
            <span class="symbol-label bg-light-primary text-primary fw-bold fs-5">
                {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
            </span>
        </span>
    </button>

    {{-- Dropdown --}}
    <div class="dropdown-menu dropdown-menu-end user-dropdown-menu border-0 p-0">
        {{-- Informasi pengguna --}}
        <div class="user-dropdown-header">
            <div class="d-flex align-items-center gap-3">
                <span class="symbol symbol-55px symbol-circle flex-shrink-0">
                    <span class="symbol-label bg-primary text-white fw-bold fs-3">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                    </span>
                </span>

                <div class="user-dropdown-info">
                    <div
                        class="fw-bold text-gray-900 fs-5 text-truncate"
                        title="{{ auth()->user()?->name }}"
                    >
                        {{ auth()->user()?->name ?? 'Pengguna' }}
                    </div>

                    <div
                        class="text-muted fs-7 text-truncate mt-1"
                        title="{{ auth()->user()?->email }}"
                    >
                        {{ auth()->user()?->email ?? '-' }}
                    </div>

                    @if ((auth()->user()?->getRoleNames() ?? collect())->isNotEmpty())
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            @foreach (auth()->user()->getRoleNames() as $role)
                                <span class="badge badge-light-primary fw-semibold">
                                    {{ ucwords(str_replace('_', ' ', $role)) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="separator"></div>

        {{-- Menu tindakan --}}
        <div class="user-dropdown-actions">
            <a
                href="{{ route('profile.edit') }}"
                class="user-dropdown-item"
            >
                <span class="user-dropdown-icon bg-light-primary text-primary">
                    <i class="ki-outline ki-user fs-4"></i>
                </span>

                <span class="flex-grow-1">
                    <span class="d-block fw-semibold text-gray-800">
                        Profil Saya
                    </span>
                    <span class="d-block text-muted fs-8">
                        Kelola informasi akun
                    </span>
                </span>

                <i class="ki-outline ki-right fs-4 text-muted"></i>
            </a>

            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf

                <button
                    type="submit"
                    class="user-dropdown-item user-dropdown-logout w-100 border-0"
                >
                    <span class="user-dropdown-icon bg-light-danger text-danger">
                        <i class="ki-outline ki-exit-right fs-4"></i>
                    </span>

                    <span class="flex-grow-1 text-start">
                        <span class="d-block fw-semibold text-danger">
                            Keluar
                        </span>
                        <span class="d-block text-muted fs-8">
                            Akhiri sesi aplikasi
                        </span>
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>
