<div class="report-users-shell">
    <div class="card border-0 mb-6 overflow-hidden report-users-hero"><div class="card-body p-7 d-flex justify-content-between align-items-center flex-wrap gap-4">
        <div><span class="badge badge-light mb-3">ADMINISTRASI IDENTITAS</span><h1 class="text-white mb-2">{{ $currentReport['label'] }}</h1><div class="text-white opacity-75">Akun, akses, role, dan penempatan kerja dalam satu ruang kontrol.</div></div>
        <div class="report-hero-icon"><i class="ki-outline ki-profile-user fs-2x text-white"></i></div>
    </div></div>
    @unless($reportKey === 'overview') @include('reports.profiles.partials.back-to-overview') @endunless
    <x-metronic.card title="Filter laporan" class="mb-6">@include('reports.profiles.partials.filter')</x-metronic.card>
    @include('reports.profiles.partials.kpis')
    <div class="row g-5"><div class="col-xl-8"><x-metronic.card title="Daftar Pengguna">@include('reports.profiles.partials.table')</x-metronic.card></div>
        <div class="col-xl-4"><x-metronic.card title="Distribusi Role"><div class="d-flex flex-column gap-3">@forelse($report['charts']['roles'] ?? [] as $role)<div class="d-flex justify-content-between"><span>{{ str($role['label'])->replace('_',' ')->title() }}</span><span class="badge badge-light-primary">{{ $role['value'] }}</span></div>@empty<x-metronic.empty-state title="Belum ada role" description="Distribusi role akan tampil di sini." />@endforelse</div></x-metronic.card></div></div>
</div>
@push('styles')<style>.report-users-hero{background:linear-gradient(125deg,#5b21b6,#8b5cf6)}.report-hero-icon{align-items:center;background:rgba(255,255,255,.14);border-radius:1rem;display:flex;height:72px;justify-content:center;width:72px}.report-profile-kpi{border:1px solid #ede9fe;box-shadow:0 8px 25px rgba(91,33,182,.06)}</style>@endpush
