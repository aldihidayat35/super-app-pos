@php
    $user = auth()->user();
    $menuItems = collect(config('navigation'))->filter(function (array $item) use ($user): bool {
        if (!empty($item['permission']) && !$user?->can($item['permission'])) return false;
        if (empty($item['children'])) return true;
        return collect($item['children'])->contains(fn (array $child): bool => empty($child['permission']) || $user?->can($child['permission']));
    });
@endphp
<aside id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar">
    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-3">
            <img alt="GudangToko" src="{{ asset('assets/media/logos/demo38-small.svg') }}" class="h-30px">
            <span class="text-white fw-bold fs-4">GudangToko</span>
        </a>
    </div>
    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div class="app-sidebar-wrapper hover-scroll-overlay-y my-5 px-3" data-kt-scroll="true">
            <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold" data-kt-menu="true">
                <div class="menu-item pt-5"><div class="menu-content"><span class="menu-heading fw-bold text-uppercase fs-7">Menu Utama</span></div></div>
                @forelse ($menuItems as $item)
                    @php
                        $children = collect($item['children'] ?? [])->filter(fn (array $child): bool => empty($child['permission']) || $user?->can($child['permission']));
                        $isOpen = collect($item['active'] ?? [])->contains(fn (string $pattern): bool => request()->routeIs($pattern))
                            || $children->contains(fn (array $child): bool => collect($child['active'] ?? [])->contains(fn (string $pattern): bool => request()->routeIs($pattern)));
                    @endphp
                    @if ($children->isNotEmpty())
                        <div class="menu-item menu-accordion {{ $isOpen ? 'here show' : '' }}" data-kt-menu-trigger="click">
                            <span class="menu-link"><span class="menu-icon"><i class="{{ $item['icon'] }} fs-2"></i></span><span class="menu-title">{{ $item['label'] }}</span><span class="menu-arrow"></span></span>
                            <div class="menu-sub menu-sub-accordion">
                                @foreach ($children as $child)
                                    <div class="menu-item"><a class="menu-link {{ request()->routeIs(...$child['active']) ? 'active' : '' }}" href="{{ route($child['route']) }}"><span class="menu-bullet"><span class="bullet bullet-dot"></span></span><span class="menu-title">{{ $child['label'] }}</span></a></div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="menu-item"><a class="menu-link {{ $isOpen ? 'active' : '' }}" href="{{ route($item['route']) }}"><span class="menu-icon"><i class="{{ $item['icon'] }} fs-2"></i></span><span class="menu-title">{{ $item['label'] }}</span></a></div>
                    @endif
                @empty
                    <div class="menu-item"><div class="menu-content text-muted fs-7">Tidak ada menu yang dapat diakses.</div></div>
                @endforelse
            </div>
        </div>
    </div>
</aside>
