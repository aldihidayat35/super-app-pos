@php
    $user = auth()->user();
    $menuItems = collect(config('navigation'))->filter(function (array $item) use ($user): bool {
        if (!empty($item['permission']) && !$user?->can($item['permission'])) {
            return false;
        }
        if (empty($item['children'])) {
            return true;
        }
        return collect($item['children'])->contains(
            fn(array $child): bool => empty($child['permission']) || $user?->can($child['permission']),
        );
    });

    // Get work location type label and display info
    $workLocationType = '';
    $displayName = $user->name ?? '';
    $rolesString = '';
    if ($user?->workLocations?->isNotEmpty()) {
        $wl = $user->workLocations->first();
        $type = $wl->type;
        $label = match ($type) {
            'warehouse' => 'Gudang',
            'branch' => 'Cabang/Toko',
            'internal_store' => 'Toko Internal',
            'customer' => 'Pelanggan',
            default => ucfirst($type),
        };
        // Tampilkan nama lokasi kerja, bukan hanya tipenya
        $workLocationType = $wl->name ?? $label;
    }
    if ($user?->roles()->exists()) {
        $roles = $user->roles->pluck('name')->toArray();
        $rolesString = implode(' | ', $roles);
    }
@endphp

<aside id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar"
    aria-label="Navigasi utama">
    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div class="app-sidebar-wrapper hover-scroll-overlay-y my-3 px-3" data-kt-scroll="true"
            data-kt-scroll-height="100%" data-kt-scroll-save-state="true">
            <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold" data-kt-menu="true">
                {{-- User info section - Metronic style --}}
                <div class="menu-item menu-accordion show d-flex flex-column"
                    style="padding: 12px; border-bottom: 2px dashed #e4e6ef; margin-top: 12px; margin-bottom: 12px;">
                    <div class="d-flex align-items-center"
                        style="
            padding: 12px;
            background: linear-gradient(135deg, #ffffff 0%, #f2fbf6 100%);
            border: 1px solid #e8f3ec;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        ">
                        {{-- Avatar --}}
                        <div class="position-relative me-3 flex-shrink-0" style="width: 52px; height: 52px;">
                            <img alt="Avatar {{ $displayName }}"
                                src="{{ $user?->avatar_path ? asset('storage/' . $user->avatar_path) : asset('assets/media/avatars/10.jpg') }}"
                                class="object-fit-cover"
                                style="
                    width: 52px;
                    height: 52px;
                    display: block;
                    object-fit: cover;
                    border-radius: 10px;
                    border: 2px solid #ffffff;
                    box-shadow: 0 0 0 2px #d8f3e3;
                ">

                            {{-- Status online --}}
                            <span class="position-absolute bg-success border border-2 border-white"
                                style="
                    width: 12px;
                    height: 12px;
                    right: -3px;
                    bottom: -3px;
                    border-radius: 4px;
                "
                                title="Online"></span>
                        </div>

                        {{-- Informasi pengguna --}}
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <span class="fw-bold fs-6 text-gray-900 text-truncate" title="{{ $displayName }}">
                                    {{ $displayName }}
                                </span>

                                <span class="d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="
                        width: 24px;
                        height: 24px;
                        background-color: #e8fff3;
                        color: #17c653;
                        border-radius: 7px;
                    "
                                    title="Pengguna aktif">
                                    <i class="ki-outline ki-shield-tick fs-5"></i>
                                </span>
                            </div>

                            {{-- Jabatan/lokasi kerja --}}
                            <div class="d-inline-flex align-items-center mb-2"
                                style="
                    max-width: 100%;
                    padding: 3px 8px;
                    background-color: #e8fff3;
                    border: 1px solid #d5f5e3;
                    border-radius: 6px;
                ">
                                <i class="ki-outline ki-briefcase fs-7 text-success me-1"></i>

                                <span class="fw-semibold text-success text-truncate" style="font-size: 11px;"
                                    title="{{ $workLocationType ?: 'Administrator' }}">
                                    {{ $workLocationType ?: 'Administrator' }}
                                </span>
                            </div>

                            {{-- Role --}}
                            @if ($user && $user->roles()->exists())
                                <div>
                                    <span class="badge fw-semibold text-gray-700 text-truncate"
                                        style="
                            max-width: 100%;
                            padding: 5px 8px;
                            background-color: #f5f6f8;
                            border: 1px solid #e4e6ef;
                            border-radius: 6px;
                            font-size: 11px;
                        "
                                        title="{{ $rolesString }}">
                                        <i class="ki-outline ki-profile-user fs-7 me-1"></i>
                                        {{ $rolesString }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="menu-item pt-4">
                    <div class="menu-content">
                        <span class="menu-heading fw-bold text-uppercase fs-7">Menu Utama</span>
                    </div>
                </div>

                {{-- Align user section with sidebar padding --}}
                <div class="p-3"></div>

                @forelse ($menuItems as $item)
                    @php
                        $children = collect($item['children'] ?? [])->filter(
                            fn(array $child): bool => empty($child['permission']) || $user?->can($child['permission']),
                        );
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
                            <span class="menu-link {{ $isOpen ? 'active' : '' }}">
                                <span class="menu-icon {{ $item['icon'] }}"></span>
                                <span class="menu-title">{{ $item['label'] }}</span>
                                <span class="menu-arrow"></span>
                            </span>
                            <div class="menu-sub menu-sub-accordion {{ $isOpen ? 'show' : '' }}">
                                @foreach ($children as $child)
                                    <div class="menu-item">
                                        <a class="menu-link {{ request()->routeIs(...$child['active']) ? 'active' : '' }}"
                                            href="{{ route($child['route']) }}">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">{{ $child['label'] }}</span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="menu-item">
                            <a class="menu-link {{ $isOpen ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                <span class="menu-icon {{ $item['icon'] }}"></span>
                                <span class="menu-title">{{ $item['label'] }}</span>
                            </a>
                        </div>
                    @endif
                @empty
                    <div class="menu-item">
                        <div class="menu-content text-muted fs-7">Tidak ada menu yang dapat diakses.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</aside>
