<div class="report-owner-shell">
    <div class="card border-0 mb-6 report-owner-hero"><div class="card-body p-8"><div class="text-uppercase fs-9 fw-bold report-owner-gold mb-2">EXECUTIVE BUSINESS REPORT</div><div class="d-flex justify-content-between align-items-end flex-wrap gap-4"><div><h1 class="text-white fs-1 mb-2">{{ $currentReport['label'] }}</h1><div class="text-white opacity-75">Pandangan menyeluruh untuk keputusan pemilik usaha.</div></div><div class="text-end"><div class="report-owner-gold fs-8">PERIODE</div><div class="text-white fw-semibold">{{ $filters['start_date'] }} — {{ $filters['end_date'] }}</div></div></div></div></div>
    @unless($reportKey === 'overview') @include('reports.profiles.partials.back-to-overview') @endunless
    <x-metronic.card title="Parameter laporan eksekutif" class="mb-6">@include('reports.profiles.partials.filter')</x-metronic.card>
    @include('reports.profiles.partials.kpis')
    <x-metronic.card :title="$currentReport['label']" subtitle="Ringkasan lintas lokasi dan channel sesuai filter aktif.">@include('reports.profiles.partials.table')</x-metronic.card>
</div>
@push('styles')<style>.report-owner-hero{background:linear-gradient(125deg,#0f172a,#1e3a5f);box-shadow:0 18px 40px rgba(15,23,42,.22)}.report-owner-gold{color:#f4c96b}.report-owner-shell .report-profile-kpi{background:linear-gradient(180deg,#fff,#fffbeb);border-top:3px solid #d4a72c}</style>@endpush
