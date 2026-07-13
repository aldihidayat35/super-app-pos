<div class="d-flex align-items-center ms-3">
    <button type="button" class="btn btn-icon btn-active-light-primary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu pengguna"><span class="symbol symbol-35px"><span class="symbol-label bg-light-primary text-primary fw-bold">{{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}</span></span></button>
    <div class="dropdown-menu dropdown-menu-end menu-sub-dropdown w-275px py-4">
        <div class="px-7 py-3"><div class="fw-bold">{{ auth()->user()?->name }}</div><div class="text-muted fs-7">{{ auth()->user()?->email }}</div><div class="mt-2">@foreach (auth()->user()?->getRoleNames() ?? [] as $role)<span class="badge badge-light-primary me-1">{{ str_replace('_', ' ', $role) }}</span>@endforeach</div></div>
        <div class="separator my-2"></div>
        <div class="px-4 mb-2"><a href="{{ route('profile.edit') }}" class="btn btn-sm btn-light-primary w-100"><i class="ki-outline ki-user fs-4"></i> Profil Saya</a></div>
        <form method="POST" action="{{ route('logout') }}" class="px-4">@csrf<button class="btn btn-sm btn-light-danger w-100" type="submit"><i class="ki-outline ki-exit-right fs-4"></i> Keluar</button></form>
    </div>
</div>
