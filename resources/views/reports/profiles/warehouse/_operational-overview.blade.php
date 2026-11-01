@php
    use App\Support\CurrencyFormatter;
    $summary = $report['summary'] ?? [];
    $movement = collect($report['movements'] ?? [])->values();
    $transfer = collect($report['transfers'] ?? [])->values();
    $status = $report['stock_status'] ?? [];
    $statusTotal = max(1, array_sum(array_map('intval', $status)));
    $movementMax = max(1, (float) $movement->max(fn ($row) => max((float) data_get($row, 'incoming', 0), (float) data_get($row, 'outgoing', 0))));
    $transferMax = max(1, (float) $transfer->max(fn ($row) => max((float) data_get($row, 'incoming', 0), (float) data_get($row, 'outgoing', 0))));
    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y').' – '.\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');
    $params = fn (string $key): array => array_filter(['report' => $key, 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id, 'range' => $filters['range'] ?? 'monthly']);
    $detail = fn (string $key): string => route('reports.profiles.warehouse.show', $params($key));
    $money = fn ($value): string => $value === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string) $value);
    $topSupplierValue = max(1, (float) collect(data_get($report, 'inbound.suppliers', []))->max('value'));
@endphp

@include('reports.profiles.warehouse._operational-overview-styles')

<div class="warehouse-report">
    <svg class="wr-symbols" aria-hidden="true"><symbol id="wr-box" viewBox="0 0 24 24"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Zm0 0 8 4m8-4-8 4m0 10V11"/></symbol><symbol id="wr-stack" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Zm-9 10 9 5 9-5M3 8v10l9 5 9-5V8"/></symbol><symbol id="wr-swap" viewBox="0 0 24 24"><path d="M4 7h14m0 0-4-4m4 4-4 4M20 17H6m0 0 4 4m-4-4 4-4"/></symbol><symbol id="wr-truck" viewBox="0 0 24 24"><path d="M3 5h11v12H3V5Zm11 4h4l3 4v4h-7V9ZM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></symbol><symbol id="wr-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.9"/></symbol><symbol id="wr-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-4-10 3 3 5-6"/></symbol></svg>

    <header class="wr-header">
        <div class="wr-heading"><span class="wr-logo"><svg><use href="#wr-box"/></svg></span><div><h1>Laporan Operasional Gudang</h1><p>Ringkasan persediaan, mutasi, penerimaan, transfer, koreksi, dan performa tim.</p></div></div>
        <div class="wr-actions"><span class="wr-period"><i class="ki-outline ki-calendar"></i>{{ $periodLabel }}</span>@if($canExport)<button class="wr-button" type="submit" form="warehouseExport"><i class="ki-outline ki-exit-down"></i> Ekspor</button>@endif</div>
    </header>
    @if($canExport)<form id="warehouseExport" method="POST" action="{{ route('reports.profiles.exports.store', ['profile' => 'warehouse', 'report' => 'overview']) }}" hidden>@csrf<input type="hidden" name="format" value="csv"><input type="hidden" name="start_date" value="{{ $filters['start_date'] }}"><input type="hidden" name="end_date" value="{{ $filters['end_date'] }}"><input type="hidden" name="work_location_id" value="{{ $selectedLocation?->id }}"><input type="hidden" name="range" value="{{ $filters['range'] ?? 'monthly' }}"></form>@endif
    <section class="wr-filter">@include('reports.profiles.partials.filter')</section>

    <section class="wr-hero">
        <div class="wr-hero-title"><h2>Ringkasan Gudang</h2><p>{{ $periodLabel }} · {{ $selectedLocation?->name ?? 'Lokasi gudang' }}</p></div>
        <div class="wr-hero-kpis">
            @foreach([['Total Produk',$summary['total_products'] ?? 0,'wr-box'],['Stok Tersedia',qty((string)($summary['available_quantity'] ?? 0)),'wr-stack'],['Nilai HPP',$money($summary['layer_value'] ?? null),'wr-stack'],['Total Mutasi',$summary['mutation_count'] ?? 0,'wr-swap'],['Penerimaan',$summary['receipt_count'] ?? 0,'wr-truck'],['Supplier Aktif',$summary['supplier_count'] ?? 0,'wr-users']] as [$label,$value,$icon])
                <article><svg><use href="#{{ $icon }}"/></svg><span>{{ $label }}</span><strong title="{{ $value }}">{{ $value }}</strong><small>Periode aktif</small></article>
            @endforeach
        </div>
        <div class="wr-warehouse-art" aria-hidden="true"><i></i><b>GUDANG</b><span>▦</span></div>
    </section>

    <section class="wr-mini-grid">
        @foreach([['Persediaan (Qty)',qty((string)($summary['available_quantity'] ?? 0)),'wr-stack','blue'],['Nilai HPP Berlapis',$money($summary['layer_value'] ?? null),'wr-box','green'],['Mutasi (Masuk + Keluar)',$summary['mutation_count'] ?? 0,'wr-swap','cyan'],['Penerimaan',$money(data_get($report,'inbound.value')),'wr-truck','orange'],['Supplier Aktif',$summary['supplier_count'] ?? 0,'wr-users','green']] as [$label,$value,$icon,$tone])
            <article class="wr-mini {{ $tone }}"><svg><use href="#{{ $icon }}"/></svg><div><span>{{ $label }}</span><strong title="{{ $value }}">{{ $value }}</strong><small>Lokasi dan periode aktif</small></div></article>
        @endforeach
    </section>

    <section class="wr-chart-grid">
        <article class="wr-card wr-chart-wide"><div class="wr-card-head"><h2>Tren Mutasi Persediaan</h2><span class="wr-pill">Qty</span></div><svg class="wr-line-chart" viewBox="0 0 620 190" role="img" aria-label="Grafik mutasi masuk dan keluar">
            @foreach([30,70,110,150] as $y)<line x1="45" y1="{{ $y }}" x2="600" y2="{{ $y }}" class="grid"/>@endforeach
            @php $count=max(1,$movement->count()-1);$inPoints=[];$outPoints=[];foreach($movement as $i=>$row){$x=55+($i/$count)*525;$inY=160-(((float)data_get($row,'incoming',0)/$movementMax)*120);$outY=160-(((float)data_get($row,'outgoing',0)/$movementMax)*120);$inPoints[]="$x,$inY";$outPoints[]="$x,$outY";} @endphp
            <polyline points="{{ implode(' ',$inPoints) }}" class="line incoming"/><polyline points="{{ implode(' ',$outPoints) }}" class="line outgoing"/>
            @foreach($movement as $i=>$row)@php $x=55+($i/$count)*525;$inY=160-(((float)data_get($row,'incoming',0)/$movementMax)*120);$outY=160-(((float)data_get($row,'outgoing',0)/$movementMax)*120); @endphp<circle cx="{{ $x }}" cy="{{ $inY }}" r="5" class="point incoming"><title>{{ data_get($row,'date','-') }} · Masuk {{ qty((string)data_get($row,'incoming',0)) }}</title></circle><circle cx="{{ $x }}" cy="{{ $outY }}" r="5" class="point outgoing"><title>{{ data_get($row,'date','-') }} · Keluar {{ qty((string)data_get($row,'outgoing',0)) }}</title></circle>@endforeach
        </svg><div class="wr-legend"><span><i class="in"></i>Masuk</span><span><i class="out"></i>Keluar</span></div></article>

        <article class="wr-card"><div class="wr-card-head"><h2>Status Stok</h2></div><div class="wr-donut-row"><div class="wr-donut" style="--normal:{{ ((int)($status['normal'] ?? 0)/$statusTotal)*100 }}%;--critical:{{ (((int)($status['normal'] ?? 0)+(int)($status['critical'] ?? 0))/$statusTotal)*100 }}%"><b>{{ $summary['total_products'] ?? 0 }}</b><span>Produk</span></div><div class="wr-status-list">@foreach(['normal'=>['Stok Normal','green'],'critical'=>['Stok Kritis','orange'],'empty'=>['Perlu Restock','red']] as $key=>[$label,$color])<div title="{{ $label }}: {{ $status[$key] ?? 0 }} produk"><span><i class="{{ $color }}"></i>{{ $label }}</span><strong>{{ $status[$key] ?? 0 }} ({{ round(((int)($status[$key] ?? 0)/$statusTotal)*100,1) }}%)</strong></div>@endforeach</div></div></article>

        <article class="wr-card"><div class="wr-card-head"><h2>Transfer Masuk vs Keluar</h2></div><div class="wr-bars-chart">@forelse($transfer as $row)<div class="wr-bar-column"><div class="wr-bars"><i class="in" style="height:{{ max(3,((float)data_get($row,'incoming',0)/$transferMax)*100) }}%" title="Masuk {{ qty((string)data_get($row,'incoming',0)) }}"></i><i class="out" style="height:{{ max(3,((float)data_get($row,'outgoing',0)/$transferMax)*100) }}%" title="Keluar {{ qty((string)data_get($row,'outgoing',0)) }}"></i></div><span>{{ \Carbon\Carbon::parse(data_get($row,'date'))->format('d M') }}</span></div>@empty<div class="wr-empty">Belum ada transfer pada periode ini.</div>@endforelse</div></article>
    </section>

    <section class="wr-detail-grid">
        <article class="wr-card"><x-wr-head title="Persediaan & Nilai HPP" icon="wr-box" :url="$detail('inventory')"/><div class="wr-stats"><div><span>Total Produk</span><b>{{ $summary['total_products'] ?? 0 }}</b></div><div><span>Nilai HPP</span><b>{{ $money($summary['layer_value'] ?? null) }}</b></div><div><span>Stok Tersedia</span><b>{{ qty((string)($summary['available_quantity'] ?? 0)) }}</b></div></div><div class="wr-table-wrap"><table><thead><tr><th>SKU</th><th>Produk</th><th>Stok</th></tr></thead><tbody>@forelse($report['inventory_rows'] ?? [] as $row)<tr><td>{{ $row['sku'] }}</td><td>{{ $row['product'] }}</td><td>{{ qty((string)$row['available']) }}</td></tr>@empty<tr><td colspan="3">Belum ada persediaan.</td></tr>@endforelse</tbody></table></div></article>
        <article class="wr-card"><x-wr-head title="Mutasi & Kartu Stok" icon="wr-swap" :url="$detail('movements')"/><div class="wr-stats"><div><span>Total Mutasi</span><b>{{ $summary['mutation_count'] ?? 0 }}</b></div><div><span>Rentang</span><b>{{ ucfirst($filters['range'] ?? 'monthly') }}</b></div></div><div class="wr-mini-bars">@forelse($movement->take(8) as $row)<div><span>{{ \Carbon\Carbon::parse(data_get($row,'date'))->format('d M') }}</span><i><b class="in" style="width:{{ ((float)data_get($row,'incoming',0)/$movementMax)*100 }}%" title="Masuk {{ qty((string)data_get($row,'incoming',0)) }}"></b><b class="out" style="width:{{ ((float)data_get($row,'outgoing',0)/$movementMax)*100 }}%" title="Keluar {{ qty((string)data_get($row,'outgoing',0)) }}"></b></i></div>@empty<div class="wr-empty">Belum ada mutasi.</div>@endforelse</div></article>
        <article class="wr-card"><x-wr-head title="Penerimaan & Supplier" icon="wr-truck" :url="$detail('inbound')"/><div class="wr-stats"><div><span>Nilai Penerimaan</span><b>{{ $money(data_get($report,'inbound.value')) }}</b></div><div><span>Dokumen Posted</span><b>{{ data_get($report,'inbound.count',0) }}</b></div></div><div class="wr-suppliers">@forelse(data_get($report,'inbound.suppliers',[]) as $supplier)<div><span>{{ $supplier['name'] }}</span><i><b style="width:{{ ((float)($supplier['value'] ?? 0)/$topSupplierValue)*100 }}%"></b></i>@if($canViewSensitive)<strong>{{ $money($supplier['value']) }}</strong>@endif</div>@empty<div class="wr-empty">Belum ada penerimaan supplier.</div>@endforelse</div></article>
        <article class="wr-card"><x-wr-head title="Opname, Koreksi & Loss" icon="wr-shield" :url="$detail('controls')"/><div class="wr-stats three"><div><span>Total Koreksi</span><b>{{ data_get($report,'controls.corrections',0) }}</b></div><div><span>Total Loss</span><b>{{ data_get($report,'controls.loss_count',0) }}</b></div><div><span>Nilai Loss</span><b>{{ $money(data_get($report,'controls.loss_value')) }}</b></div></div><div class="wr-health"><span>✓</span><div><b>Kontrol persediaan periode aktif</b><p>Hanya transaksi final yang dihitung; draft tidak masuk laporan.</p></div></div></article>
        <article class="wr-card"><x-wr-head title="Tim Gudang" icon="wr-users" :url="$detail('team')"/><div class="wr-stats three"><div><span>Total Tim</span><b>{{ data_get($report,'team.total',0) }}</b></div><div><span>Hadir</span><b>{{ data_get($report,'team.present',0) }}</b></div><div><span>Terlambat</span><b>{{ data_get($report,'team.late',0) }}</b></div></div><div class="wr-table-wrap"><table><thead><tr><th>Nama</th><th>Peran</th><th>Status</th></tr></thead><tbody>@forelse(data_get($report,'team.rows',[]) as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['position'] }}</td><td><span class="wr-status">{{ $row['status'] }}</span></td></tr>@empty<tr><td colspan="3">Belum ada tim aktif.</td></tr>@endforelse</tbody></table></div></article>
        <article class="wr-card wr-insight"><div class="wr-card-head"><h2><i class="ki-outline ki-chart-line-up"></i> Insight Gudang</h2></div><ul><li><span>✓</span><b>{{ qty((string)($summary['available_quantity'] ?? 0)) }} stok tersedia</b> di lokasi aktif</li><li><span>✓</span><b>{{ $status['critical'] ?? 0 }} produk kritis</b> perlu dipantau</li><li><span>✓</span><b>{{ data_get($report,'controls.loss_count',0) }} loss final</b> pada periode aktif</li><li><span>✓</span><b>{{ data_get($report,'team.present',0) }} anggota tim</b> tercatat hadir</li></ul><div class="wr-insight-art">✓<small>▦ ▦</small></div></article>
    </section>
</div>
