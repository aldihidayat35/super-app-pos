@php
    use App\Support\CurrencyFormatter;
    $summary = $report['summary'] ?? [];
    $health = $report['health'] ?? [];
    $inventory = $report['inventory'] ?? [];
    $revenueChart = collect(data_get($report, 'charts.revenue', []))->values();
    $channelMix = collect(data_get($report, 'charts.channel_mix', []))->values();
    $aging = data_get($report, 'charts.aging', []);
    $revenueMax = max(1, (float) $revenueChart->max(fn ($row) => (float) data_get($row, 'value', data_get($row, 'revenue', data_get($row, 'total', 0)))));
    $channelTotal = max(1, (float) $channelMix->sum(fn ($row) => (float) data_get($row, 'value', 0)));
    $agingTotal = max(1, (float) array_sum(array_map('floatval', is_array($aging) ? $aging : [])));
    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y').' – '.\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');
    $params = fn (string $key): array => array_filter(['report' => $key, 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id, 'range' => $filters['range'] ?? 'monthly']);
    $detail = fn (string $key): string => route('reports.profiles.owner.show', $params($key));
    $money = fn ($value): string => $value === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string) $value);
    $marginLabel = $summary['margin_percent'] === null ? 'Akses terbatas' : str_replace('.', ',', (string) $summary['margin_percent']).'%';
    $onHand = (float) data_get($inventory, 'available', 0);
    $reserved = (float) data_get($inventory, 'reserved', 0);
    $stockTotal = max(1, $onHand + $reserved);
    $scoreLabel = ((int) data_get($health, 'overall', 0)) >= 80 ? 'Sehat' : (((int) data_get($health, 'overall', 0)) >= 60 ? 'Perlu perhatian' : 'Berisiko');
    $kpis = [
        ['label' => 'Revenue', 'value' => $money($summary['revenue'] ?? 0), 'icon' => 'or-chart', 'tone' => 'green', 'note' => 'Pendapatan periode aktif', 'series' => data_get($report, 'sparklines.revenue', []), 'money' => true],
        ['label' => 'Gross Margin', 'value' => $money($summary['gross_margin'] ?? null), 'icon' => 'or-layers', 'tone' => 'green', 'note' => $marginLabel, 'series' => data_get($report, 'sparklines.gross_margin', []), 'money' => true],
        ['label' => 'Stock Value', 'value' => $money($summary['stock_value'] ?? null), 'icon' => 'or-box', 'tone' => 'green', 'note' => 'HPP lapisan aktif', 'series' => data_get($report, 'sparklines.stock_value', []), 'money' => true],
        ['label' => 'Receivable Outstanding', 'value' => $money($summary['receivable_outstanding'] ?? 0), 'icon' => 'or-coins', 'tone' => 'green', 'note' => 'Piutang per lokasi', 'series' => data_get($report, 'sparklines.receivable', []), 'money' => true],
        ['label' => 'Critical Stock', 'value' => $summary['critical_stock_count'] ?? 0, 'icon' => 'or-alert', 'tone' => 'red', 'note' => 'Perlu perhatian segera', 'series' => data_get($report, 'sparklines.critical_stock', []), 'money' => false],
        ['label' => 'Open Accounts', 'value' => $summary['open_accounts'] ?? 0, 'icon' => 'or-users', 'tone' => 'green', 'note' => 'Akun terbuka per lokasi', 'series' => data_get($report, 'sparklines.open_accounts', []), 'money' => false],
    ];
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<div class="owner-report">
    <svg class="or-symbols" aria-hidden="true"><symbol id="or-chart" viewBox="0 0 24 24"><path d="M4 20v-7m6 7V6m6 14V10m6 10V3M2 21h21"/></symbol><symbol id="or-layers" viewBox="0 0 24 24"><path d="m12 2 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 17l9 5 9-5"/></symbol><symbol id="or-box" viewBox="0 0 24 24"><path d="m12 2 9 5-9 5-9-5 9-5ZM3 7v10l9 5 9-5V7M12 12v10"/></symbol><symbol id="or-coins" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.7 4 3 9 3s9-1.3 9-3V5m-9 6v6c0 1.7 4 3 9 3s9-1.3 9-3v-6"/></symbol><symbol id="or-alert" viewBox="0 0 24 24"><path d="m12 2 10 19H2L12 2Zm0 7v5m0 4h.01"/></symbol><symbol id="or-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.9"/></symbol><symbol id="or-target" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><path d="m12 12 9-9"/></symbol><symbol id="or-gavel" viewBox="0 0 24 24"><path d="m2 21 9-9M6 5l5-5 8 8-5 5-8-8Zm4 4 5-5m-1 10 3-3 4 4-3 3"/></symbol><symbol id="or-cart" viewBox="0 0 24 24"><path d="M2 3h2l3 13h13l3-10H5M9 21h.01M19 21h.01"/></symbol><symbol id="or-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 3"/></symbol></svg>

    <header class="or-top-shell">
        <div class="or-title-bar"><div class="or-brand"><span class="or-brand-icon"><svg><use href="#or-chart"/></svg></span><div><div class="or-eyebrow">EXECUTIVE BUSINESS REPORT</div><h1>Laporan Owner</h1><p>Pandangan menyeluruh lintas toko, gudang, channel, piutang, dan risiko usaha.</p></div></div><div class="or-actions"><span class="or-period"><i class="ki-outline ki-calendar"></i>{{ $periodLabel }}</span>@if($canExport)<button type="submit" form="ownerExport"><i class="ki-outline ki-exit-down"></i>Ekspor</button>@endif<a href="{{ route('reports.profiles.owner.show', ['report' => 'overview']) }}"><i class="ki-outline ki-arrows-circle"></i>Perbarui</a></div></div>
        <section class="or-filter">@include('reports.profiles.partials.filter')</section>
    </header>
    @if($canExport)<form id="ownerExport" method="POST" action="{{ route('reports.profiles.exports.store', ['profile' => 'owner', 'report' => 'overview']) }}" hidden>@csrf<input type="hidden" name="format" value="csv"><input type="hidden" name="start_date" value="{{ $filters['start_date'] }}"><input type="hidden" name="end_date" value="{{ $filters['end_date'] }}"><input type="hidden" name="work_location_id" value="{{ $selectedLocation?->id }}"><input type="hidden" name="range" value="{{ $filters['range'] ?? 'monthly' }}"></form>@endif

    <section class="or-kpis">
        @foreach($kpis as $index => $kpi)
            @php
                $series = is_array($kpi['series']) ? array_values($kpi['series']) : [];
                if ($series === []) {
                    $series = ['0', '0'];
                } elseif (count($series) === 1) {
                    $series = [$series[0], $series[0]];
                }
                $seriesNumbers = array_map('floatval', $series);
                $seriesMin = min($seriesNumbers);
                $seriesMax = max($seriesNumbers);
                $seriesRange = max(1, $seriesMax - $seriesMin);
                $seriesCount = max(1, count($seriesNumbers) - 1);
                $sparkPoints = collect($seriesNumbers)->map(fn (float $point, int $pointIndex): string => (8 + (($pointIndex / $seriesCount) * 104)).','.(38 - ((($point - $seriesMin) / $seriesRange) * 26)))->all();
                $sparkArea = 'M '.implode(' L ', $sparkPoints).' L 112 44 L 8 44 Z';
            @endphp
            <article class="or-kpi" data-owner-kpi-card>
                <div class="or-kpi-label"><svg><use href="#{{ $kpi['icon'] }}"/></svg><span>{{ $kpi['label'] }}</span></div>
                <strong title="{{ $kpi['value'] }}">{{ $kpi['value'] }}</strong>
                <small>{{ $kpi['note'] }}</small>
                <svg class="or-kpi-chart {{ $kpi['tone'] }}" viewBox="0 0 120 48" role="img" aria-label="Grafik {{ $kpi['label'] }}" data-owner-kpi-chart>
                    <defs><linearGradient id="orSpark{{ $index }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".30"/><stop offset="1" stop-color="currentColor" stop-opacity=".02"/></linearGradient></defs>
                    <path class="area" d="{{ $sparkArea }}" fill="url(#orSpark{{ $index }})"/>
                    <polyline points="{{ implode(' ', $sparkPoints) }}"/>
                    @foreach($sparkPoints as $pointIndex => $point)
                        @php [$pointX, $pointY] = explode(',', $point); @endphp
                        <circle cx="{{ $pointX }}" cy="{{ $pointY }}" r="2.8"><title>{{ $kpi['label'] }} · {{ $kpi['money'] ? $money($series[$pointIndex]) : qty((string) $series[$pointIndex]) }}</title></circle>
                    @endforeach
                </svg>
            </article>
        @endforeach
    </section>

    <section class="or-hero-row">
        <article class="or-executive"><div class="or-growth-art"><i></i><i></i><i></i><i></i><span>↗</span></div><div><h2>Ringkasan Eksekutif</h2><p>Pendapatan periode aktif mencapai <b>{{ $money($summary['revenue'] ?? 0) }}</b>@if($canViewSensitive) dengan margin <b>{{ $marginLabel }}</b> dan nilai HPP persediaan <b>{{ $money($summary['stock_value'] ?? null) }}</b>@endif. Piutang outstanding saat ini <b>{{ $money($summary['receivable_outstanding'] ?? 0) }}</b>. Fokus keputusan: {{ ($summary['critical_stock_count'] ?? 0) > 0 ? 'kendalikan stok kritis' : 'pertahankan kesehatan stok' }}, percepat penagihan, dan tindak lanjuti anomali terbuka.</p></div></article>
        <article class="or-health"><h2>Skor Kesehatan Bisnis <i class="ki-outline ki-information-5"></i></h2><div class="or-health-body"><div class="or-gauge" style="--score:{{ data_get($health,'overall',0) * 1.8 }}deg"><b>{{ data_get($health,'overall',0) }}<small>/100</small></b><span>{{ $scoreLabel }}</span></div><div class="or-score-grid">@foreach(['profitability'=>'Profitabilitas','liquidity'=>'Likuiditas','inventory'=>'Kesehatan Persediaan','collection'=>'Kesehatan Penagihan','operations'=>'Stabilitas Operasional'] as $key=>$label)<div><span>{{ $label }}</span><b>{{ data_get($health,$key) === null ? '–' : data_get($health,$key) }}<small>/100</small></b><i><em style="width:{{ data_get($health,$key,0) ?? 0 }}%"></em></i><small>{{ $key === 'inventory' ? ($summary['critical_stock_count'] ?? 0).' stok kritis' : 'Berdasarkan periode aktif' }}</small></div>@endforeach</div></div></article>
    </section>

    <section class="or-panels">
        <article class="or-panel"><x-owner-panel-head title="Pendapatan & Channel Mix" :url="$detail('income')"/><p>Komposisi pendapatan dan tren periode aktif.</p><div class="or-two-metrics"><div><span>Retur</span><b>{{ $money($summary['returns_value'] ?? 0) }}</b></div><div><span>Selisih Kas</span><b>{{ $money($summary['cash_difference'] ?? 0) }}</b></div></div><h4>Komposisi Channel</h4><div class="or-channel-bars">@foreach($channelMix as $channel)<div><span>{{ data_get($channel,'label','-') }}</span><b>{{ $money(data_get($channel,'value',0)) }}</b><i><em style="width:{{ ((float)data_get($channel,'value',0)/$channelTotal)*100 }}%"></em></i></div>@endforeach</div><h4>Tren Pendapatan</h4>@if($revenueChart->isEmpty())<div class="or-empty-chart">Belum ada transaksi pada periode ini.</div>@else<svg class="or-revenue-chart" viewBox="0 0 230 100">@php $rc=max(1,$revenueChart->count()-1);$points=[];foreach($revenueChart as $i=>$row){$x=10+($i/$rc)*210;$v=(float)data_get($row,'value',data_get($row,'revenue',data_get($row,'total',0)));$y=86-(($v/$revenueMax)*68);$points[]="$x,$y";} @endphp<polyline points="{{ implode(' ',$points) }}"/>@foreach($revenueChart as $i=>$row)@php $x=10+($i/$rc)*210;$v=(float)data_get($row,'value',data_get($row,'revenue',data_get($row,'total',0)));$y=86-(($v/$revenueMax)*68); @endphp<circle cx="{{ $x }}" cy="{{ $y }}" r="4"><title>{{ data_get($row,'date',data_get($row,'label','-')) }} · {{ $money($v) }}</title></circle>@endforeach</svg>@endif</article>

        <article class="or-panel"><x-owner-panel-head title="Perbandingan Lokasi" :url="$detail('locations')"/><p>Kinerja pendapatan, stok, dan risiko per lokasi.</p><div class="or-location-bars">@foreach(array_slice($report['locations'] ?? [],0,4) as $location)<div title="{{ $location['name'] }}"><span>{{ $location['name'] }}</span><i><em style="width:{{ min(100,((float)$location['revenue']/$revenueMax)*100) }}%"></em></i></div>@endforeach</div><div class="or-table"><table><thead><tr><th>Lokasi</th><th>Pendapatan</th><th>Stok</th><th>Kritis</th></tr></thead><tbody>@forelse(array_slice($report['locations'] ?? [],0,5) as $location)<tr><td>{{ $location['name'] }}</td><td>{{ $money($location['revenue']) }}</td><td>{{ $money($location['stock_value']) }}</td><td>{{ $location['critical_count'] }}</td></tr>@empty<tr><td colspan="4">Belum ada lokasi aktif.</td></tr>@endforelse</tbody></table></div></article>

        <article class="or-panel"><x-owner-panel-head title="Piutang & Aging" :url="$detail('receivables')"/><p>Status piutang dan umur tagihan pelanggan.</p><div class="or-two-metrics"><div><span>Total Piutang</span><b>{{ $money($summary['receivable_outstanding'] ?? 0) }}</b></div><div><span>Open Accounts</span><b>{{ $summary['open_accounts'] ?? 0 }}</b></div></div><div class="or-donut-row"><div class="or-aging-donut" style="--current:{{ ((float)($aging['not_due'] ?? $aging['current'] ?? 0)/$agingTotal)*100 }}%;--overdue:{{ (100-((float)($aging['not_due'] ?? $aging['current'] ?? 0)/$agingTotal)*100) }}%"><b>{{ $money($summary['receivable_outstanding'] ?? 0) }}</b><span>Piutang</span></div><div><span>Current</span><b>{{ $money($aging['not_due'] ?? $aging['current'] ?? 0) }}</b><span>Overdue</span><b>{{ $money($summary['overdue_receivable'] ?? 0) }}</b></div></div><h4>Daftar Piutang Terbaru</h4><div class="or-table"><table><thead><tr><th>No.</th><th>Customer</th><th>Jatuh Tempo</th><th>Nilai</th></tr></thead><tbody>@forelse($report['receivables'] ?? [] as $row)<tr><td>{{ $row['number'] }}</td><td>{{ $row['customer'] }}</td><td>{{ $row['due_date'] }}</td><td>{{ $money($row['outstanding']) }}</td></tr>@empty<tr><td colspan="4">Tidak ada piutang aktif.</td></tr>@endforelse</tbody></table></div></article>

        <article class="or-panel"><x-owner-panel-head title="Stok & Risiko Persediaan" :url="$detail('inventory')"/><p>Kondisi stok, risiko, dan pergerakan persediaan.</p><div class="or-donut-row"><div class="or-stock-donut" style="--available:{{ max(0,min(100,($onHand/$stockTotal)*100)) }}%"><b>{{ qty((string)data_get($inventory,'on_hand',0)) }}</b><span>Total Stok</span></div><div><span>Available</span><b>{{ qty((string)data_get($inventory,'available',0)) }}</b><span>Reserved</span><b>{{ qty((string)data_get($inventory,'reserved',0)) }}</b></div></div><div class="or-risk-chips"><div>⚡<span>Fast Moving<b>{{ data_get($inventory,'fast_moving',0) }}</b></span></div><div>◴<span>Slow Moving<b>{{ data_get($inventory,'slow_moving',0) }}</b></span></div></div><h4>Top Stok Kritis</h4><div class="or-table"><table><thead><tr><th>SKU</th><th>Produk</th><th>Stok</th></tr></thead><tbody>@forelse(data_get($inventory,'alerts',[]) as $row)<tr><td>{{ $row['sku'] }}</td><td>{{ $row['product'] }}</td><td>{{ qty((string)$row['available']) }}</td></tr>@empty<tr><td colspan="3">Tidak ada stok kritis.</td></tr>@endforelse</tbody></table></div></article>

        <article class="or-panel"><x-owner-panel-head title="Pembelian & Supplier" :url="$detail('purchasing')"/><p>Realisasi pembelian dan performa supplier.</p><div class="or-purchase"><svg><use href="#or-cart"/></svg><span>Total Pembelian<b>{{ $money(data_get($report,'purchasing.value')) }}</b></span></div><div class="or-risk-chips"><div>★<span>Supplier Score<b>{{ qty((string)data_get($report,'purchasing.supplier_score',0)) }}/100</b></span></div><div>♙<span>Supplier Aktif<b>{{ count(data_get($report,'purchasing.suppliers',[])) }}</b></span></div></div><h4>Supplier & Order Terakhir</h4><div class="or-table"><table><thead><tr><th>Supplier</th><th>PO</th><th>Nilai</th><th>Terakhir</th></tr></thead><tbody>@forelse(data_get($report,'purchasing.suppliers',[]) as $row)<tr><td>{{ $row['supplier'] }}</td><td>{{ $row['po_count'] }}</td><td>{{ $money($canViewSensitive ? $row['value'] : null) }}</td><td>{{ $row['last_order'] }}</td></tr>@empty<tr><td colspan="4">Belum ada pembelian.</td></tr>@endforelse</tbody></table></div></article>

        <article class="or-panel"><x-owner-panel-head title="Tenaga Kerja & Bonus" :url="$detail('workforce')"/><p>Kehadiran, lembur, dan kompensasi karyawan.</p><div class="or-workforce">@foreach([['Hadir',data_get($report,'workforce.present',0),'or-users'],['Terlambat',data_get($report,'workforce.late',0),'or-clock'],['Izin/Sakit',data_get($report,'workforce.permission',0),'or-alert'],['Lembur',qty((string)data_get($report,'workforce.overtime_minutes',0)).' menit','or-coins']] as [$label,$value,$icon])<div><svg><use href="#{{ $icon }}"/></svg><span>{{ $label }}<b>{{ $value }}</b></span></div>@endforeach</div><div class="or-workforce-note"><i class="ki-outline ki-chart-simple"></i><span>Data kehadiran dihitung dari periode dan lokasi yang sedang dipilih.</span></div></article>
    </section>

    <section class="or-bottom">
        <article><x-owner-panel-head title="Fokus Owner Minggu Ini" :url="$detail('overview')"/><p>Prioritas utama untuk meningkatkan kinerja dan mengurangi risiko usaha.</p><div class="or-priorities">@foreach([['1','Kendalikan stok kritis',($summary['critical_stock_count'] ?? 0).' produk perlu respons'],['2','Percepat penagihan',$money($summary['overdue_receivable'] ?? 0).' overdue'],['3','Tinjau pembelian',$money(data_get($report,'purchasing.value')).' periode aktif'],['4','Optimalkan margin',$marginLabel.' margin aktual']] as [$rank,$title,$note])<div><b>{{ $rank }}</b><span><strong>{{ $title }}</strong><small>{{ $note }}</small></span></div>@endforeach</div></article>
        <article><x-owner-panel-head title="Anomali & Keputusan" :url="$detail('profitability')"/><p>Temuan penting yang memerlukan keputusan owner.</p><div class="or-anomalies"><div class="danger">!<span><b>Overdue tinggi</b><small>{{ $money($summary['overdue_receivable'] ?? 0) }}</small></span></div><div>✓<span><b>Approval menunggu</b><small>{{ $summary['pending_approval'] ?? 0 }}</small></span></div><div>✓<span><b>Anomali terbuka</b><small>{{ $summary['anomaly_open'] ?? 0 }}</small></span></div><div class="warning">▣<span><b>Stok kritis</b><small>{{ $summary['critical_stock_count'] ?? 0 }} produk</small></span></div></div></article>
    </section>
</div>
