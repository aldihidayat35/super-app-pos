@php
    use App\Support\CurrencyFormatter;
    use Illuminate\Support\Carbon;

    $money = fn (mixed $value): string => CurrencyFormatter::rupiah((string) ($value ?? 0));
    $number = fn (mixed $value): string => qty((string) ($value ?? 0));
    $start = Carbon::parse($filters['start_date']);
    $end = Carbon::parse($filters['end_date']);
    $periodLabel = $start->isSameDay($end) ? $start->translatedFormat('d M Y') : $start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y');
    $locationLabel = $selectedLocation?->name ?? 'Toko yang diizinkan';
    $detailParams = fn (string $detail): array => array_filter(['report' => $detail, 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id, 'range' => $filters['range'] ?? 'monthly']);
    $detailUrl = fn (string $detail): string => route('reports.profiles.retail.show', $detailParams($detail));
    $change = function (?array $item): string {
        if (($item['value'] ?? null) === null) return 'Belum ada pembanding';
        $arrow = ($item['direction'] ?? 'up') === 'down' ? '▼' : '▲';
        return $arrow.' '.(((float) $item['value'] >= 0) ? '+' : '').str_replace('.', ',', (string) $item['value']).'%';
    };
    $chartRows = collect($report['sales']['chart'] ?? [])->values();
    $chartMax = max(1.0, (float) $chartRows->max(fn (array $row): float => (float) ($row['revenue'] ?? 0)));
    $chartXs = [74, 133, 192, 251, 310, 369, 428];
    $chartPoints = $chartRows->map(function (array $row, int $index) use ($chartMax, $chartXs): array {
        $x = $chartXs[$index] ?? 428;

        return [
            'x' => $x,
            'revenue_y' => round(132 - (102 * ((float) ($row['revenue'] ?? 0) / $chartMax)), 1),
            'margin_y' => round(132 - (102 * ((float) ($row['margin'] ?? 0) / $chartMax)), 1),
            'date' => Carbon::parse($row['date'])->translatedFormat('d M'),
            'revenue' => (string) ($row['revenue'] ?? '0'),
            'margin' => (string) ($row['margin'] ?? '0'),
        ];
    });
    $marginVisible = (bool) data_get($report, 'profitability.sensitive', false);
    $teamTotal = max(1, (int) data_get($report, 'team.total', 0));
    $productTotal = max(1, (int) data_get($report, 'inventory.total_products', 0));
    $criticalPercent = min(100, (int) round(((int) data_get($report, 'inventory.critical_count', 0) / $productTotal) * 100));
    $restockPercent = min(100, (int) round(((int) data_get($report, 'inventory.restock_count', 0) / $productTotal) * 100));
@endphp

<div class="retail-operational-report">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon-sprite" aria-hidden="true">
        <symbol id="ror-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></symbol><symbol id="ror-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol><symbol id="ror-download" viewBox="0 0 24 24"><path d="M12 3v13m-5-5 5 5 5-5M4 17v4h16v-4"/></symbol><symbol id="ror-refresh" viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14.8-4M4 4v5h5M4 13a8 8 0 0 0 14.8 4M20 20v-5h-5"/></symbol><symbol id="ror-filter" viewBox="0 0 24 24"><path d="M4 5h16l-6.5 7.5V19l-3 1.5v-8z"/></symbol><symbol id="ror-coins" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/></symbol><symbol id="ror-chart" viewBox="0 0 24 24"><path d="M5 20v-6M11.5 20V9M18 20V4"/></symbol><symbol id="ror-percent" viewBox="0 0 24 24"><circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M19 5 5 19"/></symbol><symbol id="ror-bag" viewBox="0 0 24 24"><rect x="4" y="7" width="16" height="14" rx="1.5"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/></symbol><symbol id="ror-users" viewBox="0 0 24 24"><circle cx="9" cy="7" r="3.5"/><path d="M2 20v-2a7 7 0 0 1 14 0v2M17 4a4 4 0 0 1 0 7.5M18 13c3.3.6 4.5 3.2 4.5 6.8"/></symbol><symbol id="ror-check" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m7 12 3.2 3.2L17 8"/></symbol><symbol id="ror-close" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></symbol><symbol id="ror-bed" viewBox="0 0 24 24"><path d="M3 20V4M21 20v-8H3v8M7 12V8h6a3 3 0 0 1 3 3v1M3 18h18"/></symbol>
    </svg>

    <header class="topbar"><div class="heading"><span class="brand-icon"><svg class="icon"><use href="#ror-chart"/></svg></span><div><h1>Laporan Operasional Toko</h1><div class="subtitle">Ringkasan lengkap kinerja operasional, penjualan, profitabilitas, kas, stok, retur, piutang, dan tim toko.</div></div></div><div class="top-actions"><button class="period-button" id="reportPeriodShortcut" type="button"><svg class="icon"><use href="#ror-calendar"/></svg><span>Periode: {{ $periodLabel }}</span><svg class="icon"><use href="#ror-down"/></svg></button>@if($canExport)<button class="icon-button" type="submit" form="retailReportExport" title="Ekspor CSV"><svg class="icon"><use href="#ror-download"/></svg></button>@endif<a class="icon-button" href="{{ route('reports.profiles.retail.show', ['report' => 'overview']) }}" title="Atur ulang filter"><svg class="icon"><use href="#ror-refresh"/></svg></a></div></header>
    @if($canExport)<form id="retailReportExport" method="POST" action="{{ route('reports.profiles.exports.store', ['profile' => 'retail', 'report' => 'overview']) }}" hidden>@csrf<input type="hidden" name="format" value="csv"><input type="hidden" name="start_date" value="{{ $filters['start_date'] }}"><input type="hidden" name="end_date" value="{{ $filters['end_date'] }}"><input type="hidden" name="work_location_id" value="{{ $selectedLocation?->id }}"><input type="hidden" name="range" value="{{ $filters['range'] ?? 'monthly' }}"></form>@endif

    <form method="GET" id="retailReportFilters" class="filter-panel"><input type="hidden" name="report" value="overview"><label class="filter-field"><span class="field-label">Tanggal mulai</span><span class="control-wrap"><input type="date" name="start_date" id="reportStartDate" value="{{ $filters['start_date'] }}"></span></label><label class="filter-field"><span class="field-label">Tanggal akhir</span><span class="control-wrap"><input type="date" name="end_date" value="{{ $filters['end_date'] }}"></span></label><label class="filter-field"><span class="field-label">Lokasi kerja</span><span class="control-wrap"><select name="work_location_id" data-control="native">@foreach($workLocations as $location)<option value="{{ $location->id }}" @selected($selectedLocation?->id === $location->id)>{{ $location->name }}</option>@endforeach</select><svg class="icon control-icon"><use href="#ror-down"/></svg></span></label><label class="filter-field"><span class="field-label">Rentang grafik</span><span class="control-wrap"><select name="range" data-control="native">@foreach(['monthly' => 'Bulanan', 'daily' => 'Harian', 'yearly' => 'Tahunan'] as $value => $label)<option value="{{ $value }}" @selected(($filters['range'] ?? 'monthly') === $value)>{{ $label }}</option>@endforeach</select><svg class="icon control-icon"><use href="#ror-down"/></svg></span></label><button class="filter-button" type="submit"><svg class="icon"><use href="#ror-filter"/></svg>Terapkan</button><a class="icon-button filter-reset" href="{{ route('reports.profiles.retail.show', ['report' => 'overview']) }}" title="Reset filter"><svg class="icon"><use href="#ror-refresh"/></svg></a></form>

    <section class="summary-banner"><div class="summary-label"><h2>Ringkasan Kinerja Toko</h2><p>Periode {{ $periodLabel }} · {{ $locationLabel }}</p></div><div class="summary-kpis"><div class="summary-metric"><div class="metric-caption"><svg class="icon"><use href="#ror-coins"/></svg>Penjualan</div><strong>{{ $money(data_get($report, 'summary.revenue')) }}</strong><div class="summary-change"><span class="delta">{{ $change(data_get($report, 'summary.revenue_change')) }}</span><span class="summary-change-label">vs. periode sebelumnya</span></div></div><div class="summary-metric"><div class="metric-caption"><svg class="icon"><use href="#ror-chart"/></svg>Penghasilan (Margin)</div><strong>{{ $marginVisible ? $money(data_get($report, 'summary.margin')) : 'Akses terbatas' }}</strong>@if($marginVisible)<div class="summary-change"><span class="delta">{{ $change(data_get($report, 'summary.margin_change')) }}</span><span class="summary-change-label">vs. periode sebelumnya</span></div>@endif</div><div class="summary-metric"><div class="metric-caption"><svg class="icon"><use href="#ror-percent"/></svg>Margin</div><strong>{{ $marginVisible ? str_replace('.', ',', data_get($report, 'summary.margin_percent', '0.0')).'%' : 'Akses terbatas' }}</strong></div><div class="summary-metric"><div class="metric-caption"><svg class="icon"><use href="#ror-bag"/></svg>Transaksi</div><strong>{{ data_get($report, 'summary.transaction_count', 0) }}</strong><div class="summary-change"><span class="delta">{{ $change(data_get($report, 'summary.transaction_change')) }}</span><span class="summary-change-label">vs. periode sebelumnya</span></div></div></div><div class="store-art-slot"></div></section>

    <main class="dashboard-grid"><div class="report-row top-row">
        <section class="panel top-panel sales-panel"><div class="panel-heading"><h2>Penjualan &amp; Penghasilan</h2><div class="panel-heading-actions"><a class="detail-link" href="{{ $detailUrl('sales') }}">Lihat Detail</a></div></div><div class="stat-row three"><div class="stat"><div class="stat-label">Penjualan</div><div class="stat-value">{{ $money(data_get($report, 'sales.revenue')) }}</div><span class="delta">{{ $change(data_get($report, 'sales.revenue_change')) }}</span></div><div class="stat"><div class="stat-label">Penghasilan (Margin)</div><div class="stat-value">{{ $marginVisible ? $money(data_get($report, 'sales.margin')) : 'Akses terbatas' }}</div>@if($marginVisible)<span class="delta">{{ $change(data_get($report, 'sales.margin_change')) }}</span>@endif</div><div class="stat"><div class="stat-label">Rata-rata Tiket</div><div class="stat-value">{{ $money(data_get($report, 'sales.average_ticket')) }}</div></div></div>
            <div class="chart-stage" data-sales-chart><svg class="sales-chart" viewBox="0 0 462 155" role="img" aria-label="Grafik penjualan dan penghasilan"><g class="chart-gridline">@foreach([12,36,60,84,108,132] as $y)<line x1="37" y1="{{ $y }}" x2="459" y2="{{ $y }}"/>@endforeach</g>@forelse($chartPoints as $point)<g class="chart-column" tabindex="0" role="button" data-chart-column data-x="{{ $point['x'] }}" data-y="{{ $point['revenue_y'] }}" data-date="{{ $point['date'] }}" data-revenue="{{ $money($point['revenue']) }}" data-margin="{{ $marginVisible ? $money($point['margin']) : '' }}" aria-label="{{ $point['date'] }}, penjualan {{ $money($point['revenue']) }}{{ $marginVisible ? ', penghasilan '.$money($point['margin']) : '' }}"><rect class="chart-bar" x="{{ $point['x'] - 16 }}" y="{{ $point['revenue_y'] }}" width="32" height="{{ 132 - $point['revenue_y'] }}" rx="2"/><rect class="chart-hit-area" x="{{ $point['x'] - 25 }}" y="8" width="50" height="128"/></g>@empty<text x="231" y="75" text-anchor="middle" class="chart-axis">Belum ada transaksi pada periode ini</text>@endforelse @if($marginVisible && $chartPoints->isNotEmpty())<polyline class="chart-margin-line" points="{{ $chartPoints->map(fn ($p) => $p['x'].','.$p['margin_y'])->implode(' ') }}"/>@foreach($chartPoints as $point)<circle class="chart-margin-dot" cx="{{ $point['x'] }}" cy="{{ $point['margin_y'] }}" r="3.4"/>@endforeach @endif<g class="chart-axis" text-anchor="middle">@foreach($chartPoints as $point)<text x="{{ $point['x'] }}" y="149">{{ $point['date'] }}</text>@endforeach</g></svg><div class="chart-tooltip" data-chart-tooltip role="status" aria-live="polite" hidden><strong data-tooltip-date></strong><span><i class="tooltip-swatch sales"></i>Penjualan <b data-tooltip-revenue></b></span><span data-tooltip-margin-row><i class="tooltip-swatch margin"></i>Penghasilan <b data-tooltip-margin></b></span></div></div><div class="chart-legend"><span><i class="legend-bar"></i>Penjualan</span>@if($marginVisible)<span><i class="legend-line"></i>Penghasilan</span>@endif</div></section>
        <section class="panel top-panel profit-panel" data-profit-card><div class="panel-heading"><h2>Profitabilitas &amp; Margin</h2><a class="detail-link" href="{{ $detailUrl('profitability') }}">Lihat Detail</a></div>@if($marginVisible)@php
            $mPct = max(0, min(100, (float) data_get($report, 'profitability.margin_percent', 0)));
            $hPct = max(0, min(100, (float) data_get($report, 'profitability.hpp_percent', 0)));
            if ($hPct <= 0 && $mPct > 0 && $mPct <= 100) {
                $hPct = round(100 - $mPct, 1);
            }
            $sumMh = $mPct + $hPct;
            $mRatio = $sumMh > 0 ? ($mPct / $sumMh) : 0;
            $hRatio = $sumMh > 0 ? ($hPct / $sumMh) : 0;
            $circ = 251.33;
            $mStroke = round($mRatio * $circ, 2);
            $hStroke = round($hRatio * $circ, 2);
            $hOffset = -round($mStroke, 2);
        @endphp<div class="stat-row two"><div class="stat"><div class="stat-label">Margin</div><div class="stat-value">{{ $money(data_get($report, 'profitability.margin')) }}</div></div><div class="stat"><div class="stat-label">Margin (%)</div><div class="stat-value">{{ str_replace('.', ',', data_get($report, 'profitability.margin_percent', '0.0')) }}%</div></div></div><div class="profit-body"><div class="profit-ring-stage" data-profit-stage><svg class="profit-donut" viewBox="0 0 110 110" role="img" aria-label="Grafik perbandingan HPP dan Margin"><circle class="donut-bg" cx="55" cy="55" r="40" /><circle class="donut-segment segment-margin" cx="55" cy="55" r="40" stroke-dasharray="{{ $mStroke }} {{ round($circ - $mStroke, 2) }}" stroke-dashoffset="0" data-segment="margin" data-label="Margin" data-value="{{ $money(data_get($report, 'profitability.margin')) }}" data-pct="{{ str_replace('.', ',', data_get($report, 'profitability.margin_percent', '0.0')) }}%" data-color="#10b981" tabindex="0" role="button" aria-label="Margin: {{ $money(data_get($report, 'profitability.margin')) }}" /><circle class="donut-segment segment-hpp" cx="55" cy="55" r="40" stroke-dasharray="{{ $hStroke }} {{ round($circ - $hStroke, 2) }}" stroke-dashoffset="{{ $hOffset }}" data-segment="hpp" data-label="HPP" data-value="{{ $money(data_get($report, 'profitability.hpp')) }}" data-pct="{{ str_replace('.', ',', data_get($report, 'profitability.hpp_percent', '0.0')) }}%" data-color="#2563eb" tabindex="0" role="button" aria-label="HPP: {{ $money(data_get($report, 'profitability.hpp')) }}" /></svg><div class="ring-hole" data-profit-hole><strong data-center-value>{{ str_replace('.', ',', data_get($report, 'profitability.margin_percent', '0.0')) }}%</strong><small data-center-label>Margin</small></div><div class="chart-tooltip profit-tooltip" data-profit-tooltip role="status" aria-live="polite" hidden><strong data-pt-title>Margin</strong><span><i class="tooltip-swatch" data-pt-swatch></i><span data-pt-name>Margin:</span> <b data-pt-val></b></span><span class="tooltip-pct" data-pt-pct></span></div></div><div class="profit-legend" data-profit-legend><div class="legend-row" data-legend="hpp" tabindex="0" role="button" data-name="HPP" data-amount="{{ $money(data_get($report, 'profitability.hpp')) }}" data-percent="{{ str_replace('.', ',', data_get($report, 'profitability.hpp_percent', '0.0')) }}%" data-color="#2563eb"><i class="legend-dot hpp"></i><span>HPP</span><b>{{ $money(data_get($report, 'profitability.hpp')) }}</b><span>{{ str_replace('.', ',', data_get($report, 'profitability.hpp_percent', '0.0')) }}%</span></div><div class="legend-row is-active" data-legend="margin" tabindex="0" role="button" data-name="Margin" data-amount="{{ $money(data_get($report, 'profitability.margin')) }}" data-percent="{{ str_replace('.', ',', data_get($report, 'profitability.margin_percent', '0.0')) }}%" data-color="#10b981"><i class="legend-dot margin"></i><span>Margin</span><b>{{ $money(data_get($report, 'profitability.margin')) }}</b><span>{{ str_replace('.', ',', data_get($report, 'profitability.margin_percent', '0.0')) }}%</span></div><div class="legend-row" data-legend="expenses" tabindex="0" role="button" data-name="Biaya Lain" data-amount="{{ $money(data_get($report, 'profitability.expenses')) }}" data-percent="{{ str_replace('.', ',', data_get($report, 'profitability.expense_percent', '0.0')) }}%" data-color="#f59e0b"><i class="legend-dot expenses"></i><span>Biaya Lain</span><b>{{ $money(data_get($report, 'profitability.expenses')) }}</b><span>{{ str_replace('.', ',', data_get($report, 'profitability.expense_percent', '0.0')) }}%</span></div></div></div>@else<div class="restricted-state">Akses terbatas<small>HPP dan margin memerlukan izin margins.view_sensitive.</small></div>@endif</section>
        <section class="panel top-panel cash-panel"><div class="panel-heading"><h2>Shift &amp; Selisih Kas</h2><a class="detail-link" href="{{ $detailUrl('shifts') }}">Lihat Detail</a></div><div class="stat-row three"><div class="stat"><div class="stat-label">Transaksi</div><div class="stat-value">{{ data_get($report, 'shifts.transaction_count', 0) }}</div></div><div class="stat"><div class="stat-label">Selisih Kas</div><div class="stat-value">{{ $money(data_get($report, 'shifts.cash_difference')) }}</div></div><div class="stat"><div class="stat-label">Shift</div><div class="stat-value">{{ data_get($report, 'shifts.shift_count', 0) }}</div></div></div><table class="report-data-table shift-table"><colgroup><col class="date-column"><col class="cashier-column"><col class="transaction-column"><col class="amount-column"></colgroup><thead><tr><th>Tanggal</th><th>Kasir</th><th class="number">Transaksi</th><th class="number">Pendapatan</th></tr></thead><tbody>@forelse(data_get($report, 'shifts.rows', []) as $row)<tr><td>{{ Carbon::parse($row['date'])->translatedFormat('d M Y') }}</td><td>{{ $row['cashier'] ?? '-' }}</td><td class="number">{{ $row['transaction_count'] ?? 0 }}</td><td class="number">{{ $money($row['revenue'] ?? 0) }}</td></tr>@empty<tr><td colspan="4" class="empty-cell">Belum ada transaksi shift.</td></tr>@endforelse</tbody></table></section>
    </div><div class="report-row second-row">
        <section class="panel second-panel stock-panel"><div class="panel-heading"><h2>Stok &amp; Restock</h2><a class="detail-link" href="{{ $detailUrl('inventory') }}">Lihat Detail</a></div><div class="stat-row three"><div class="stat"><div class="stat-label">Total Produk</div><div class="stat-value">{{ data_get($report, 'inventory.total_products', 0) }}</div></div><div class="stat"><div class="stat-label">Stok Tersedia</div><div class="stat-value">{{ $number(data_get($report, 'inventory.available_stock')) }}</div></div><div class="stat"><div class="stat-label">Nilai Stok</div><div class="stat-value stock-value" title="{{ $marginVisible ? $money(data_get($report, 'inventory.stock_value')) : 'Terbatas' }}">{{ $marginVisible ? $money(data_get($report, 'inventory.stock_value')) : 'Terbatas' }}</div></div></div><div class="stock-progress-wrap"><div class="stock-progress"><div class="progress-label">Stok Kritis<strong>{{ data_get($report, 'inventory.critical_count', 0) }}</strong></div><div class="progress-track"><div class="progress-fill" style="width:{{ $criticalPercent }}%"></div></div><div class="progress-meta"><strong>{{ $criticalPercent }}%</strong>dari total produk</div></div><div class="stock-progress"><div class="progress-label">Perlu Restock<strong>{{ data_get($report, 'inventory.restock_count', 0) }}</strong></div><div class="progress-track"><div class="progress-fill restocks" style="width:{{ $restockPercent }}%"></div></div><div class="progress-meta"><strong>{{ $restockPercent }}%</strong>dari total produk</div></div></div><table class="report-data-table stock-table"><colgroup><col class="sku-column"><col class="product-column"><col class="stock-column"><col class="status-column"></colgroup><thead><tr><th>SKU</th><th>Produk</th><th>Stok</th><th>Status</th></tr></thead><tbody>@forelse(array_slice(data_get($report, 'inventory.alerts', []), 0, 2) as $row)<tr><td>{{ $row['sku'] }}</td><td class="name-col">{{ $row['product'] }}</td><td>{{ $number($row['available']) }}</td><td><span class="status-pill">Stok Kritis</span></td></tr>@empty<tr><td colspan="4" class="empty-cell">Tidak ada stok kritis.</td></tr>@endforelse</tbody></table></section>
        <section class="panel second-panel returns-panel"><div class="panel-heading"><h2>Retur, Darurat &amp; Loss</h2><a class="detail-link" href="{{ $detailUrl('exceptions') }}">Lihat Detail</a></div><div class="stat-row three"><div class="stat"><div class="stat-label">Total Retur</div><div class="stat-value">{{ data_get($report, 'exceptions.return_count', 0) }}</div></div><div class="stat"><div class="stat-label">Nilai Retur</div><div class="stat-value">{{ $money(data_get($report, 'exceptions.return_value')) }}</div></div><div class="stat"><div class="stat-label">Loss / Darurat</div><div class="stat-value">{{ $money(App\Support\Decimal::add(data_get($report, 'exceptions.loss_value', '0'), data_get($report, 'exceptions.emergency_value', '0'), 2)) }}</div></div></div><table><thead><tr><th>Jenis</th><th class="number">Transaksi</th><th class="number">Nilai</th></tr></thead><tbody>@foreach(data_get($report, 'exceptions.rows', []) as $row)<tr><td>{{ $row['label'] }}</td><td class="number">{{ $row['transaction_count'] }}</td><td class="number">{{ $money($row['value']) }}</td></tr>@endforeach</tbody></table></section>
        <section class="panel second-panel receivables-panel"><div class="panel-heading"><h2>Piutang Retail</h2><a class="detail-link" href="{{ $detailUrl('receivables') }}">Lihat Detail</a></div><div class="stat-row three"><div class="stat"><div class="stat-label">Outstanding</div><div class="stat-value">{{ $money(data_get($report, 'receivables.outstanding')) }}</div></div><div class="stat"><div class="stat-label">Overdue</div><div class="stat-value">{{ $money(data_get($report, 'receivables.overdue')) }}</div></div><div class="stat"><div class="stat-label">Akun Piutang</div><div class="stat-value">{{ data_get($report, 'receivables.account_count', 0) }}</div></div></div><h3>Piutang per Customer</h3><table class="report-data-table receivables-table"><colgroup><col class="number-column"><col class="customer-column"><col class="due-column"><col class="remaining-column"></colgroup><thead><tr><th>No. Piutang</th><th>Customer</th><th>Jatuh Tempo</th><th class="number">Sisa</th></tr></thead><tbody>@forelse(data_get($report, 'receivables.rows', []) as $row)<tr><td>{{ $row['number'] }}</td><td>{{ $row['customer'] ?? '-' }}</td><td>{{ Carbon::parse($row['due_date'])->translatedFormat('d M Y') }}</td><td class="number">{{ $money($row['outstanding_amount']) }}</td></tr>@empty<tr><td colspan="4" class="empty-cell">Tidak ada piutang aktif.</td></tr>@endforelse</tbody></table></section>
    </div>
        <section class="panel team-panel"><div class="panel-heading"><h2>Tim Toko</h2><a class="detail-link" href="{{ $detailUrl('team') }}">Lihat Detail</a></div><div class="team-body"><div class="team-stats"><div class="team-stat"><div class="team-stat-label"><svg class="icon"><use href="#ror-users"/></svg>Total Tim</div><div class="team-stat-value">{{ data_get($report, 'team.total', 0) }}</div></div><div class="team-stat"><div class="team-stat-label"><svg class="icon"><use href="#ror-check"/></svg>Hadir</div><div class="team-stat-value">{{ data_get($report, 'team.present', 0) }} <small>({{ round((data_get($report, 'team.present', 0) / $teamTotal) * 100) }}%)</small></div></div><div class="team-stat"><div class="team-stat-label"><svg class="icon"><use href="#ror-calendar"/></svg>Terlambat</div><div class="team-stat-value">{{ data_get($report, 'team.late', 0) }}</div></div><div class="team-stat"><div class="team-stat-label"><svg class="icon"><use href="#ror-close"/></svg>Izin</div><div class="team-stat-value">{{ data_get($report, 'team.permission', 0) }}</div></div><div class="team-stat"><div class="team-stat-label"><svg class="icon"><use href="#ror-bed"/></svg>Sakit</div><div class="team-stat-value">{{ data_get($report, 'team.sick', 0) }}</div></div></div><div class="team-table-wrap"><table class="team-table"><thead><tr><th>Nama Tim</th><th>Peran</th><th>Shift</th><th>Status</th><th class="number">Transaksi</th></tr></thead><tbody>@forelse(data_get($report, 'team.rows', []) as $row)<tr><td><span class="avatar">{{ str($row['name'])->explode(' ')->take(2)->map(fn ($part) => str($part)->substr(0, 1))->implode('') }}</span><strong>{{ $row['name'] }}</strong></td><td>{{ $row['position'] }}</td><td>{{ $row['shift'] }}</td><td><span class="presence">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</span></td><td class="number">{{ $row['transactions'] ?: '–' }}</td></tr>@empty<tr><td colspan="5" class="empty-cell">Belum ada tim aktif.</td></tr>@endforelse</tbody></table></div></div></section>
    </main>
</div>

@include('reports.profiles.retail._operational-overview-styles')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const button = document.getElementById('reportPeriodShortcut');
    const start = document.getElementById('reportStartDate');

    if (button && start) {
        button.addEventListener('click', () => start.showPicker ? start.showPicker() : start.focus());
    }

    document.querySelectorAll('[data-sales-chart]').forEach((stage) => {
        const chart = stage.querySelector('.sales-chart');
        const tooltip = stage.querySelector('[data-chart-tooltip]');
        const columns = Array.from(stage.querySelectorAll('[data-chart-column]'));

        if (!chart || !tooltip || columns.length === 0) return;

        const hideTooltip = () => {
            tooltip.hidden = true;
            columns.forEach((column) => column.classList.remove('is-active'));
        };

        const showTooltip = (column) => {
            columns.forEach((item) => item.classList.toggle('is-active', item === column));
            tooltip.querySelector('[data-tooltip-date]').textContent = column.dataset.date || '';
            tooltip.querySelector('[data-tooltip-revenue]').textContent = column.dataset.revenue || 'Rp0';

            const marginRow = tooltip.querySelector('[data-tooltip-margin-row]');
            const marginValue = column.dataset.margin || '';
            marginRow.hidden = marginValue === '';
            tooltip.querySelector('[data-tooltip-margin]').textContent = marginValue;

            const x = (Number(column.dataset.x || 0) / 462) * chart.clientWidth;
            const y = (Number(column.dataset.y || 0) / 155) * chart.clientHeight;
            tooltip.style.left = `${Math.max(76, Math.min(stage.clientWidth - 76, x))}px`;
            tooltip.style.top = `${Math.max(52, y)}px`;
            tooltip.hidden = false;
        };

        columns.forEach((column) => {
            column.addEventListener('mouseenter', () => showTooltip(column));
            column.addEventListener('focus', () => showTooltip(column));
            column.addEventListener('click', (event) => {
                event.stopPropagation();
                showTooltip(column);
            });
            column.addEventListener('mouseleave', hideTooltip);
            column.addEventListener('blur', hideTooltip);
        });

        document.addEventListener('click', hideTooltip);
    });

    document.querySelectorAll('[data-profit-card]').forEach((card) => {
        const stage = card.querySelector('[data-profit-stage]');
        const tooltip = card.querySelector('[data-profit-tooltip]');
        const segments = Array.from(card.querySelectorAll('[data-segment]'));
        const legendRows = Array.from(card.querySelectorAll('[data-legend]'));
        const centerVal = card.querySelector('[data-center-value]');
        const centerLabel = card.querySelector('[data-center-label]');

        if (!stage || !centerVal || !centerLabel) return;

        const defaultVal = centerVal.textContent;
        const defaultLabel = centerLabel.textContent;

        const resetProfitView = () => {
            if (tooltip) tooltip.hidden = true;
            segments.forEach((seg) => {
                seg.classList.remove('is-hovered', 'is-dimmed');
            });
            legendRows.forEach((row) => {
                row.classList.remove('is-hovered');
            });
            centerVal.textContent = defaultVal;
            centerLabel.textContent = defaultLabel;
            centerLabel.style.color = '';
        };

        const highlightItem = (key, name, value, pct, color) => {
            segments.forEach((seg) => {
                const isMatch = seg.dataset.segment === key;
                seg.classList.toggle('is-hovered', isMatch);
                seg.classList.toggle('is-dimmed', !isMatch && (key === 'margin' || key === 'hpp'));
            });

            legendRows.forEach((row) => {
                row.classList.toggle('is-hovered', row.dataset.legend === key);
            });

            centerVal.textContent = pct;
            centerLabel.textContent = name;
            if (color) {
                centerLabel.style.color = color;
            }

            if (tooltip) {
                const titleEl = tooltip.querySelector('[data-pt-title]');
                const nameEl = tooltip.querySelector('[data-pt-name]');
                const valEl = tooltip.querySelector('[data-pt-val]');
                const pctEl = tooltip.querySelector('[data-pt-pct]');
                const swatchEl = tooltip.querySelector('[data-pt-swatch]');

                if (titleEl) titleEl.textContent = name;
                if (nameEl) nameEl.textContent = name + ':';
                if (valEl) valEl.textContent = value;
                if (pctEl) pctEl.textContent = pct + ' dari Penjualan';
                if (swatchEl) swatchEl.style.backgroundColor = color || '#10b981';

                tooltip.hidden = false;
            }
        };

        segments.forEach((seg) => {
            const key = seg.dataset.segment;
            const name = seg.dataset.label;
            const val = seg.dataset.value;
            const pct = seg.dataset.pct;
            const color = seg.dataset.color;

            seg.addEventListener('mouseenter', () => highlightItem(key, name, val, pct, color));
            seg.addEventListener('focus', () => highlightItem(key, name, val, pct, color));
            seg.addEventListener('click', (event) => {
                event.stopPropagation();
                highlightItem(key, name, val, pct, color);
            });
            seg.addEventListener('mouseleave', resetProfitView);
            seg.addEventListener('blur', resetProfitView);
        });

        legendRows.forEach((row) => {
            const key = row.dataset.legend;
            const name = row.dataset.name;
            const val = row.dataset.amount;
            const pct = row.dataset.percent;
            const color = row.dataset.color;

            row.addEventListener('mouseenter', () => highlightItem(key, name, val, pct, color));
            row.addEventListener('focus', () => highlightItem(key, name, val, pct, color));
            row.addEventListener('click', (event) => {
                event.stopPropagation();
                highlightItem(key, name, val, pct, color);
            });
            row.addEventListener('mouseleave', resetProfitView);
            row.addEventListener('blur', resetProfitView);
        });

        document.addEventListener('click', (e) => {
            if (!card.contains(e.target)) {
                resetProfitView();
            }
        });
    });
});
</script>
@endpush
