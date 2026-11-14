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
    $scoreTone = match(true) {
        ((int)data_get($health,'overall',0)) >= 80 => 'success',
        ((int)data_get($health,'overall',0)) >= 60 => 'warning',
        default => 'danger',
    };
    $overallScore = (int) data_get($health, 'overall', 0);

    // Definisi 6 Kartu KPI Utama
    $kpis = [
        [
            'label' => 'Total Pendapatan',
            'value' => $money($summary['revenue'] ?? 0),
            'note'  => 'Omzet gabungan Retail & B2B',
            'tone'  => 'green',
            'icon'  => 'ki-chart-line-up',
            'series'=> data_get($report, 'sparklines.revenue', []),
            'money' => true,
        ],
        [
            'label' => 'Gross Margin',
            'value' => $canViewSensitive ? $money($summary['gross_margin'] ?? null) : 'Akses terbatas',
            'note'  => 'Rasio margin: '.$marginLabel,
            'tone'  => 'green',
            'icon'  => 'ki-percentage',
            'series'=> data_get($report, 'sparklines.gross_margin', []),
            'money' => true,
        ],
        [
            'label' => 'Nilai Aset Stok (HPP)',
            'value' => $canViewSensitive ? $money($summary['stock_value'] ?? null) : 'Akses terbatas',
            'note'  => 'HPP aktif: '.qty((string)data_get($inventory,'products',0)).' produk',
            'tone'  => 'blue',
            'icon'  => 'ki-package',
            'series'=> data_get($report, 'sparklines.stock_value', []),
            'money' => true,
        ],
        [
            'label' => 'Piutang Outstanding',
            'value' => $money($summary['receivable_outstanding'] ?? 0),
            'note'  => ($summary['open_accounts'] ?? 0).' tagihan pelanggan aktif',
            'tone'  => 'gold',
            'icon'  => 'ki-bill',
            'series'=> data_get($report, 'sparklines.receivable', []),
            'money' => true,
        ],
        [
            'label' => 'Stok Kritis',
            'value' => qty((string)($summary['critical_stock_count'] ?? 0)),
            'note'  => 'Produk di bawah stok aman',
            'tone'  => 'red',
            'icon'  => 'ki-warning-2',
            'series'=> data_get($report, 'sparklines.critical_stock', []),
            'money' => false,
        ],
        [
            'label' => 'Piutang Overdue',
            'value' => $money($summary['overdue_receivable'] ?? 0),
            'note'  => 'Tagihan melewati jatuh tempo',
            'tone'  => 'red',
            'icon'  => 'ki-time',
            'series'=> data_get($report, 'sparklines.open_accounts', []),
            'money' => true,
        ],
    ];
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<div class="owner-report-suite">

    {{-- ══════════════════════════════════════════════════════════════════════
         EXECUTIVE HERO HEADER
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="eos-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-4">
            <div>
                <div class="eos-eyebrow">
                    <i class="ki-outline ki-crown fs-7"></i>
                    <span>EXECUTIVE BUSINESS REPORT</span>
                </div>
                <h1 class="eos-title">Laporan Owner</h1>
                <p class="eos-subtitle">
                    Pemantauan performa bisnis lintas toko, perputaran stok, margin usaha, dan stabilitas keuangan.
                </p>
            </div>
            <div class="d-flex flex-column align-items-end gap-2">
                <span class="eos-period-badge">
                    <i class="ki-outline ki-calendar fs-6"></i>
                    {{ $periodLabel }}
                </span>
                @if($canExport)
                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'overview']) }}" class="d-flex gap-2">
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

    {{-- ══════════════════════════════════════════════════════════════════════
         FILTER & NAVIGASI LAPORAN OWNER
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="eos-filter-card">
        <div class="eos-nav-tabs">
            @foreach($profile['reports'] as $key => $item)
                <a href="{{ route('reports.profiles.owner.show', $params($key)) }}"
                   @class(['btn btn-sm', 'btn-primary' => $key === $reportKey, 'btn-light' => $key !== $reportKey])>
                    {{ $item['label'] }}
                </a>
            @endforeach
            <a href="{{ route('reports.profiles.owner.show', ['report' => 'overview']) }}" class="btn btn-sm btn-light-primary ms-auto">
                <i class="ki-outline ki-arrows-circle fs-6"></i>Perbarui Data
            </a>
        </div>
        @include('reports.profiles.partials.filter')
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         6 KARTU KPI UTAMA DENGAN SPARKLINE
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="eos-kpi-grid">
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
                $sparkPoints = collect($seriesNumbers)->map(function(float $pt, int $ptIdx) use ($seriesCount, $seriesMin, $seriesRange): string {
                    $x = round(6 + (($ptIdx / $seriesCount) * 108), 1);
                    $y = round(38 - ((($pt - $seriesMin) / $seriesRange) * 28), 1);
                    return $x.','.$y;
                })->all();
                $sparkArea = 'M '.implode(' L ', $sparkPoints).' L 114 44 L 6 44 Z';
            @endphp
            <div class="eos-kpi-card tone-{{ $kpi['tone'] }}">
                <div>
                    <div class="eos-kpi-header">
                        <span class="eos-kpi-label">{{ $kpi['label'] }}</span>
                        <div class="eos-kpi-icon bg-light-{{ $kpi['tone'] }} text-{{ $kpi['tone'] }}">
                            <i class="ki-outline {{ $kpi['icon'] }}"></i>
                        </div>
                    </div>
                    <div class="eos-kpi-value" title="{{ $kpi['value'] }}">{{ $kpi['value'] }}</div>
                    <div class="eos-kpi-note">{{ $kpi['note'] }}</div>
                </div>

                <svg class="eos-kpi-chart {{ $kpi['tone'] }}" viewBox="0 0 120 44" data-owner-kpi-chart aria-label="Grafik {{ $kpi['label'] }}">
                    <defs>
                        <linearGradient id="eosSparkGrad{{ $index }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="currentColor" stop-opacity="0.30"/>
                            <stop offset="100%" stop-color="currentColor" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <path class="area" d="{{ $sparkArea }}" fill="url(#eosSparkGrad{{ $index }})"/>
                    <polyline points="{{ implode(' ', $sparkPoints) }}"/>
                </svg>
            </div>
        @endforeach
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         RINGKASAN EKSEKUTIF & SKOR KESEHATAN BISNIS
         ══════════════════════════════════════════════ --}}
    <div class="row g-5 mb-6">
        {{-- Ringkasan Naratif --}}
        <div class="col-lg-5">
            <div class="eos-briefing-box">
                <h3>
                    <i class="ki-outline ki-document text-warning"></i>
                    Ringkasan Eksekutif
                </h3>
                <p>
                    Total omzet penjualan usaha pada periode aktif tercatat sebesar <strong>{{ $money($summary['revenue'] ?? 0) }}</strong>@if($canViewSensitive) dengan perolehan gross margin <strong>{{ $marginLabel }}</strong> dan nilai modal persediaan <strong>{{ $money($summary['stock_value'] ?? null) }}</strong>@endif.
                    Kewajiban piutang dari pelanggan saat ini berjumlah <strong>{{ $money($summary['receivable_outstanding'] ?? 0) }}</strong>.
                    Prioritas pengawasan: {{ ($summary['critical_stock_count'] ?? 0) > 0 ? 'segera lakukan restock untuk '.$summary['critical_stock_count'].' barang kritis' : 'pertahankan kestabilan persediaan barang' }} dan kawal penagihan piutang pelanggan.
                </p>

                <div class="eos-briefing-stats">
                    <div>
                        <div class="eos-briefing-stat-label">Total Retur Penjualan</div>
                        <div class="eos-briefing-stat-val">{{ $money($summary['returns_value'] ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="eos-briefing-stat-label">Selisih Kas Kasir</div>
                        <div class="eos-briefing-stat-val">{{ $money($summary['cash_difference'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Skor Kesehatan Bisnis --}}
        <div class="col-lg-7">
            <div class="eos-card h-100 mb-0">
                <div class="eos-card-header">
                    <h2 class="eos-card-title">
                        <i class="ki-outline ki-heart-circle text-success fs-3"></i>
                        Skor Kesehatan Bisnis
                    </h2>
                    <span class="badge badge-light-{{ $scoreTone }} fs-8 fw-bold">
                        {{ $scoreLabel }}
                    </span>
                </div>
                <div class="eos-card-body">
                    <div class="eos-health-gauge">
                        <div class="eos-gauge-circle" style="--score:{{ $overallScore * 1.8 }}deg">
                            <div class="eos-gauge-score">{{ $overallScore }}</div>
                            <small class="text-muted fs-9">/ 100</small>
                        </div>

                        <div class="eos-health-metric-row">
                            @foreach([
                                'profitability' => 'Profitabilitas & Margin',
                                'liquidity'     => 'Likuiditas Tagihan',
                                'inventory'     => 'Kesehatan Persediaan',
                                'collection'    => 'Kelancaran Penagihan',
                                'operations'    => 'Kestabilan Operasional',
                            ] as $key => $lbl)
                                @php
                                    $val = data_get($health, $key);
                                    $scoreNum = $val === null ? 0 : (int)$val;
                                    $color = $scoreNum >= 80 ? '#10b981' : ($scoreNum >= 60 ? '#f59e0b' : '#ef4444');
                                @endphp
                                <div class="eos-health-item">
                                    <span class="text-gray-700 fw-semibold">{{ $lbl }}</span>
                                    <div class="eos-health-bar">
                                        <div class="eos-health-fill" style="width:{{ $scoreNum }}%;background:{{ $color }}"></div>
                                    </div>
                                    <span class="fw-bold text-end">{{ $val === null ? '—' : $val }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         PENDAPATAN & CHANNEL MIX vs KINERJA LOKASI
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-5 mb-6">
        {{-- Pendapatan & Channel Mix --}}
        <div class="col-lg-6">
            <div class="eos-card h-100 mb-0">
                <div class="eos-card-header">
                    <h3 class="eos-card-title">
                        <i class="ki-outline ki-chart-simple-2 text-primary fs-3"></i>
                        Pendapatan &amp; Channel Mix
                    </h3>
                    <a href="{{ $detail('income') }}" class="btn btn-sm btn-light-primary py-1 px-3 fs-8">
                        Rincian Pendapatan →
                    </a>
                </div>
                <div class="eos-card-body">
                    <div class="mb-4">
                        <div class="text-muted fs-8 fw-bold text-uppercase mb-2">Komposisi Channel Penjualan</div>
                        @foreach($channelMix as $ch)
                            @php
                                $cVal = (float) data_get($ch, 'value', 0);
                                $cPct = $channelTotal > 0 ? ($cVal / $channelTotal * 100) : 0;
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between fs-8 fw-semibold mb-1">
                                    <span>{{ data_get($ch, 'label', '-') }}</span>
                                    <span class="fw-bold text-dark">{{ $money($cVal) }} ({{ number_format($cPct, 1) }}%)</span>
                                </div>
                                <div class="progress h-6px bg-light">
                                    <div class="progress-bar bg-success" style="width: {{ $cPct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="text-muted fs-8 fw-bold text-uppercase mb-2">Tren Pendapatan ({{ ucfirst((string)$filters['range']) }})</div>
                    @if($revenueChart->isEmpty())
                        <div class="text-center text-muted py-5 border border-dashed rounded bg-light">
                            Belum ada rekaman penjualan pada periode ini.
                        </div>
                    @else
                        @php
                            $rc = max(1, $revenueChart->count() - 1);
                            $chartPoints = [];
                            foreach ($revenueChart as $i => $row) {
                                $v = (float) data_get($row, 'value', data_get($row, 'revenue', data_get($row, 'total', 0)));
                                $x = round(10 + ($i / $rc) * 230, 1);
                                $y = round(80 - (($v / $revenueMax) * 65), 1);
                                $chartPoints[] = "$x,$y";
                            }
                        @endphp
                        <svg class="eos-revenue-chart-svg" viewBox="0 0 250 90">
                            <polyline points="{{ implode(' ', $chartPoints) }}"/>
                            @foreach($revenueChart as $i => $row)
                                @php
                                    $v = (float) data_get($row, 'value', data_get($row, 'revenue', data_get($row, 'total', 0)));
                                    $x = round(10 + ($i / $rc) * 230, 1);
                                    $y = round(80 - (($v / $revenueMax) * 65), 1);
                                    $dateLbl = (string) data_get($row, 'date', data_get($row, 'label', '-'));
                                @endphp
                                <circle cx="{{ $x }}" cy="{{ $y }}" r="4">
                                    <title>{{ $dateLbl }}: {{ $money($v) }}</title>
                                </circle>
                            @endforeach
                        </svg>
                    @endif
                </div>
            </div>
        </div>

        {{-- Perbandingan Lokasi Cabang --}}
        <div class="col-lg-6">
            <div class="eos-card h-100 mb-0">
                <div class="eos-card-header">
                    <h3 class="eos-card-title">
                        <i class="ki-outline ki-geolocation text-info fs-3"></i>
                        Perbandingan Lokasi &amp; Cabang
                    </h3>
                    <a href="{{ $detail('locations') }}" class="btn btn-sm btn-light-info py-1 px-3 fs-8">
                        Semua Lokasi →
                    </a>
                </div>
                <div class="eos-card-body p-0">
                    <div class="table-responsive">
                        <table class="eos-table">
                            <thead>
                                <tr>
                                    <th>Nama Cabang / Toko</th>
                                    <th>Penjualan</th>
                                    @if($canViewSensitive)<th>Nilai Stok</th>@endif
                                    <th class="text-center">Stok Kritis</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(array_slice($locations, 0, 5) as $loc)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-gray-800">{{ $loc['name'] }}</div>
                                        </td>
                                        <td class="fw-bold text-success">{{ $money($loc['revenue']) }}</td>
                                        @if($canViewSensitive)
                                            <td>{{ $money($loc['stock_value'] ?? null) }}</td>
                                        @endif
                                        <td class="text-center">
                                            @if(($loc['critical_count'] ?? 0) > 0)
                                                <span class="badge badge-light-danger fs-8 fw-bold">{{ $loc['critical_count'] }}</span>
                                            @else
                                                <span class="badge badge-light-success fs-8">Aman</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $canViewSensitive ? 4 : 3 }}" class="text-center py-4 text-muted">
                                            Belum ada lokasi aktif yang terdaftar.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         PIUTANG & AGING vs STOK & RISIKO
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-5 mb-6">
        {{-- Piutang Usaha & Aging --}}
        <div class="col-lg-6">
            <div class="eos-card h-100 mb-0">
                <div class="eos-card-header">
                    <h3 class="eos-card-title">
                        <i class="ki-outline ki-bill text-warning fs-3"></i>
                        Piutang &amp; Aging Tagihan
                    </h3>
                    <a href="{{ $detail('receivables') }}" class="btn btn-sm btn-light-warning py-1 px-3 fs-8">
                        Kelola Piutang →
                    </a>
                </div>
                <div class="eos-card-body">
                    <div class="d-flex justify-content-between p-3 rounded bg-light mb-4">
                        <div>
                            <span class="text-muted fs-8 d-block fw-bold">TOTAL PIUTANG</span>
                            <span class="fs-6 fw-bolder text-gray-900">{{ $money($summary['receivable_outstanding'] ?? 0) }}</span>
                        </div>
                        <div class="text-end">
                            <span class="text-muted fs-8 d-block fw-bold">TAGIHAN OVERDUE</span>
                            <span class="fs-6 fw-bolder text-danger">{{ $money($summary['overdue_receivable'] ?? 0) }}</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="eos-table">
                            <thead>
                                <tr>
                                    <th>Pelanggan</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Sisa Tagihan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(array_slice($receivables, 0, 4) as $rcv)
                                    <tr>
                                        <td class="fw-bold text-gray-800">{{ $rcv['customer'] }}</td>
                                        <td>
                                            @php
                                                $dueDate = \Carbon\Carbon::parse($rcv['due_date']);
                                                $isLate = $dueDate->isPast();
                                            @endphp
                                            <span @class(['badge fs-9', 'badge-light-danger' => $isLate, 'badge-light-primary' => !$isLate])>
                                                {{ $dueDate->translatedFormat('d M Y') }}
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark">{{ $money($rcv['outstanding']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            <i class="ki-outline ki-check-circle text-success fs-4 me-1"></i>
                                            Tidak ada piutang aktif saat ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stok & Risiko Persediaan --}}
        <div class="col-lg-6">
            <div class="eos-card h-100 mb-0">
                <div class="eos-card-header">
                    <h3 class="eos-card-title">
                        <i class="ki-outline ki-package text-danger fs-3"></i>
                        Stok &amp; Risiko Persediaan
                    </h3>
                    <a href="{{ $detail('inventory') }}" class="btn btn-sm btn-light-danger py-1 px-3 fs-8">
                        Katalog Stok →
                    </a>
                </div>
                <div class="eos-card-body">
                    <div class="d-flex justify-content-between p-3 rounded bg-light mb-4">
                        <div>
                            <span class="text-muted fs-8 d-block fw-bold">TOTAL STOK TERSEDIA</span>
                            <span class="fs-6 fw-bolder text-gray-900">{{ qty((string)data_get($inventory, 'available', 0)) }} unit</span>
                        </div>
                        <div class="text-end">
                            <span class="text-muted fs-8 d-block fw-bold">PRODUK KRITIS</span>
                            <span class="fs-6 fw-bolder text-danger">{{ $summary['critical_stock_count'] ?? 0 }} SKU</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="eos-table">
                            <thead>
                                <tr>
                                    <th>Nama Produk</th>
                                    <th>SKU</th>
                                    <th>Stok Sisa</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(data_get($inventory, 'alerts', []) as $alert)
                                    <tr>
                                        <td class="fw-bold text-gray-800">{{ $alert['product'] }}</td>
                                        <td><code class="fs-9">{{ $alert['sku'] }}</code></td>
                                        <td>
                                            <span class="badge badge-light-danger fw-bold fs-9">
                                                {{ qty((string)$alert['available']) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            <i class="ki-outline ki-check-circle text-success fs-4 me-1"></i>
                                            Seluruh stok barang dalam batas aman.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         PENGADAAN & SUPPLIER vs ANOMALI & KEPUTUSAN
         ══════════════════════════════════════════════════════════════════════ --}}
    <div class="row g-5">
        {{-- Pengadaan & Supplier --}}
        <div class="col-lg-6">
            <div class="eos-card h-100 mb-0">
                <div class="eos-card-header">
                    <h3 class="eos-card-title">
                        <i class="ki-outline ki-truck text-dark fs-3"></i>
                        Pengadaan &amp; Supplier
                    </h3>
                    <a href="{{ $detail('purchasing') }}" class="btn btn-sm btn-light py-1 px-3 fs-8">
                        Lihat PO →
                    </a>
                </div>
                <div class="eos-card-body">
                    <div class="d-flex justify-content-between p-3 rounded bg-light mb-4">
                        <div>
                            <span class="text-muted fs-8 d-block fw-bold">TOTAL BELANJA PO</span>
                            <span class="fs-6 fw-bolder text-gray-900">{{ $money($canViewSensitive ? data_get($purchasing, 'value') : null) }}</span>
                        </div>
                        <div class="text-end">
                            <span class="text-muted fs-8 d-block fw-bold">SUPPLIER AKTIF</span>
                            <span class="fs-6 fw-bolder text-primary">{{ count(data_get($purchasing, 'suppliers', [])) }} Mitra</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="eos-table">
                            <thead>
                                <tr>
                                    <th>Supplier</th>
                                    <th>Jumlah PO</th>
                                    <th>Nilai Belanja</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(data_get($purchasing, 'suppliers', []) as $sup)
                                    <tr>
                                        <td class="fw-bold text-gray-800">{{ $sup['supplier'] }}</td>
                                        <td>{{ $sup['po_count'] }} order</td>
                                        <td class="fw-bold text-dark">{{ $money($canViewSensitive ? $sup['value'] : null) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            Belum ada pengadaan barang pada periode ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Anomali & Keputusan --}}
        <div class="col-lg-6">
            <div class="eos-card h-100 mb-0">
                <div class="eos-card-header">
                    <h3 class="eos-card-title">
                        <i class="ki-outline ki-shield-search text-primary fs-3"></i>
                        Anomali &amp; Keputusan
                    </h3>
                </div>
                <div class="eos-card-body">
                    <div class="d-flex flex-column gap-3">
                        {{-- Overdue --}}
                        <div @class(['eos-anomaly-card', 'alert-danger' => ($summary['overdue_receivable'] ?? 0) > 0, 'alert-success' => ($summary['overdue_receivable'] ?? 0) <= 0])>
                            <i class="ki-outline {{ ($summary['overdue_receivable'] ?? 0) > 0 ? 'ki-information-2 fs-2' : 'ki-check-circle fs-2' }}"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-7">Piutang Melewati Jatuh Tempo</div>
                                <div class="fs-8">
                                    @if(($summary['overdue_receivable'] ?? 0) > 0)
                                        Outstanding {{ $money($summary['overdue_receivable'] ?? 0) }} memerlukan penagihan intensif.
                                    @else
                                        Tidak ada piutang overdue. Arus kas pelanggan lancar.
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Stok Kritis --}}
                        <div @class(['eos-anomaly-card', 'alert-warning' => ($summary['critical_stock_count'] ?? 0) > 0, 'alert-success' => ($summary['critical_stock_count'] ?? 0) <= 0])>
                            <i class="ki-outline {{ ($summary['critical_stock_count'] ?? 0) > 0 ? 'ki-warning fs-2' : 'ki-check-circle fs-2' }}"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-7">Peringatan Stok Kritis</div>
                                <div class="fs-8">
                                    @if(($summary['critical_stock_count'] ?? 0) > 0)
                                        {{ $summary['critical_stock_count'] }} barang berada di bawah batas minimum persediaan.
                                    @else
                                        Persediaan mencukupi. Tidak ada produk kritis.
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Approval Menunggu --}}
                        <div @class(['eos-anomaly-card', 'alert-warning' => ($summary['pending_approval'] ?? 0) > 0, 'alert-success' => ($summary['pending_approval'] ?? 0) <= 0])>
                            <i class="ki-outline {{ ($summary['pending_approval'] ?? 0) > 0 ? 'ki-time fs-2' : 'ki-check-circle fs-2' }}"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-7">Persetujuan Transaksi Menunggu</div>
                                <div class="fs-8">
                                    @if(($summary['pending_approval'] ?? 0) > 0)
                                        Ada {{ $summary['pending_approval'] }} pengajuan menunggu keputusan owner.
                                    @else
                                        Semua pengajuan telah diproses tuntas.
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Anomali Terbuka --}}
                        <div @class(['eos-anomaly-card', 'alert-warning' => ($summary['anomaly_open'] ?? 0) > 0, 'alert-success' => ($summary['anomaly_open'] ?? 0) <= 0])>
                            <i class="ki-outline {{ ($summary['anomaly_open'] ?? 0) > 0 ? 'ki-shield-cross fs-2' : 'ki-check-circle fs-2' }}"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-7">Deteksi Anomali Operasional</div>
                                <div class="fs-8">
                                    @if(($summary['anomaly_open'] ?? 0) > 0)
                                        {{ $summary['anomaly_open'] }} anomali sistem aktif terdeteksi dan butuh audit.
                                    @else
                                        Sistem berjalan aman tanpa anomali terbuka.
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
