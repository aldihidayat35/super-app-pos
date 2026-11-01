<div class="report-warehouse-shell">
    <div class="card border-0 mb-6 report-warehouse-hero"><div class="card-body p-7"><div class="d-flex align-items-center gap-4"><span class="report-warehouse-icon"><i class="ki-outline ki-delivery-3 fs-2x"></i></span><div><div class="text-uppercase fs-9 fw-bold text-warning mb-1">PUSAT KENDALI GUDANG</div><h1 class="text-white mb-1">{{ $currentReport['label'] }}</h1><div class="text-white opacity-75">{{ $selectedLocation?->name ?? 'Lokasi gudang yang diizinkan' }} · diperbarui {{ now('Asia/Jakarta')->format('H:i') }} WIB</div></div></div></div></div>
    @unless($reportKey === 'overview') @include('reports.profiles.partials.back-to-overview') @endunless
    <x-metronic.card title="Periode dan lokasi gudang" class="mb-6">@include('reports.profiles.partials.filter')</x-metronic.card>
    @include('reports.profiles.partials.kpis')
    <x-metronic.card :title="$currentReport['label']" subtitle="Data hanya berasal dari lokasi gudang yang diizinkan.">@include('reports.profiles.partials.table')</x-metronic.card>
</div>
@push('styles')<style>.report-warehouse-hero{background:linear-gradient(120deg,#102a43,#1f5f8b);box-shadow:0 14px 35px rgba(16,42,67,.18)}.report-warehouse-icon{align-items:center;background:#f59e0b;border-radius:1rem;color:#102a43;display:flex;height:68px;justify-content:center;width:68px}.report-warehouse-shell .report-profile-kpi{border-left:4px solid #f59e0b}</style>@endpush
