@php
    use App\Support\CurrencyFormatter;

    $summary               = $report['summary'] ?? [];
    $topProfitableProducts = $report['top_profitable_products'] ?? [];
    $lowMarginProducts     = $report['low_margin_products'] ?? [];
    $priceHistoryRows      = $report['rows'] ?? [];
    $locationList          = $workLocations ?? $locations ?? [];

    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y')
                 .' – '
                 .\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');

    $money = fn($v): string => $v === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string)$v);
    $fmtPercent = fn($v): string => $v === null ? '0%' : str_replace('.', ',', (string)$v).'%';

    $totalRevenue    = (float) ($summary['total_revenue'] ?? 0);
    $totalMargin     = (float) ($summary['total_margin'] ?? 0);
    $posRevenue      = (float) ($summary['pos_revenue'] ?? 0);
    $posMargin       = (float) ($summary['pos_margin'] ?? 0);
    $b2bRevenue      = (float) ($summary['b2b_revenue'] ?? 0);
    $avgMarginPct    = (float) ($summary['average_margin'] ?? 0);
    $priceChanges    = (int) ($summary['price_changes'] ?? 0);
    $pendingApprovals= (int) ($summary['pending_approvals'] ?? 0);
    $anomaliesCount  = (int) ($summary['sensitive_anomalies'] ?? 0);

    $posMarginPct = $posRevenue > 0 ? round(($posMargin / $posRevenue) * 100, 1) : 0;
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<style>
/* ══════════════════════════════════════════════════════════════════════════
   CUSTOM STYLES FOR EXECUTIVE PROFITABILITY & PRICING
   ══════════════════════════════════════════════════════════════════════════ */
.ep-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
}
.ep-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 12px 16px;
    border-bottom: 1px solid var(--line);
}
.ep-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #edf2f7;
    color: var(--dark);
    vertical-align: middle;
}
.ep-table tr:hover td {
    background: #fbfcfe;
}
.channel-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    transition: all .2s ease;
}
.channel-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(16, 42, 75, 0.05);
}
.margin-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 11.5px;
}
.margin-pill.high {
    background: #e6f4ea;
    color: #137333;
    border: 1px solid #ceead6;
}
.margin-pill.medium {
    background: #fef7e0;
    color: #b06000;
    border: 1px solid #feefc3;
}
.margin-pill.low {
    background: #fce8e6;
    color: #c5221f;
    border: 1px solid #fad2cf;
}
</style>

<div class="gudang-dashboard-wrap">

    {{-- HERO SECTION --}}
    <header class="hero" aria-labelledby="title">
        <svg class="hero-decor" viewBox="0 0 460 210" aria-hidden="true">
            <defs>
                <pattern id="profit-grid" width="22" height="22" patternUnits="userSpaceOnUse"><path d="M22 0H0v22" stroke="#b6cce8" stroke-opacity=".12"/></pattern>
            </defs>
            <rect width="460" height="210" fill="url(#profit-grid)"/>
        </svg>

        <div>
            <div class="eyebrow">
                <i class="ki-outline ki-percentage fs-6 text-warning me-1"></i>
                <span>EXECUTIVE PRICING &amp; PROFITABILITY</span>
            </div>
            <h1 id="title">Profitabilitas &amp; Analisis Margin</h1>
            <p>Pengawasan Laba Kotor, Rasio Margin Kanal Penjualan, Deteksi Margin Kritis, dan Tata Kelola Perubahan Harga.</p>

            {{-- 8-MODULE EXECUTIVE NAVIGATION PILLS --}}
            <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'overview', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-home-2 fs-7 me-1"></i> Ringkasan
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'income', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-chart-line-up fs-7 me-1"></i> Laba Rugi
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'profitability', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash gold py-1 px-3" style="min-height:30px; font-size:11px;">
                    <i class="ki-outline ki-percentage fs-7 me-1"></i> Margin
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'inventory', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-cube-2 fs-7 me-1"></i> Stok
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'purchasing', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-truck fs-7 me-1"></i> Pembelian
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'receivables', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-wallet fs-7 me-1"></i> Piutang
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'locations', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-shop fs-7 me-1"></i> Cabang
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'tax', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-shield-tick fs-7 me-1"></i> Pajak
                </a>
            </div>
        </div>

        <div class="hero-actions">
            <div class="period-chip">
                <i class="ki-outline ki-calendar fs-6 me-1"></i>
                <span>{{ $periodLabel }}</span>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <button type="button" class="btn-dash clear" onclick="window.print()">
                    <i class="ki-outline ki-printer fs-6 me-1"></i>
                    <span>Cetak A4</span>
                </button>

                @if($canExport)
                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'profitability']) }}" class="d-inline-flex gap-2">
                        @csrf
                        @foreach(request()->except(['format','_token']) as $n => $v)
                            @if(is_scalar($v))<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="hidden" name="format" value="pdf">
                        <button type="submit" class="btn-dash green">
                            <i class="ki-outline ki-file-down fs-6 me-1"></i>
                            <span>PDF</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'profitability']) }}" class="d-inline-flex">
                        @csrf
                        @foreach(request()->except(['format','_token']) as $n => $v)
                            @if(is_scalar($v))<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="hidden" name="format" value="csv">
                        <button type="submit" class="btn-dash clear">
                            <i class="ki-outline ki-file-sheet fs-6 me-1"></i>
                            <span>Excel/CSV</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    {{-- FILTER BAR --}}
    <form method="GET" action="{{ route('reports.profiles.owner.show', ['report' => 'profitability']) }}" class="filter-card">
        <input type="hidden" name="range" value="{{ $filters['range'] ?? 'monthly' }}">
        
        <div class="d-flex align-items-center gap-2">
            <i class="ki-outline ki-filter-search fs-4 text-primary"></i>
            <span class="fw-bold text-gray-800 fs-7 text-uppercase letter-spacing-1">Filter Data</span>
        </div>

        <div class="filter-field">
            <label for="filter-start-date">Tanggal Mulai</label>
            <input type="date" id="filter-start-date" name="start_date" value="{{ $filters['start_date'] }}" class="filter-input">
        </div>

        <div class="filter-field">
            <label for="filter-end-date">Tanggal Akhir</label>
            <input type="date" id="filter-end-date" name="end_date" value="{{ $filters['end_date'] }}" class="filter-input">
        </div>

        <div class="filter-field">
            <label for="filter-location">Cabang / Unit Toko</label>
            <select id="filter-location" name="work_location_id" class="filter-input">
                <option value="">Semua Cabang Toko</option>
                @foreach($locationList as $loc)
                    <option value="{{ $loc->id }}" @selected(($selectedLocation?->id ?? null) === $loc->id)>
                        {{ $loc->name }} ({{ strtoupper($loc->type ?? 'Branch') }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn-dash gold" style="color:var(--dark); font-weight:700;">
                <i class="ki-outline ki-check fs-6 me-1"></i> Terapkan
            </button>
            <a href="{{ route('reports.profiles.owner.show', ['report' => 'profitability']) }}" class="btn-dash clear" title="Reset Filter">
                <i class="ki-outline ki-arrows-circle fs-6"></i>
            </a>
        </div>
    </form>

    {{-- 4 EXECUTIVE KPI CARDS --}}
    <section class="kpi-grid" aria-label="Key Performance Indicators">
        
        {{-- CARD 1: TOTAL LABA KOTOR --}}
        <article class="kpi-card" style="border-top: 3px solid #078455;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="kpi-label">TOTAL LABA KOTOR (PENJUALAN)</span>
                <div class="badge badge-light-success p-2">
                    <i class="ki-outline ki-wallet fs-4 text-success"></i>
                </div>
            </div>
            <div class="kpi-value text-success" style="font-size: 1.65rem;">
                {{ $money($totalMargin) }}
            </div>
            <div class="kpi-sub">
                Margin kotor dari transaksi penjualan POS
            </div>
            <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top border-gray-100 fs-8 text-muted">
                <i class="ki-outline ki-information-2 text-primary fs-7"></i>
                <span>Akumulasi selisih harga jual terhadap HPP riil</span>
            </div>
        </article>

        {{-- CARD 2: RATA-RATA MARGIN % --}}
        <article class="kpi-card" style="border-top: 3px solid #f39c12;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="kpi-label">RATA-RATA MARGIN</span>
                <div class="badge badge-light-warning p-2">
                    <i class="ki-outline ki-percentage fs-4 text-warning"></i>
                </div>
            </div>
            <div class="kpi-value text-warning" style="font-size: 1.65rem;">
                {{ $fmtPercent($avgMarginPct) }}
            </div>
            <div class="kpi-sub">
                Rasio profitabilitas kotor ritel
            </div>
            <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top border-gray-100 fs-8 text-muted">
                <i class="ki-outline ki-chart-simple text-warning fs-7"></i>
                <span>Target ideal operasional retail: 20% – 35%</span>
            </div>
        </article>

        {{-- CARD 3: TOTAL OMZET PENJUALAN --}}
        <article class="kpi-card" style="border-top: 3px solid #102a4b;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="kpi-label">TOTAL OMZET PENJUALAN</span>
                <div class="badge badge-light-primary p-2">
                    <i class="ki-outline ki-shop fs-4 text-primary"></i>
                </div>
            </div>
            <div class="kpi-value text-primary" style="font-size: 1.65rem;">
                {{ $money($totalRevenue) }}
            </div>
            <div class="kpi-sub">
                Kasir POS: {{ $money($posRevenue) }}
            </div>
            <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top border-gray-100 fs-8 text-muted">
                <span>Grosir/B2B: {{ $money($b2bRevenue) }}</span>
                <span class="badge badge-light-info fw-bold">{{ $summary['pos_transactions_count'] ?? '' }}</span>
            </div>
        </article>

        {{-- CARD 4: TATA KELOLA & APPROVAL HARGA --}}
        <article class="kpi-card" style="border-top: 3px solid #b44136;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="kpi-label">TATA KELOLA HARGA</span>
                <div class="badge badge-light-danger p-2">
                    <i class="ki-outline ki-security-user fs-4 text-danger"></i>
                </div>
            </div>
            <div class="kpi-value text-dark" style="font-size: 1.65rem;">
                {{ number_format($priceChanges, 0, ',', '.') }}
                <span class="fs-7 fw-normal text-muted">Penyesuaian</span>
            </div>
            <div class="kpi-sub">
                Aktivitas penyesuaian harga jual
            </div>
            <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top border-gray-100 fs-8">
                @if($pendingApprovals > 0)
                    <span class="badge badge-light-warning fw-bold">
                        <i class="ki-outline ki-time text-warning me-1"></i> {{ $pendingApprovals }} Menunggu Approval
                    </span>
                @else
                    <span class="text-success fw-bold">
                        <i class="ki-outline ki-check-circle text-success me-1"></i> Tidak ada antrean
                    </span>
                @endif

                @if($anomaliesCount > 0)
                    <span class="badge badge-light-danger fw-bold">
                        {{ $anomaliesCount }} Anomali
                    </span>
                @endif
            </div>
        </article>

    </section>

    {{-- SECTION: KOMPARASI KANAL PENJUALAN --}}
    <section class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h2 class="fs-4 fw-bold text-gray-900 mb-0">Komparasi Profitabilitas Kanal Penjualan</h2>
                <div class="fs-7 text-muted">Rasio perolehan margin kotor antara penjualan Kasir (Retail POS) dan Pesanan Grosir (B2B)</div>
            </div>
        </div>

        <div class="row g-4">
            {{-- KANAL 1: RETAIL POS --}}
            <div class="col-md-6">
                <div class="channel-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="badge badge-light-success p-3 rounded-circle">
                                <i class="ki-outline ki-calculator fs-2 text-success"></i>
                            </div>
                            <div>
                                <h3 class="fs-5 fw-bold text-gray-900 mb-0">Penjualan Kasir (POS Ritel)</h3>
                                <div class="fs-7 text-muted">Transaksi langsung di gerai toko</div>
                            </div>
                        </div>
                        <span class="margin-pill {{ $posMarginPct >= 20 ? 'high' : ($posMarginPct >= 10 ? 'medium' : 'low') }}">
                            Margin: {{ $fmtPercent($posMarginPct) }}
                        </span>
                    </div>

                    <div class="row g-3 pt-3 border-top border-gray-100">
                        <div class="col-6">
                            <div class="fs-8 text-uppercase fw-semibold text-muted">Total Omzet Kasir</div>
                            <div class="fs-5 fw-bold text-gray-900 mt-1">{{ $money($posRevenue) }}</div>
                        </div>
                        <div class="col-6">
                            <div class="fs-8 text-uppercase fw-semibold text-muted">Total Laba Kotor Kasir</div>
                            <div class="fs-5 fw-bold text-success mt-1">{{ $money($posMargin) }}</div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="d-flex justify-content-between fs-8 text-muted mb-1">
                            <span>Efisiensi Margin Kasir</span>
                            <span class="fw-bold text-dark">{{ $fmtPercent($posMarginPct) }}</span>
                        </div>
                        <div class="progress h-6px bg-light-success">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ min(100, max(0, $posMarginPct)) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KANAL 2: B2B GROSIR --}}
            <div class="col-md-6">
                <div class="channel-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="badge badge-light-primary p-3 rounded-circle">
                                <i class="ki-outline ki-cube-3 fs-2 text-primary"></i>
                            </div>
                            <div>
                                <h3 class="fs-5 fw-bold text-gray-900 mb-0">Penjualan Grosir (B2B)</h3>
                                <div class="fs-7 text-muted">Pesanan langganan partai besar</div>
                            </div>
                        </div>
                        <span class="badge badge-light-info fw-bold py-2 px-3">
                            Katalog Khusus &amp; Termin
                        </span>
                    </div>

                    <div class="row g-3 pt-3 border-top border-gray-100">
                        <div class="col-6">
                            <div class="fs-8 text-uppercase fw-semibold text-muted">Total Omzet Grosir</div>
                            <div class="fs-5 fw-bold text-gray-900 mt-1">{{ $money($b2bRevenue) }}</div>
                        </div>
                        <div class="col-6">
                            <div class="fs-8 text-uppercase fw-semibold text-muted">Kontribusi Omzet</div>
                            <div class="fs-5 fw-bold text-primary mt-1">
                                {{ $totalRevenue > 0 ? round(($b2bRevenue / $totalRevenue) * 100, 1) : 0 }}%
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="d-flex justify-content-between fs-8 text-muted mb-1">
                            <span>Pangsa Belanja B2B terhadap Total</span>
                            <span class="fw-bold text-dark">{{ $totalRevenue > 0 ? round(($b2bRevenue / $totalRevenue) * 100, 1) : 0 }}%</span>
                        </div>
                        <div class="progress h-6px bg-light-primary">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, max(0, ($totalRevenue > 0 ? ($b2bRevenue / $totalRevenue) * 100 : 0))) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- DUA KOLOM: TOP PRODUK PROFITABEL & PERINGATAN MARGIN TIPIS --}}
    <div class="row g-4 mb-5">
        
        {{-- KOLOM 1: TOP 10 PRODUK PALING MENGUNTUNGKAN --}}
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white py-4 border-bottom border-gray-200">
                    <div class="d-flex align-items-center justify-content-between w-100">
                        <div class="d-flex align-items-center gap-2">
                            <div class="badge badge-light-success p-2">
                                <i class="ki-outline ki-crown-2 fs-3 text-success"></i>
                            </div>
                            <div>
                                <h2 class="card-title fs-5 fw-bold text-gray-900 mb-0">Top 10 Produk Paling Menguntungkan</h2>
                                <div class="fs-8 text-muted">Penyumbang nominal laba kotor terbesar di ritel</div>
                            </div>
                        </div>
                        <span class="badge badge-light-success fw-bold">High Profit</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="ep-table">
                            <thead>
                                <tr>
                                    <th>Produk &amp; SKU</th>
                                    <th class="text-center">Qty Terjual</th>
                                    <th class="text-end">Total Omzet</th>
                                    <th class="text-end">Laba Kotor</th>
                                    <th class="text-center">Margin %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topProfitableProducts as $prod)
                                    @php
                                        $marginPct = round((float) ($prod['avg_margin_pct'] ?? 0), 1);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-gray-900">{{ $prod['product_name'] }}</div>
                                            <div class="text-muted fs-8 font-monospace">{{ $prod['sku'] }}</div>
                                        </td>
                                        <td class="text-center fw-semibold text-gray-700">
                                            {{ number_format((float)$prod['total_sold_qty'], 0, ',', '.') }}
                                        </td>
                                        <td class="text-end text-muted">
                                            {{ $money($prod['total_revenue']) }}
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            {{ $money($prod['total_margin']) }}
                                        </td>
                                        <td class="text-center">
                                            <span class="margin-pill {{ $marginPct >= 25 ? 'high' : ($marginPct >= 15 ? 'medium' : 'low') }}">
                                                {{ $fmtPercent($marginPct) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="ki-outline ki-information-2 fs-2 d-block mb-2 text-muted"></i>
                                            Belum ada transaksi penjualan pada periode ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- KOLOM 2: PERINGATAN PRODUK MARGIN TIPIS / BERISIKO --}}
        <div class="col-xl-6">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-white py-4 border-bottom border-gray-200">
                    <div class="d-flex align-items-center justify-content-between w-100">
                        <div class="d-flex align-items-center gap-2">
                            <div class="badge badge-light-danger p-2">
                                <i class="ki-outline ki-shield-cross fs-3 text-danger"></i>
                            </div>
                            <div>
                                <h2 class="card-title fs-5 fw-bold text-gray-900 mb-0">Peringatan Margin Tipis (&lt; 15%)</h2>
                                <div class="fs-8 text-muted">Produk dengan keuntungan minim atau rentan tergerus inflasi HPP</div>
                            </div>
                        </div>
                        <span class="badge badge-light-danger fw-bold">Risiko Margin</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="ep-table">
                            <thead>
                                <tr>
                                    <th>Produk &amp; SKU</th>
                                    <th class="text-center">Qty Terjual</th>
                                    <th class="text-end">Total Omzet</th>
                                    <th class="text-end">Laba Kotor</th>
                                    <th class="text-center">Margin %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lowMarginProducts as $prod)
                                    @php
                                        $marginPct = round((float) ($prod['avg_margin_pct'] ?? 0), 1);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-gray-900">{{ $prod['product_name'] }}</div>
                                            <div class="text-muted fs-8 font-monospace">{{ $prod['sku'] }}</div>
                                        </td>
                                        <td class="text-center fw-semibold text-gray-700">
                                            {{ number_format((float)$prod['total_sold_qty'], 0, ',', '.') }}
                                        </td>
                                        <td class="text-end text-muted">
                                            {{ $money($prod['total_revenue']) }}
                                        </td>
                                        <td class="text-end fw-bold {{ (float)$prod['total_margin'] <= 0 ? 'text-danger' : 'text-gray-800' }}">
                                            {{ $money($prod['total_margin']) }}
                                        </td>
                                        <td class="text-center">
                                            <span class="margin-pill low">
                                                <i class="ki-outline ki-arrow-down fs-8 me-1"></i> {{ $fmtPercent($marginPct) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="ki-outline ki-check-circle fs-2 text-success d-block mb-2"></i>
                                            Semua produk yang terjual memiliki margin sehat di atas 15%.
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

    {{-- TABEL RIWAYAT PENYESUAIAN HARGA JUAL --}}
    <section class="card shadow-sm border-0 mb-5" style="border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-white py-4 border-bottom border-gray-200">
            <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="badge badge-light-primary p-2">
                        <i class="ki-outline ki-tag fs-3 text-primary"></i>
                    </div>
                    <div>
                        <h2 class="card-title fs-5 fw-bold text-gray-900 mb-0">Riwayat Penyesuaian Harga Jual Terbaru</h2>
                        <div class="fs-8 text-muted">Log histori perubahan harga jual, snapshot HPP modal, dan batas harga anti-rugi</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-light fw-bold text-gray-600">
                        {{ count($priceHistoryRows) }} Rekaman Terdata
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="ep-table">
                    <thead>
                        <tr>
                            <th>Waktu &amp; Tanggal</th>
                            <th>Produk &amp; SKU</th>
                            <th>Kanal</th>
                            <th class="text-end">Harga Lama</th>
                            <th class="text-end">Harga Baru</th>
                            <th class="text-center">Perubahan</th>
                            <th class="text-end">Snapshot HPP</th>
                            <th class="text-center">Estimasi Margin</th>
                            <th>Alasan Penyesuaian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($priceHistoryRows as $row)
                            @php
                                $chgPct = (float) ($row['price_change_percent'] ?? 0);
                                $mrgPct = (float) ($row['margin_percent'] ?? 0);
                            @endphp
                            <tr>
                                <td class="text-muted fs-8 text-nowrap">
                                    {{ \Carbon\Carbon::parse($row['created_at'])->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td>
                                    <div class="fw-bold text-gray-900">{{ $row['product'] ?? '-' }}</div>
                                    <div class="text-muted fs-8 font-monospace">{{ $row['sku'] ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-light-dark text-uppercase fs-9 fw-bold">
                                        {{ $row['channel'] ?? 'RETAIL' }}
                                    </span>
                                </td>
                                <td class="text-end text-muted font-monospace">
                                    {{ $money($row['old_price']) }}
                                </td>
                                <td class="text-end fw-bold text-gray-900 font-monospace">
                                    {{ $money($row['new_price']) }}
                                </td>
                                <td class="text-center">
                                    @if($chgPct > 0)
                                        <span class="badge badge-light-success fw-bold">
                                            +{{ $fmtPercent($chgPct) }}
                                        </span>
                                    @elseif($chgPct < 0)
                                        <span class="badge badge-light-danger fw-bold">
                                            {{ $fmtPercent($chgPct) }}
                                        </span>
                                    @else
                                        <span class="badge badge-light text-muted">0%</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace text-muted">
                                    {{ $money($row['hpp_snapshot']) }}
                                </td>
                                <td class="text-center">
                                    @if($row['margin_percent'] !== null)
                                        <span class="margin-pill {{ $mrgPct >= 20 ? 'high' : ($mrgPct >= 10 ? 'medium' : 'low') }}">
                                            {{ $fmtPercent($mrgPct) }}
                                        </span>
                                    @else
                                        <span class="text-muted fs-8">Terkunci</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-gray-700 fs-8">{{ $row['reason'] ?? '-' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="ki-outline ki-notepad fs-2 d-block mb-2 text-muted"></i>
                                    Tidak ada catatan penyesuaian harga jual pada rentang tanggal ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</div>
