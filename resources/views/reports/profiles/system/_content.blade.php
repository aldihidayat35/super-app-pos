<div class="report-system-shell">
    <div class="card border-0 mb-6 report-system-hero"><div class="card-body p-7 d-flex justify-content-between align-items-center flex-wrap gap-4"><div><div class="d-flex align-items-center gap-2 mb-2"><span class="report-status-dot"></span><span class="text-uppercase fs-9 fw-bold text-success">SYSTEM CONTROL CENTER</span></div><h1 class="text-white mb-2">{{ $currentReport['label'] }}</h1><div class="text-white opacity-75">Audit, keamanan, integrasi, dan kesehatan data aplikasi.</div></div><i class="ki-outline ki-shield-tick fs-3x text-info"></i></div></div>
    @unless($reportKey === 'overview') @include('reports.profiles.partials.back-to-overview') @endunless
    <x-metronic.card title="Scope pemeriksaan" class="mb-6">@include('reports.profiles.partials.filter')</x-metronic.card>
    @include('reports.profiles.partials.kpis')
    <div class="card report-system-data"><div class="card-header"><h3 class="card-title text-white">{{ $currentReport['label'] }}</h3></div><div class="card-body bg-white rounded-bottom">@include('reports.profiles.partials.table')</div></div>
</div>
@push('styles')<style>.report-system-hero{background:radial-gradient(circle at 85% 20%,#253b80,#111827 55%)}.report-status-dot{background:#22c55e;border-radius:50%;box-shadow:0 0 0 5px rgba(34,197,94,.15);height:9px;width:9px}.report-system-data{background:#111827;border:0}.report-system-shell .report-profile-kpi{border:1px solid #c7d2fe}</style>@endpush
