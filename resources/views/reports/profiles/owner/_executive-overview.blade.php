@php
    use App\Support\CurrencyFormatter;

    $summary   = $report['summary']    ?? [];
    $health    = $report['health']     ?? [];
    $inventory = $report['inventory']  ?? [];
    $purchasing= $report['purchasing'] ?? [];
    $locations = $report['locations']  ?? [];
    $receivables = $report['receivables'] ?? [];
    $charts    = $report['charts']     ?? [];

    $revenueChart = collect(data_get($charts, 'revenue', []))->values();
    $channelMix   = collect(data_get($charts, 'channel_mix', []))->values();
    $aging        = data_get($charts, 'aging', []);

    $revenueMax   = max(1, (float) $revenueChart->max(fn($r) => (float)data_get($r,'value',data_get($r,'revenue',data_get($r,'total',0)))));
    $channelTotal = max(1, (float) $channelMix->sum(fn($r) => (float)data_get($r,'value',0)));
    $agingTotal   = max(1, (float) array_sum(array_map('floatval', is_array($aging) ? $aging : [])));

    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y')
                 .' – '
                 .\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');

    $params = fn(string $key): array => array_filter([
        'report' => $key,
        'start_date' => $filters['start_date'],
        'end_date'   => $filters['end_date'],
        'work_location_id' => $selectedLocation?->id,
        'range' => $filters['range'] ?? 'monthly',
    ]);
    $detail = fn(string $key): string => route('reports.profiles.owner.show', $params($key));

    $money = fn($v): string => $v === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string)$v);
    $marginLabel = $summary['margin_percent'] === null ? 'Akses terbatas' : str_replace('.', ',', (string) $summary['margin_percent']).'%';

    $scoreLabel = match(true) {
        ((int)data_get($health,'overall',0)) >= 80 => 'Sehat',
        ((int)data_get($health,'overall',0)) >= 60 => 'Perlu Perhatian',
        default => 'Berisiko',
    };
    $overallScore = (int) data_get($health, 'overall', 0);

    // Hitung item fokus/prioritas
    $criticalCount = (int) ($summary['critical_stock_count'] ?? 0);
    $overdueAmount = (float) ($summary['overdue_receivable'] ?? 0);
    $slowCount = (int) data_get($inventory, 'slow_moving', 0);
    $pendingCount = (int) ($summary['pending_approval'] ?? 0);
    $focusPriorities = ($criticalCount > 0 ? 1 : 0)
                     + ($overdueAmount > 0 ? 1 : 0)
                     + ($slowCount > 0 ? 1 : 0)
                     + ($pendingCount > 0 ? 1 : 0);

    // Donut calculations
    $arTotal = max(1, (float) ($summary['receivable_outstanding'] ?? 0));
    $arCurrent = max(0, $arTotal - $overdueAmount);
    $arCurrentPct = round(($arCurrent / $arTotal) * 100);

    $qtyOnHand = (float) data_get($inventory, 'on_hand', 0);
    $qtyReserved = (float) data_get($inventory, 'reserved', 0);
    $qtyTotal = max(1, $qtyOnHand + $qtyReserved);
    $qtyAvailablePct = round(($qtyOnHand / $qtyTotal) * 100);

    // 6 Kartu KPI
    $kpis = [
        [
            'ico'    => 'chart',
            'color'  => 'blue',
            'label'  => 'Pendapatan',
            'val'    => $money($summary['revenue'] ?? 0),
            'note'   => 'Penjualan pada periode terpilih',
            'pill'   => '',
            'accent' => false,
            'series' => data_get($report, 'sparklines.revenue', []),
            'tone'   => 'blue',
        ],
        [
            'ico'    => 'layer',
            'color'  => 'green',
            'label'  => 'Margin Kotor',
            'val'    => $canViewSensitive ? $money($summary['gross_margin'] ?? null) : 'Akses terbatas',
            'note'   => $summary['margin_percent'] !== null ? $marginLabel.' dari pendapatan' : 'Rasio margin usaha',
            'pill'   => '',
            'accent' => false,
            'series' => data_get($report, 'sparklines.gross_margin', []),
            'tone'   => 'green',
        ],
        [
            'ico'    => 'box',
            'color'  => '',
            'label'  => 'Nilai Persediaan',
            'val'    => $canViewSensitive ? $money($summary['stock_value'] ?? null) : 'Akses terbatas',
            'note'   => 'Berdasarkan HPP lapisan aktif',
            'pill'   => '',
            'accent' => true,
            'series' => data_get($report, 'sparklines.stock_value', []),
            'tone'   => 'gold',
        ],
        [
            'ico'    => 'coins',
            'color'  => 'blue',
            'label'  => 'Piutang Berjalan',
            'val'    => $money($summary['receivable_outstanding'] ?? 0),
            'note'   => 'Saldo tagihan belum lunas',
            'pill'   => '',
            'accent' => false,
            'series' => data_get($report, 'sparklines.receivable', []),
            'tone'   => 'blue',
        ],
        [
            'ico'    => 'alert',
            'color'  => $criticalCount > 0 ? 'red' : 'green',
            'label'  => 'Stok Kritis',
            'val'    => qty((string)$criticalCount),
            'note'   => $criticalCount > 0 ? 'SKU di bawah batas minimum' : 'Tidak ada SKU di bawah minimum',
            'pill'   => $criticalCount > 0 ? '<span class="pill alert">Prioritas</span>' : '',
            'accent' => false,
            'series' => data_get($report, 'sparklines.critical_stock', []),
            'tone'   => $criticalCount > 0 ? 'red' : 'green',
        ],
        [
            'ico'    => 'user',
            'color'  => 'blue',
            'label'  => 'Akun Piutang Terbuka',
            'val'    => qty((string)($summary['open_accounts'] ?? 0)),
            'note'   => 'Pelanggan dengan tagihan aktif',
            'pill'   => '',
            'accent' => false,
            'series' => data_get($report, 'sparklines.open_accounts', []),
            'tone'   => 'blue',
        ],
    ];
@endphp

@include('reports.profiles.owner._executive-overview-styles')

{{-- ══════════════════════════════════════════════════════════════════════════
     SVG SYMBOL DEFINITIONS (IDENTIK DENGAN PROTOTIPE HTML)
     ══════════════════════════════════════════════════════════════════════════ --}}
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-warehouse" viewBox="0 0 24 24"><path d="M3 21V8l9-5 9 5v13M3 21h18M7 21V11h10v10M7 15h10M7 18h10"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18M8 16v-4m5 4V8m5 8V5"/></symbol>
    <symbol id="i-cal" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></symbol>
    <symbol id="i-down" viewBox="0 0 24 24"><path d="M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M20 10c0 5-8 10-8 10s-8-5-8-10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></symbol>
    <symbol id="i-filter" viewBox="0 0 24 24"><path d="M3 5h18l-7 8v5l-4 2v-7Z"/></symbol>
    <symbol id="i-reset" viewBox="0 0 24 24"><path d="M4 11a8 8 0 1 1 1 5m-1-10v5h5"/></symbol>
    <symbol id="i-layer" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5"/></symbol>
    <symbol id="i-box" viewBox="0 0 24 24"><path d="M3 7l9-4 9 4v10l-9 4-9-4V7Zm0 0 9 4 9-4m-9 4v10M8 5l9 4v4"/></symbol>
    <symbol id="i-coins" viewBox="0 0 24 24"><ellipse cx="10" cy="6" rx="7" ry="3"/><path d="M3 6v5c0 1.7 3.1 3 7 3m7-8v4M3 11v5c0 1.7 3.1 3 7 3m0-5c0-1.7 2.5-3 5.5-3s5.5 1.3 5.5 3-2.5 3-5.5 3-5.5-1.3-5.5-3Zm0 0v4c0 1.7 2.5 3 5.5 3s5.5-1.3 5.5-3v-4"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3 2 20h20L12 3Zm0 6v5m0 3h.01"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="7" r="3"/><path d="M5 21v-3a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v3"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M2 20v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2m4 0v-2a4 4 0 0 0-2-3.5M16 4a3 3 0 0 1 0 6"/><circle cx="9" cy="7" r="3"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M4 12h15m-5-5 5 5-5 5"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-cart" viewBox="0 0 24 24"><path d="M2 4h3l2.5 11h11L22 7H6M9 20h.01M18 20h.01"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M6 18 18 6"/></symbol>
    <symbol id="i-bolt" viewBox="0 0 24 24"><path d="m13 2-9 12h7l-1 8 10-12h-7l1-8Z"/></symbol>
</svg>

<div class="gudang-dashboard-wrap">

    {{-- ══════════════════════════════════════════════════════════════════════
         HERO SECTION
         ══════════════════════════════════════════════════════════════════════ --}}
    <section class="hero" aria-labelledby="title">
        <svg class="hero-decor" viewBox="0 0 700 178" preserveAspectRatio="xMaxYMid slice" fill="none" aria-hidden="true">
            <defs>
                <pattern id="grid" width="27" height="27" patternUnits="userSpaceOnUse"><path d="M27 0H0v27" stroke="#b6cce8" stroke-opacity=".16"/></pattern>
                <linearGradient id="fade"><stop stop-color="#000"/><stop offset=".75" stop-color="#fff"/></linearGradient>
                <mask id="grid-mask"><rect width="700" height="178" fill="url(#fade)"/></mask>
                <linearGradient id="arc"><stop stop-color="#edba52" stop-opacity=".06"/><stop offset="1" stop-color="#edba52" stop-opacity=".65"/></linearGradient>
            </defs>
            <rect width="700" height="178" fill="url(#grid)" mask="url(#grid-mask)"/>
            <path d="M230 182c116-9 144-106 256-106 110 0 160-57 206-103M308 193c98-20 150-96 241-100 68-3 129-43 173-104" stroke="url(#arc)" stroke-width="1.2"/>
            <path d="M421 180c72-35 104-44 183-48 63-3 97-35 112-59" stroke="#a8d8ec" stroke-opacity=".18"/>
            <circle cx="486" cy="76" r="3" fill="#edba52" fill-opacity=".52"/>
            <circle cx="549" cy="93" r="2.5" fill="#edba52" fill-opacity=".52"/>
        </svg>

        <div>
            <div class="eyebrow">
                <svg class="ico" aria-hidden="true"><use href="#i-chart"/></svg>
                <span>EXECUTIVE BUSINESS REPORT</span>
            </div>
            <h1 id="title">Laporan Owner</h1>
            <p>Pantau kinerja toko dan gudang, kondisi persediaan, dan hal yang perlu ditindaklanjuti.</p>
            <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'overview', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash gold py-1 px-3" style="min-height:30px; font-size:11px;">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-chart"/></svg>
                    Ringkasan Eksekutif
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'income', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-coins"/></svg>
                    Laba Rugi &amp; Finansial
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'tax', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-check"/></svg>
                    Rekapitulasi Pajak
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'locations', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-warehouse"/></svg>
                    Performa Cabang
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'receivables', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-coins"/></svg>
                    Piutang &amp; Aging
                </a>
            </div>
        </div>

        <div class="hero-actions">
            <span class="hero-date" id="hero-date">
                <svg class="ico" aria-hidden="true"><use href="#i-cal"/></svg>
                <span>{{ $periodLabel }}</span>
            </span>
            {{-- Catatan 2: Ekspor dalam bentuk PDF --}}
            @if($canExport)
                <button class="btn-dash gold" type="submit" form="ownerExportPdfForm">
                    <svg class="ico" aria-hidden="true"><use href="#i-down"/></svg>
                    <span>Ekspor PDF</span>
                </button>
            @endif
        </div>
    </section>

    {{-- Form Ekspor PDF Tersembunyi (Catatan 2) --}}
    @if($canExport)
        <form id="ownerExportPdfForm" method="POST" action="{{ route('reports.profiles.exports.store', ['profile' => 'owner', 'report' => 'overview']) }}" hidden>
            @csrf
            <input type="hidden" name="format" value="pdf">
            <input type="hidden" name="start_date" value="{{ $filters['start_date'] }}">
            <input type="hidden" name="end_date" value="{{ $filters['end_date'] }}">
            <input type="hidden" name="work_location_id" value="{{ $selectedLocation?->id }}">
            <input type="hidden" name="range" value="{{ $filters['range'] ?? 'monthly' }}">
        </form>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         FILTER FORM
         ══════════════════════════════════════════════════════════════════════ --}}
    <form class="filters" id="filters" method="GET" action="{{ route('reports.profiles.owner.show', ['report' => 'overview']) }}" aria-label="Filter laporan">
        <label class="field">
            Tanggal mulai
            <span class="input-wrap">
                <svg class="ico" aria-hidden="true"><use href="#i-cal"/></svg>
                <input type="date" name="start_date" id="start" required value="{{ $filters['start_date'] }}">
            </span>
        </label>

        <label class="field">
            Tanggal akhir
            <span class="input-wrap">
                <svg class="ico" aria-hidden="true"><use href="#i-cal"/></svg>
                <input type="date" name="end_date" id="end" required value="{{ $filters['end_date'] }}">
            </span>
        </label>

        <label class="field">
            Lokasi operasional
            <span class="input-wrap">
                <svg class="ico" aria-hidden="true"><use href="#i-pin"/></svg>
                <select name="work_location_id" id="location">
                    <option value="">Semua lokasi berizin</option>
                    @foreach($workLocations as $loc)
                        <option value="{{ $loc->id }}" @selected((int)($filters['work_location_id'] ?? 0) === $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </span>
        </label>

        <label class="field">
            Rentang grafik
            <span class="input-wrap">
                <svg class="ico" aria-hidden="true"><use href="#i-chart"/></svg>
                <select name="range" id="group">
                    <option value="daily" @selected(($filters['range'] ?? 'daily') === 'daily')>Harian</option>
                    <option value="monthly" @selected(($filters['range'] ?? '') === 'monthly')>Bulanan</option>
                    <option value="yearly" @selected(($filters['range'] ?? '') === 'yearly')>Tahunan</option>
                </select>
            </span>
        </label>

        <div class="filters-end">
            <button class="btn-dash green" type="submit">
                <svg class="ico" aria-hidden="true"><use href="#i-filter"/></svg>
                <span>Terapkan</span>
            </button>
            <a href="{{ route('reports.profiles.owner.show', ['report' => 'overview']) }}" class="btn-dash clear icon-only" title="Atur ulang filter" aria-label="Atur ulang filter">
                <svg class="ico" aria-hidden="true"><use href="#i-close"/></svg>
            </a>
            <a href="{{ route('reports.profiles.owner.show', $params('overview')) }}" class="btn-dash clear icon-only" title="Perbarui laporan" aria-label="Perbarui laporan">
                <svg class="ico" aria-hidden="true"><use href="#i-reset"/></svg>
            </a>
        </div>
    </form>

    {{-- Catatan 3: Tab menu dihapus sesuai arahan user --}}

    {{-- ══════════════════════════════════════════════════════════════════════
         RINGKASAN KINERJA & 6 KPIS
         ══════════════════════════════════════════════════════════════════════ --}}
    <section id="ringkasan" aria-labelledby="overview">
        <div class="section-head">
            <div>
                <h2 id="overview">Ringkasan kinerja</h2>
                <p id="scope">{{ $selectedLocation ? $selectedLocation->name : 'Semua lokasi berizin' }}</p>
            </div>
            <div class="refresh" id="refresh-label">
                <svg class="ico" aria-hidden="true"><use href="#i-clock"/></svg>
                <span>Posisi per {{ \Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y') }}</span>
            </div>
        </div>

        <div id="kpis" class="kpis">
            @foreach($kpis as $index => $kpi)
                <article class="card-dash kpi{{ $kpi['accent'] ? ' accent' : '' }}">
                    <div class="kpi-top">
                        <span class="sq {{ $kpi['color'] }}">
                            <svg class="ico" aria-hidden="true"><use href="#i-{{ $kpi['ico'] }}"/></svg>
                        </span>
                        {!! $kpi['pill'] !!}
                    </div>
                    <div>
                        <div class="kpi-name">{{ $kpi['label'] }}</div>
                        <div class="kpi-value" title="{{ $kpi['val'] }}">{{ $kpi['val'] }}</div>
                        <div class="kpi-note">{{ $kpi['note'] }}</div>
                    </div>
                    <span data-owner-kpi-chart hidden></span>
                </article>
            @endforeach
        </div>

        {{-- ══════════════════════════════════════════════════════════════════
             MAIN GRID: TREN PENDAPATAN vs PERLU DITINDAKLANJUTI
             ══════════════════════════════════════════════════════════════════ --}}
        <div class="main-grid">
            {{-- Tren Pendapatan --}}
            <article class="card-dash panel">
                <div class="panel-head">
                    <div>
                        <h3>Tren pendapatan</h3>
                        <p>Pergerakan pendapatan sesuai periode terpilih</p>
                    </div>
                    <a href="{{ $detail('income') }}" class="detail">
                        Lihat detail <svg class="ico" aria-hidden="true"><use href="#i-arrow"/></svg>
                    </a>
                </div>

                <div class="trend-meta">
                    <div>
                        <strong>{{ $money($summary['revenue'] ?? 0) }}</strong>
                        <small>Total periode terpilih</small>
                    </div>
                    @if($revenueChart->isNotEmpty())
                        <div class="legend">
                            <span><i class="dot navy"></i>Pendapatan ({{ ucfirst((string)$filters['range']) }})</span>
                        </div>
                    @endif
                </div>

                @if($revenueChart->isEmpty())
                    <div class="chart-empty">
                        <svg class="ico" aria-hidden="true"><use href="#i-chart"/></svg>
                        <span>Belum ada transaksi pada periode ini.</span>
                    </div>
                @else
                    @php
                        $rc = max(1, $revenueChart->count() - 1);
                        $stepVal = $revenueMax / 4;
                        $width = 540;
                        $left = 40;
                        $right = 15;
                        $bottom = 135;
                        $top = 15;
                        $plotWidth = $width - $left - $right;
                        $barW = min(40, max(12, ($plotWidth / $revenueChart->count()) * 0.45));

                        $yCoord = fn($v) => $bottom - (($v / $revenueMax) * ($bottom - $top));
                        $xCoord = fn($idx) => $left + (($idx + 0.5) * ($plotWidth / $revenueChart->count()));

                        $points = [];
                        foreach ($revenueChart as $i => $row) {
                            $v = (float) data_get($row, 'value', data_get($row, 'revenue', data_get($row, 'total', 0)));
                            $points[] = round($xCoord($i), 1).','.round($yCoord($v), 1);
                        }
                    @endphp
                    <div class="chart">
                        <svg viewBox="0 0 {{ $width }} 164" role="group" aria-label="Grafik pendapatan">
                            {{-- Grid lines & labels --}}
                            @for($i = 0; $i <= 4; $i++)
                                @php
                                    $currStep = $stepVal * $i;
                                    $y = $yCoord($currStep);
                                    $label = $currStep >= 1000000 ? round($currStep / 1000000, 1).' jt' : ($currStep >= 1000 ? round($currStep / 1000).' rb' : round($currStep));
                                @endphp
                                <line x1="{{ $left }}" x2="{{ $width - $right }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#e4ecf5" stroke-dasharray="{{ $i > 0 ? '3 4' : '0' }}"/>
                                <text class="axis" x="{{ $left - 8 }}" y="{{ $y + 4 }}" text-anchor="end">{{ $label }}</text>
                            @endfor

                            {{-- Bars --}}
                            @foreach($revenueChart as $i => $row)
                                @php
                                    $v = (float) data_get($row, 'value', data_get($row, 'revenue', data_get($row, 'total', 0)));
                                    $bx = $xCoord($i) - ($barW / 2);
                                    $by = $yCoord($v);
                                    $bh = max(2, $bottom - $by);
                                    $dateStr = (string) data_get($row, 'date', data_get($row, 'label', '-'));
                                @endphp
                                <rect class="bar" x="{{ round($bx, 1) }}" y="{{ round($by, 1) }}" width="{{ round($barW, 1) }}" height="{{ round($bh, 1) }}" rx="4">
                                    <title>{{ $dateStr }} · {{ $money($v) }}</title>
                                </rect>
                            @endforeach

                            {{-- Polyline & Circles --}}
                            @if(count($points) > 1)
                                <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="#0a9a65" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity=".9"/>
                                @foreach($revenueChart as $i => $row)
                                    @php
                                        $v = (float) data_get($row, 'value', data_get($row, 'revenue', data_get($row, 'total', 0)));
                                    @endphp
                                    <circle cx="{{ round($xCoord($i), 1) }}" cy="{{ round($yCoord($v), 1) }}" r="2.8" fill="#fff" stroke="#0a8b5a" stroke-width="1.5"/>
                                @endforeach
                            @endif

                            {{-- X Axis Labels --}}
                            @foreach($revenueChart as $i => $row)
                                @php
                                    $dateStr = (string) data_get($row, 'date', data_get($row, 'label', '-'));
                                    // Sederhanakan format label tanggal
                                    if (strlen($dateStr) >= 10 && str_contains($dateStr, '-')) {
                                        $dateStr = \Carbon\Carbon::parse($dateStr)->format('d M');
                                    }
                                @endphp
                                @if($revenueChart->count() <= 10 || $i % (intdiv($revenueChart->count(), 7) ?: 1) === 0 || $i === $revenueChart->count() - 1)
                                    <text class="axis" x="{{ round($xCoord($i), 1) }}" y="153" text-anchor="middle">{{ $dateStr }}</text>
                                @endif
                            @endforeach
                        </svg>
                    </div>
                @endif
            </article>

            {{-- Perlu Ditindaklanjuti --}}
            <article class="card-dash panel focus" aria-labelledby="focus-title">
                <div class="focus-header">
                    <div class="panel-title">
                        <span class="sq"><svg class="ico" aria-hidden="true"><use href="#i-alert"/></svg></span>
                        <div>
                            <h3 id="focus-title">Perlu ditindaklanjuti</h3>
                            <p>Prioritas dari laporan yang sedang dilihat</p>
                        </div>
                    </div>
                    <span class="pill {{ $focusPriorities > 0 ? 'warn' : 'ok' }}">
                        {{ $focusPriorities > 0 ? $focusPriorities.' kategori' : 'Tidak ada prioritas' }}
                    </span>
                </div>

                <div class="focus-items">
                    {{-- Stok Kritis --}}
                    <a href="{{ $detail('inventory') }}" class="focus-item">
                        <span class="sq {{ $criticalCount > 0 ? 'red' : 'green' }}">
                            <svg class="ico" aria-hidden="true"><use href="#i-{{ $criticalCount > 0 ? 'alert' : 'check' }}"/></svg>
                        </span>
                        <span>
                            <strong>Stok di bawah batas minimum</strong>
                            <small>{{ $criticalCount > 0 ? $criticalCount.' SKU perlu dicek untuk restok' : 'Tidak ada SKU kritis' }}</small>
                        </span>
                        <svg class="ico" aria-hidden="true"><use href="#i-chevron"/></svg>
                    </a>

                    {{-- Piutang Overdue --}}
                    <a href="{{ $detail('receivables') }}" class="focus-item">
                        <span class="sq {{ $overdueAmount > 0 ? 'red' : 'green' }}">
                            <svg class="ico" aria-hidden="true"><use href="#i-{{ $overdueAmount > 0 ? 'clock' : 'check' }}"/></svg>
                        </span>
                        <span>
                            <strong>Piutang lewat jatuh tempo</strong>
                            <small>{{ $overdueAmount > 0 ? $money($overdueAmount).' tagihan terlambat' : 'Tidak ada tagihan terlambat' }}</small>
                        </span>
                        <svg class="ico" aria-hidden="true"><use href="#i-chevron"/></svg>
                    </a>

                    {{-- Persediaan Lambat Bergerak --}}
                    <a href="{{ $detail('inventory') }}" class="focus-item">
                        <span class="sq {{ $slowCount > 0 ? '' : 'green' }}">
                            <svg class="ico" aria-hidden="true"><use href="#i-{{ $slowCount > 0 ? 'box' : 'check' }}"/></svg>
                        </span>
                        <span>
                            <strong>Persediaan lambat bergerak</strong>
                            <small>{{ $slowCount > 0 ? $slowCount.' SKU perlu ditinjau' : 'Tidak ada SKU lambat' }}</small>
                        </span>
                        <svg class="ico" aria-hidden="true"><use href="#i-chevron"/></svg>
                    </a>

                    {{-- Approval Menunggu --}}
                    <a href="{{ $detail('overview') }}" class="focus-item">
                        <span class="sq {{ $pendingCount > 0 ? 'red' : 'green' }}">
                            <svg class="ico" aria-hidden="true"><use href="#i-{{ $pendingCount > 0 ? 'bolt' : 'check' }}"/></svg>
                        </span>
                        <span>
                            <strong>Persetujuan transaksi menunggu</strong>
                            <small>{{ $pendingCount > 0 ? $pendingCount.' pengajuan butuh approval' : 'Semua transaksi approved' }}</small>
                        </span>
                        <svg class="ico" aria-hidden="true"><use href="#i-chevron"/></svg>
                    </a>
                </div>

                <div class="focus-foot">
                    <svg class="ico" aria-hidden="true"><use href="#i-info"/></svg>
                    <span>Posisi per {{ \Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y') }}</span>
                </div>
            </article>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════
             SKOR KESEHATAN BISNIS (DENGAN TOMBOL MODAL - CATATAN 1)
             ══════════════════════════════════════════════════════════════════ --}}
        <article class="card-dash health" id="health" aria-label="Skor kesehatan bisnis">
            <div class="health-lead">
                <div class="score" style="--score:{{ $overallScore }}%" aria-label="{{ $overallScore }} dari 100">
                    {{ $overallScore }}
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <strong>Skor kesehatan bisnis</strong>
                        {{-- Catatan 1: Tombol Modal Rumus Pengambilan Nilai --}}
                        <button type="button" class="health-info-btn" data-bs-toggle="modal" data-bs-target="#modalRumusSkorBisnis" title="Lihat Rumus Perhitungan" aria-label="Lihat Rumus Perhitungan">
                            <svg class="ico" aria-hidden="true"><use href="#i-info"/></svg>
                            <span>Rumus Skor</span>
                        </button>
                    </div>
                    <small>Skor gabungan · {{ $overallScore }}/100 ({{ $scoreLabel }})</small>
                </div>
            </div>

            <div class="health-metrics">
                @foreach([
                    'profitability' => 'Profitabilitas',
                    'liquidity'     => 'Likuiditas',
                    'inventory'     => 'Persediaan',
                    'collection'    => 'Penagihan',
                    'operations'    => 'Operasional',
                ] as $key => $lbl)
                    @php
                        $scoreVal = data_get($health, $key);
                        $num = $scoreVal === null ? 0 : (int)$scoreVal;
                    @endphp
                    <div class="health-one">
                        <small>{{ $lbl }}</small>
                        <strong>{{ $scoreVal === null ? '—' : $scoreVal }}<em>/100</em></strong>
                        <div class="progress-health" aria-hidden="true">
                            <span style="width:{{ $num }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    {{-- ══════════════════════════════════════════════════════════════════════
         DETAIL OPERASIONAL (6 MODULE CARDS)
         ══════════════════════════════════════════════════════════════════════ --}}
    <section id="operasional" aria-labelledby="ops-title">
        <div class="section-head">
            <div>
                <h2 id="ops-title">Detail operasional</h2>
                <p>Telusuri metrik per bagian dan lokasi.</p>
            </div>
            <div class="section-label">6 bagian laporan</div>
        </div>

        <div class="modules" id="modules">

            {{-- 1. Pendapatan & Channel Mix --}}
            <article class="card-dash panel module" id="mod-pendapatan">
                <div class="panel-head">
                    <div class="panel-title">
                        <span class="sq blue"><svg class="ico" aria-hidden="true"><use href="#i-chart"/></svg></span>
                        <div>
                            <h3>Pendapatan &amp; Channel Mix</h3>
                            <p>Retail POS dan penjualan B2B</p>
                        </div>
                    </div>
                    <a href="{{ $detail('income') }}" class="detail">Lihat detail <svg class="ico" aria-hidden="true"><use href="#i-arrow"/></svg></a>
                </div>
                <div class="module-body">
                    <div class="mini-row">
                        <div class="mini">
                            <small>Retur</small>
                            <b>{{ $money($summary['returns_value'] ?? 0) }}</b>
                        </div>
                        <div class="mini">
                            <small>Selisih kas</small>
                            <b>{{ $money($summary['cash_difference'] ?? 0) }}</b>
                        </div>
                    </div>
                    @foreach($channelMix as $ch)
                        @php
                            $cVal = (float) data_get($ch, 'value', 0);
                            $cPct = $channelTotal > 0 ? ($cVal / $channelTotal * 100) : 0;
                            $barColor = str_contains(strtolower((string)data_get($ch,'label')), 'b2b') ? '#b68029' : '#24496f';
                        @endphp
                        <div class="channel">
                            <div class="channel-label">
                                <span>{{ data_get($ch, 'label', '-') }} · {{ number_format($cPct, 1) }}%</span>
                                <b>{{ $money($cVal) }}</b>
                            </div>
                            <div class="progress-channel" aria-hidden="true">
                                <span style="width:{{ $cPct }}%; --bar:{{ $barColor }}"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="module-foot">Total pendapatan {{ $money($summary['revenue'] ?? 0) }}</div>
            </article>

            {{-- 2. Perbandingan Lokasi --}}
            <article class="card-dash panel module" id="mod-lokasi">
                <div class="panel-head">
                    <div class="panel-title">
                        <span class="sq blue"><svg class="ico" aria-hidden="true"><use href="#i-pin"/></svg></span>
                        <div>
                            <h3>Perbandingan Lokasi</h3>
                            <p>Kinerja toko dan gudang</p>
                        </div>
                    </div>
                    <a href="{{ $detail('locations') }}" class="detail">Lihat detail <svg class="ico" aria-hidden="true"><use href="#i-arrow"/></svg></a>
                </div>
                <div class="module-body">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Lokasi</th>
                                    <th>Pendapatan</th>
                                    <th>Nilai stok</th>
                                    <th class="num">Kritis</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(array_slice($locations, 0, 3) as $loc)
                                    <tr>
                                        <td>{{ $loc['name'] }}</td>
                                        <td>{{ $money($loc['revenue']) }}</td>
                                        <td>{{ $money($canViewSensitive ? $loc['stock_value'] : null) }}</td>
                                        <td class="num">{{ $loc['critical_count'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="empty" colspan="4">Belum ada lokasi aktif.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="module-foot">{{ count($locations) }} lokasi ditampilkan · Posisi per {{ \Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y') }}</div>
            </article>

            {{-- 3. Piutang & Umur Tagihan --}}
            <article class="card-dash panel module" id="mod-piutang">
                <div class="panel-head">
                    <div class="panel-title">
                        <span class="sq"><svg class="ico" aria-hidden="true"><use href="#i-coins"/></svg></span>
                        <div>
                            <h3>Piutang &amp; Umur Tagihan</h3>
                            <p>Saldo dan status jatuh tempo</p>
                        </div>
                    </div>
                    <a href="{{ $detail('receivables') }}" class="detail">Lihat detail <svg class="ico" aria-hidden="true"><use href="#i-arrow"/></svg></a>
                </div>
                <div class="module-body">
                    <div class="donut-box">
                        <div class="donut" style="--pct:{{ $arCurrentPct }}%; --ring:#24496f; --ring2:{{ $overdueAmount > 0 ? '#d1983d' : '#e8eef5' }}">
                            <b>{{ count($receivables) }}</b>
                            <small>Tagihan</small>
                        </div>
                        <div class="legend-stack">
                            <div>
                                <i class="dot navy"></i>
                                <span>Belum jatuh tempo</span>
                                <b>{{ $money($arCurrent) }}</b>
                            </div>
                            <div>
                                <i class="dot gold"></i>
                                <span>Lewat jatuh tempo</span>
                                <b>{{ $money($overdueAmount) }}</b>
                            </div>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No. tagihan</th>
                                    <th>Jatuh tempo</th>
                                    <th class="num">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(array_slice($receivables, 0, 2) as $ar)
                                    <tr>
                                        <td>{{ $ar['number'] }}</td>
                                        <td>{{ \Carbon\Carbon::parse($ar['due_date'])->format('d M') }}</td>
                                        <td class="num">{{ $money($ar['outstanding']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="empty" colspan="3">Tidak ada piutang aktif.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="module-foot">Posisi per {{ \Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y') }}</div>
            </article>

            {{-- 4. Stok & Risiko Persediaan --}}
            <article class="card-dash panel module" id="mod-persediaan">
                <div class="panel-head">
                    <div class="panel-title">
                        <span class="sq green"><svg class="ico" aria-hidden="true"><use href="#i-box"/></svg></span>
                        <div>
                            <h3>Stok &amp; Risiko Persediaan</h3>
                            <p>Stok tersedia, dipesan, dan kritis</p>
                        </div>
                    </div>
                    <a href="{{ $detail('inventory') }}" class="detail">Lihat detail <svg class="ico" aria-hidden="true"><use href="#i-arrow"/></svg></a>
                </div>
                <div class="module-body">
                    <div class="donut-box">
                        <div class="donut" style="--pct:{{ $qtyAvailablePct }}%; --ring:#079764; --ring2:#a5b7cd">
                            <b>{{ qty((string)$qtyOnHand) }}</b>
                            <small>Unit stok</small>
                        </div>
                        <div class="legend-stack">
                            <div>
                                <i class="dot"></i>
                                <span>Tersedia</span>
                                <b>{{ qty((string)data_get($inventory,'available',0)) }}</b>
                            </div>
                            <div>
                                <i class="dot gray"></i>
                                <span>Dipesan</span>
                                <b>{{ qty((string)$qtyReserved) }}</b>
                            </div>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Produk</th>
                                    <th class="num">Stok</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(array_slice(data_get($inventory, 'alerts', []), 0, 2) as $row)
                                    <tr>
                                        <td>{{ $row['sku'] }}</td>
                                        <td>{{ $row['product'] }}</td>
                                        <td class="num">{{ qty((string)$row['available']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="empty" colspan="3">Tidak ada stok kritis.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="module-foot">
                    Cepat bergerak {{ data_get($inventory, 'fast_moving', 0) }} SKU · Lambat bergerak {{ data_get($inventory, 'slow_moving', 0) }} SKU
                </div>
            </article>

            {{-- 5. Pembelian & Supplier --}}
            <article class="card-dash panel module" id="mod-pembelian">
                <div class="panel-head">
                    <div class="panel-title">
                        <span class="sq"><svg class="ico" aria-hidden="true"><use href="#i-cart"/></svg></span>
                        <div>
                            <h3>Pembelian &amp; Supplier</h3>
                            <p>Realisasi pembelian dan pesanan terbaru</p>
                        </div>
                    </div>
                    <a href="{{ $detail('purchasing') }}" class="detail">Lihat detail <svg class="ico" aria-hidden="true"><use href="#i-arrow"/></svg></a>
                </div>
                <div class="module-body">
                    <div class="mini-row">
                        <div class="mini">
                            <small>Total pembelian</small>
                            <b>{{ $money($canViewSensitive ? data_get($purchasing, 'value') : null) }}</b>
                        </div>
                        <div class="mini">
                            <small>Supplier aktif</small>
                            <b>{{ count(data_get($purchasing, 'suppliers', [])) }}</b>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Supplier</th>
                                    <th>PO</th>
                                    <th class="num">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(array_slice(data_get($purchasing, 'suppliers', []), 0, 2) as $sup)
                                    <tr>
                                        <td>{{ $sup['supplier'] }}</td>
                                        <td>{{ $sup['po_count'] }}</td>
                                        <td class="num">{{ $money($canViewSensitive ? $sup['value'] : null) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="empty" colspan="3">Belum ada pembelian.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="module-foot">{{ count(data_get($purchasing, 'suppliers', [])) }} supplier aktif pada periode terpilih</div>
            </article>

            {{-- 6. Anomali & Keputusan --}}
            <article class="card-dash panel module" id="mod-anomali">
                <div class="panel-head">
                    <div class="panel-title">
                        <span class="sq red"><svg class="ico" aria-hidden="true"><use href="#i-alert"/></svg></span>
                        <div>
                            <h3>Anomali &amp; Keputusan</h3>
                            <p>Temuan dan izin yang perlu persetujuan</p>
                        </div>
                    </div>
                    <a href="{{ $detail('profitability') }}" class="detail">Lihat detail <svg class="ico" aria-hidden="true"><use href="#i-arrow"/></svg></a>
                </div>
                <div class="module-body">
                    <div class="mini-row">
                        <div class="mini">
                            <small>Approval menunggu</small>
                            <b class="{{ ($summary['pending_approval'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $summary['pending_approval'] ?? 0 }}</b>
                        </div>
                        <div class="mini">
                            <small>Anomali terbuka</small>
                            <b class="{{ ($summary['anomaly_open'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $summary['anomaly_open'] ?? 0 }}</b>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Indikator</th>
                                    <th>Kondisi</th>
                                    <th class="num">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Overdue Piutang</td>
                                    <td>{{ $money($overdueAmount) }}</td>
                                    <td class="num"><span class="pill {{ $overdueAmount > 0 ? 'alert' : 'ok' }}">{{ $overdueAmount > 0 ? 'Tindak' : 'Aman' }}</span></td>
                                </tr>
                                <tr>
                                    <td>Stok Kritis</td>
                                    <td>{{ $criticalCount }} produk</td>
                                    <td class="num"><span class="pill {{ $criticalCount > 0 ? 'warn' : 'ok' }}">{{ $criticalCount > 0 ? 'Restok' : 'Aman' }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="module-foot">Pengawasan kepatuhan &amp; mitigasi risiko operasional</div>
            </article>

        </div>
    </section>

    <footer class="site-foot">
        <span>GudangToko · Laporan Owner</span>
        <span>Posisi data aktif per {{ \Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d F Y') }}</span>
    </footer>

</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL RUMUS SKOR KESEHATAN BISNIS (CATATAN 1)
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modalRumusSkorBisnis" tabindex="-1" aria-labelledby="modalRumusSkorTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRumusSkorTitle">
                    <svg class="ico me-2" style="color:#ffcf76"><use href="#i-chart"/></svg>
                    Rumus &amp; Perhitungan Skor Kesehatan Bisnis
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 75vh; overflow-y: auto;">
                <div class="alert alert-primary d-flex align-items-center gap-3 p-3 mb-4 rounded-3">
                    <svg class="ico" style="width:24px;height:24px;flex:none"><use href="#i-info"/></svg>
                    <div style="font-size:12.5px;line-height:1.5">
                        Skor Kesehatan Bisnis adalah metrik agregat berskala <strong>0 – 100</strong> yang mengevaluasi kondisi operasional dan finansial secara otomatis dari data riil transaksi, perputaran kas, dan stok barang.
                    </div>
                </div>

                {{-- Rumus Skor Gabungan --}}
                <div class="formula-card">
                    <h6>
                        <span>Skor Gabungan Keseluruhan (Overall Score)</span>
                        <span class="formula-code">Rata-Rata (Pilar 1 s/d 5)</span>
                    </h6>
                    <p class="formula-desc">
                        Diperoleh dari rata-rata nilai dari 5 pilar kesehatan bisnis aktif: 
                        <br><strong>Kategori Penilaian:</strong> 
                        <span class="badge bg-success text-white ms-1">≥ 80: Sehat</span>
                        <span class="badge bg-warning text-dark ms-1">60 – 79: Perlu Perhatian</span>
                        <span class="badge bg-danger text-white ms-1">&lt; 60: Berisiko</span>
                    </p>
                </div>

                {{-- 5 Cabang Metrik --}}
                <div class="formula-card">
                    <h6>
                        <span>1. Profitabilitas &amp; Margin</span>
                        <span class="formula-code">min(100, Gross Margin % × 5)</span>
                    </h6>
                    <p class="formula-desc">
                        Menilai kemampuan bisnis menghasilkan laba kotor dari omzet penjualan. Rasio gross margin sebesar 20% ke atas akan mendapatkan nilai sempurna <strong>100/100</strong>. Jika margin lebih rendah, skor menurun secara proporsional.
                    </p>
                </div>

                <div class="formula-card">
                    <h6>
                        <span>2. Likuiditas Tagihan</span>
                        <span class="formula-code">100 - (Overdue / Total Piutang × 100)</span>
                    </h6>
                    <p class="formula-desc">
                        Menilai ketepatan waktu pelunasan piutang pelanggan. Jika tidak ada piutang yang menunggak melewati jatuh tempo, skor adalah <strong>100/100</strong>. Skor akan terpotong sesuai persentase tagihan yang mengalami penundaan/macet.
                    </p>
                </div>

                <div class="formula-card">
                    <h6>
                        <span>3. Kesehatan Persediaan</span>
                        <span class="formula-code">100 - (SKU Kritis / Total SKU × 100)</span>
                    </h6>
                    <p class="formula-desc">
                        Mengukur kecukupan stok barang di gudang dan toko. Menghitung persentase produk yang berada di batas aman (di atas safety stock). Semakin sedikit barang yang habis atau kritis, semakin tinggi skornya.
                    </p>
                </div>

                <div class="formula-card">
                    <h6>
                        <span>4. Kelancaran Penagihan</span>
                        <span class="formula-code">100 - (Akun Overdue / Total Akun × 100)</span>
                    </h6>
                    <p class="formula-desc">
                        Menilai rasio debitur pelanggan yang tertib membayar. Menghitung perbandingan antara jumlah pelanggan yang menunggak terhadap seluruh pelanggan yang memiliki riwayat tagihan terbuka.
                    </p>
                </div>

                <div class="formula-card">
                    <h6>
                        <span>5. Kestabilan Operasional</span>
                        <span class="formula-code">max(0, 100 - (Pending + Anomali) × 10)</span>
                    </h6>
                    <p class="formula-desc">
                        Memantau kepatuhan proses administrasi dan mitigasi risiko. Setiap permohonan persetujuan (approval) yang belum direspon atau anomali sistem terbuka (selisih kas, koreksi stok) memberikan penalti 10 poin untuk mendorong respon cepat tim manajemen.
                    </p>
                </div>
            </div>
            <div class="modal-footer p-3 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
