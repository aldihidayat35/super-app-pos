<div class="report-retail-shell">
    <div class="card border-0 mb-6 report-retail-hero"><div class="card-body p-7"><div class="d-flex justify-content-between align-items-center flex-wrap gap-4"><div><div class="text-uppercase fs-9 fw-bold text-success mb-2">PERFORMA TOKO</div><h1 class="text-gray-900 mb-2">{{ $currentReport['label'] }}</h1><div class="text-muted">{{ $selectedLocation?->name ?? 'Toko yang diizinkan' }} · penjualan, kas, dan kesiapan stok.</div></div><span class="report-retail-icon"><i class="ki-outline ki-shop fs-2x text-success"></i></span></div></div></div>
    @unless($reportKey === 'overview') @include('reports.profiles.partials.back-to-overview') @endunless
    <x-metronic.card title="Filter operasional toko" class="mb-6">@include('reports.profiles.partials.filter')</x-metronic.card>
    @include('reports.profiles.partials.kpis')
    <div class="card border-0 shadow-sm"><div class="card-header border-0"><h3 class="card-title fw-bold">{{ $currentReport['label'] }}</h3></div><div class="card-body pt-0">@include('reports.profiles.partials.table')</div></div>
</div>
@push('styles')<style>.report-retail-hero{background:linear-gradient(120deg,#ecfdf3,#fff);border:1px solid #bbf7d0!important}.report-retail-icon{align-items:center;background:#dcfce7;border-radius:50%;display:flex;height:76px;justify-content:center;width:76px}.report-retail-shell .report-profile-kpi{border-bottom:3px solid #22c55e}</style>@endpush
