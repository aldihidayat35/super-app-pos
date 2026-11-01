@php
    $dashboardParams = fn (string $report): array => array_filter([
        'report' => $report,
        'start_date' => $filters['start_date'] ?? null,
        'end_date' => $filters['end_date'] ?? null,
        'work_location_id' => $selectedLocation?->id,
        'range' => $filters['range'] ?? 'monthly',
    ]);
    $dashboardUrl = fn (string $report): string => route("reports.profiles.{$profileKey}.show", $dashboardParams($report));
@endphp

<div class="role-report-dashboard" data-report-theme="{{ $dashboard['theme'] }}">
    <header class="role-report-header">
        <div class="role-report-heading">
            <span class="role-report-icon"><i class="{{ $dashboard['icon'] }}"></i></span>
            <div><div class="role-report-eyebrow">{{ $dashboard['eyebrow'] }}</div><h1>{{ $dashboard['title'] }}</h1><p>{{ $dashboard['description'] }}</p></div>
        </div>
        <div class="role-report-actions">
            <span class="role-report-period"><i class="ki-outline ki-calendar"></i>{{ $filters['start_date'] }} — {{ $filters['end_date'] }}</span>
            @if($canExport)<button type="submit" form="profileOverviewExport" class="role-report-action" title="Ekspor CSV"><i class="ki-outline ki-file-down"></i></button>@endif
            <a href="{{ $dashboardUrl('overview') }}" class="role-report-action" title="Reset laporan"><i class="ki-outline ki-arrows-circle"></i></a>
        </div>
    </header>

    @if($canExport)
        <form id="profileOverviewExport" method="POST" action="{{ route('reports.profiles.exports.store', ['profile' => $profileKey, 'report' => 'overview']) }}" hidden>
            @csrf
            <input type="hidden" name="format" value="csv"><input type="hidden" name="start_date" value="{{ $filters['start_date'] }}"><input type="hidden" name="end_date" value="{{ $filters['end_date'] }}"><input type="hidden" name="work_location_id" value="{{ $selectedLocation?->id }}"><input type="hidden" name="range" value="{{ $filters['range'] ?? 'monthly' }}">
        </form>
    @endif

    <section class="role-report-filter">@include('reports.profiles.partials.filter')</section>

    <section class="role-report-summary">
        <div class="role-report-summary-copy"><h2>{{ $dashboard['summary_title'] }}</h2><p>{{ $dashboard['summary_subtitle'] }}</p></div>
        <div class="role-report-kpis">
            @foreach($dashboard['kpis'] as $kpi)
                <article class="role-report-kpi"><span>{{ $kpi['label'] }}</span><strong>{{ $kpi['value'] }}</strong><small>{{ $kpi['note'] ?? 'Periode aktif' }}</small></article>
            @endforeach
        </div>
    </section>

    <main class="role-report-grid">
        @foreach($dashboard['panels'] as $panel)
            <section class="role-report-panel">
                <div class="role-report-panel-heading"><div><h2>{{ $panel['title'] }}</h2>@isset($panel['subtitle'])<p>{{ $panel['subtitle'] }}</p>@endisset</div>@isset($panel['detail'])<a href="{{ $dashboardUrl($panel['detail']) }}">Lihat Detail</a>@endisset</div>

                @if(!empty($panel['metrics']))
                    <div class="role-report-metrics">@foreach($panel['metrics'] as $metric)<div><span>{{ $metric['label'] }}</span><strong>{{ $metric['value'] }}</strong></div>@endforeach</div>
                @endif

                @if(!empty($panel['bars']))
                    @php $barMax = max(1.0, (float) collect($panel['bars'])->max('numeric')); @endphp
                    <div class="role-report-bars">@foreach($panel['bars'] as $bar)<div class="role-bar-row" data-bar-value="{{ $bar['value'] }}"><div><span>{{ $bar['label'] }}</span><strong>{{ $bar['value'] }}</strong></div><div class="role-bar-track"><i style="width:{{ min(100, ((float) $bar['numeric'] / $barMax) * 100) }}%"></i></div></div>@endforeach</div>
                @endif

                @if(!empty($panel['rows']))
                    <div class="role-report-table-wrap"><table><thead><tr>@foreach($panel['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>@foreach(array_slice($panel['rows'], 0, 6) as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table></div>
                @elseif(empty($panel['metrics']) && empty($panel['bars']))
                    <div class="role-report-empty">Belum ada data pada periode ini.</div>
                @endif
            </section>
        @endforeach
    </main>

    <nav class="role-report-links" aria-label="Detail laporan {{ $dashboard['title'] }}">@foreach($dashboard['links'] as $key => $label)<a href="{{ $dashboardUrl($key) }}">{{ $label }}<i class="ki-outline ki-arrow-right"></i></a>@endforeach</nav>
</div>

@include('reports.profiles.partials.profile-overview-dashboard-styles')
