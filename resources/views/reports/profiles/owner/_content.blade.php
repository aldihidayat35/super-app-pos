@php
    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y')
                 .' – '
                 .\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<div class="owner-report-suite">
    {{-- Hero Header --}}
    <div class="eos-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-4">
            <div>
                <div class="eos-eyebrow">
                    <i class="ki-outline ki-crown fs-7"></i>
                    <span>EXECUTIVE BUSINESS REPORT</span>
                </div>
                <h1 class="eos-title">{{ $currentReport['label'] }}</h1>
                <p class="eos-subtitle">
                    Laporan terperinci untuk analisis keputusan strategis pemilik usaha.
                </p>
            </div>
            <div class="d-flex flex-column align-items-end gap-2">
                <span class="eos-period-badge">
                    <i class="ki-outline ki-calendar fs-6"></i>
                    {{ $periodLabel }}
                </span>
                @if($canExport)
                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>$reportKey]) }}" class="d-flex gap-2">
                        @csrf
                        @foreach(request()->except(['format','_token']) as $n => $v)
                            @if(is_scalar($v))<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endif
                        @endforeach
                        <select name="format" class="form-select form-select-sm w-auto" style="background:#fff">
                            <option value="xlsx">Excel (.xlsx)</option>
                            <option value="pdf">PDF (.pdf)</option>
                            <option value="csv">CSV (.csv)</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="ki-outline ki-exit-down fs-6"></i>Ekspor
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @unless($reportKey === 'overview')
        <div class="mb-5">
            @include('reports.profiles.partials.back-to-overview')
        </div>
    @endunless

    {{-- Filter Card --}}
    <div class="eos-filter-card">
        <div class="text-gray-800 fw-bold fs-6 mb-3">
            <i class="ki-outline ki-filter me-1 text-primary"></i>Parameter Filter
        </div>
        @include('reports.profiles.partials.filter')
    </div>

    {{-- KPI Highlights --}}
    @include('reports.profiles.partials.kpis')

    {{-- Data Table Card --}}
    <div class="eos-card">
        <div class="eos-card-header">
            <h2 class="eos-card-title">
                <i class="ki-outline ki-table text-primary fs-3"></i>
                {{ $currentReport['label'] }}
            </h2>
            <span class="text-muted fs-8">Sesuai filter periode dan lokasi aktif</span>
        </div>
        <div class="eos-card-body p-0">
            @include('reports.profiles.partials.table')
        </div>
    </div>
</div>
