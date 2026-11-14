@php
    use App\Support\CurrencyFormatter;

    $summary     = $report['summary'] ?? [];
    $branches    = $report['branches'] ?? [];
    $leaderboard = $report['leaderboard'] ?? [];

    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y')
                 .' – '
                 .\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');

    $money = fn($v): string => $v === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string)$v);
    $fmtPercent = fn($v): string => $v === null ? 'Akses terbatas' : str_replace('.', ',', (string)$v).'%';

    $branchesCount = count($branches);
    $storesCount   = count(array_filter($branches, fn($b) => ($b['type'] ?? '') === 'branch'));
    $warehousesCount = count(array_filter($branches, fn($b) => ($b['type'] ?? '') === 'warehouse'));
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<style>
/* ══════════════════════════════════════════════════════════════════════════
   CUSTOM STYLES FOR EXECUTIVE BRANCH COMPARISON & PERFORMANCE
   ══════════════════════════════════════════════════════════════════════════ */
.loc-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
@media (max-width: 991px) {
    .loc-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 575px) {
    .loc-kpi-grid {
        grid-template-columns: 1fr;
    }
}
.loc-kpi-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 125px;
}

.loc-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.loc-kpi-label {
    font-size: 12px;
    font-weight: 750;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.loc-kpi-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    background: #f1f5f9;
    color: #334155;
}
.loc-kpi-val {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -.02em;
    line-height: 1.15;
    margin-bottom: 6px;
    font-variant-numeric: tabular-nums;
}
.loc-kpi-note {
    font-size: 11px;
    color: #64748b;
    line-height: 1.35;
}

/* Leaderboard Podia Grid */
.podia-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 22px;
}
@media (max-width: 991px) {
    .podia-grid {
        grid-template-columns: 1fr;
    }
}
.podia-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.podia-crown {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    flex: none;
}
.podia-card.gold .podia-crown   { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.podia-card.silver .podia-crown { background: #d1fae5; color: #047857; border: 1px solid #a7f3d0; }
.podia-card.bronze .podia-crown { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }

/* Filter Tabs */
.branch-filter-tabs {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}
.branch-tab-btn {
    border: none;
    background: transparent;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    transition: all .15s ease;
}
.branch-tab-btn.active {
    background: #fff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

/* Branch Matrix Table */
.branch-matrix-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
}
.branch-matrix-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
    white-space: nowrap;
}
.branch-matrix-table td {
    padding: 13px 14px;
    border-bottom: 1px solid #edf2f7;
    color: var(--dark);
    vertical-align: middle;
}
.branch-matrix-table tr:hover td {
    background: #fbfcfe;
}
.rank-badge {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: inline-grid;
    place-items: center;
    font-size: 11px;
    font-weight: 800;
    background: #f1f5f9;
    color: #475569;
}
.rank-badge.top-1 { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.rank-badge.top-2 { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.rank-badge.top-3 { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

.loc-type-tag {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 10.5px;
    font-weight: 700;
}
.loc-type-tag.branch    { background: #eff6ff; color: #1d4ed8; }
.loc-type-tag.warehouse { background: #fef3c7; color: #b45309; }

.progress-share {
    height: 5px;
    border-radius: 3px;
    background: #f1f5f9;
    overflow: hidden;
    margin-top: 4px;
    width: 80px;
}
.progress-share-bar {
    height: 100%;
    background: #3b82f6;
    border-radius: 3px;
}

@media print {
    .hero-actions, .filters, .btn-dash, .branch-filter-tabs, .action-col { display: none !important; }
    .loc-kpi-card, .podia-card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
    .podia-grid { grid-template-columns: repeat(3, 1fr) !important; }
}
</style>

{{-- SVG ICONS SYMBOL DEFINITIONS --}}
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-shop" viewBox="0 0 24 24"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2v0a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12v0a2 2 0 0 1-2-2V7"/></symbol>
    <symbol id="i-warehouse" viewBox="0 0 24 24"><path d="M3 21V8l9-5 9 5v13M3 21h18M7 21V11h10v10M7 15h10M7 18h10"/></symbol>
    <symbol id="i-coins" viewBox="0 0 24 24"><ellipse cx="10" cy="6" rx="7" ry="3"/><path d="M3 6v5c0 1.7 3.1 3 7 3m7-8v4M3 11v5c0 1.7 3.1 3 7 3m0-5c0-1.7 2.5-3 5.5-3s5.5 1.3 5.5 3-2.5 3-5.5 3-5.5-1.3-5.5-3Zm0 0v4c0 1.7 2.5 3 5.5 3s5.5-1.3 5.5-3v-4"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>
    <symbol id="i-print" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></symbol>
    <symbol id="i-filter" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></symbol>
    <symbol id="i-building" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="9" y1="22" x2="9" y2="2"/><line x1="8" y1="6" x2="10" y2="6"/><line x1="14" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="10" y2="10"/><line x1="14" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="10" y2="14"/><line x1="14" y1="14" x2="16" y2="14"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18M8 16v-4m5 4V8m5 8V5"/></symbol>
    <symbol id="i-trophy" viewBox="0 0 24 24"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.45 1-1 1H7v2h10v-2h-2c-.55 0-1-.45-1-1v-2.34"/><path d="M18 4H6v7a6 6 0 0 0 12 0V4Z"/></symbol>
    <symbol id="i-medal" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></symbol>
    <symbol id="i-award" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M18 14.5V22l-6-3-6 3v-7.5"/></symbol>
    <symbol id="i-shield-tick" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3 2 20h20L12 3Zm0 6v5m0 3h.01"/></symbol>
</svg>

<div class="gudang-dashboard-wrap">

    {{-- ══════════════════════════════════════════════════════════════════════════
         HERO SECTION: EXECUTIVE BRANCH PERFORMANCE
         ══════════════════════════════════════════════════════════════════════════ --}}
    <header class="hero" aria-labelledby="title">
        <svg class="hero-decor" viewBox="0 0 460 210" aria-hidden="true">
            <defs>
                <pattern id="branch-grid" width="22" height="22" patternUnits="userSpaceOnUse"><path d="M22 0H0v22" stroke="#b6cce8" stroke-opacity=".12"/></pattern>
            </defs>
            <rect width="460" height="210" fill="url(#branch-grid)"/>
        </svg>

        <div>
            <div class="hero-badge">
                <svg class="ico"><use href="#i-building"/></svg>
                <span>EXECUTIVE BRANCH PERFORMANCE</span>
            </div>
            <h1 id="title">Performa Cabang</h1>
            <p>Komparasi Toko Internal &amp; Gudang: Ranking Omzet, Efisiensi Biaya Kas, dan Kesehatan Aset Barang.</p>

            {{-- 4-BUTTON HERO SWITCHER --}}
            <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'overview', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
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
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-shield-tick"/></svg>
                    Rekapitulasi Pajak
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'locations', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash gold py-1 px-3" style="min-height:30px; font-size:11px;">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-building"/></svg>
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
            <div class="hero-date">
                <svg class="ico"><use href="#i-calendar"/></svg>
                <span>{{ $periodLabel }}</span>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <button type="button" class="btn-dash clear" onclick="window.print()">
                    <svg class="ico"><use href="#i-print"/></svg>
                    <span>Cetak A4</span>
                </button>

                @if($canExport)
                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'locations']) }}" class="d-inline-flex gap-2">
                        @csrf
                        @foreach(request()->except(['format','_token']) as $n => $v)
                            @if(is_scalar($v))<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="hidden" name="format" value="pdf">
                        <button type="submit" class="btn-dash green">
                            <svg class="ico"><use href="#i-download"/></svg>
                            <span>Ekspor PDF</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'locations']) }}" class="d-inline-flex">
                        @csrf
                        @foreach(request()->except(['format','_token']) as $n => $v)
                            @if(is_scalar($v))<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="hidden" name="format" value="xlsx">
                        <button type="submit" class="btn-dash gold" title="Unduh Spreadsheet Excel">
                            <span>Excel</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    {{-- ══════════════════════════════════════════════════════════════════════════
         FILTER PARAMETER
         ══════════════════════════════════════════════════════════════════════════ --}}
    <form method="GET" action="{{ route('reports.profiles.owner.show', ['report' => 'locations']) }}" class="filters">
        <div class="field">
            <label for="f-start">Tanggal Mulai</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-calendar"/></svg>
                <input type="date" id="f-start" name="start_date" value="{{ $filters['start_date'] }}">
            </div>
        </div>
        <div class="field">
            <label for="f-end">Tanggal Akhir</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-calendar"/></svg>
                <input type="date" id="f-end" name="end_date" value="{{ $filters['end_date'] }}">
            </div>
        </div>
        <div class="field">
            <label for="f-range">Granularitas Waktu</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-chart"/></svg>
                <select id="f-range" name="range">
                    <option value="daily" @selected(($filters['range'] ?? '') === 'daily')>Harian</option>
                    <option value="weekly" @selected(($filters['range'] ?? '') === 'weekly')>Mingguan</option>
                    <option value="monthly" @selected(($filters['range'] ?? '') === 'monthly')>Bulanan</option>
                </select>
            </div>
        </div>
        <div class="field-actions">
            <button type="submit" class="btn-dash green">
                <svg class="ico"><use href="#i-filter"/></svg>
                <span>Terapkan</span>
            </button>
            <a href="{{ route('reports.profiles.owner.show', ['report' => 'locations']) }}" class="btn-dash clear">
                <span>Reset</span>
            </a>
        </div>
    </form>

    {{-- ══════════════════════════════════════════════════════════════════════════
         4 KARTU KPI KONSOLIDASI CABANG (BERSIH & TANPA GRAFIK GARIS)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <section class="loc-kpi-grid">
        {{-- Total Omzet Konsolidasi --}}
        <div class="loc-kpi-card">
            <div class="loc-kpi-top">
                <span class="loc-kpi-label">Total Omzet Konsolidasi</span>
                <span class="loc-kpi-badge">Semua Cabang</span>
            </div>
            <div class="loc-kpi-val">{{ $money($summary['total_revenue'] ?? 0) }}</div>
            <div class="loc-kpi-note">
                <span>Dari {{ $summary['branches_count'] ?? count($branches) }} unit operasional aktif</span>
            </div>
        </div>

        {{-- Total Beban Kas Operasional --}}
        <div class="loc-kpi-card">
            <div class="loc-kpi-top">
                <span class="loc-kpi-label">Total Beban Kas Cabang</span>
                <span class="loc-kpi-badge">Beban Shift &amp; Shortage</span>
            </div>
            <div class="loc-kpi-val">{{ $money($summary['total_expenses'] ?? 0) }}</div>
            <div class="loc-kpi-note">
                <span>Total pengeluaran kas harian kasir &amp; toko</span>
            </div>
        </div>

        {{-- Total Nilai Aset Stok --}}
        <div class="loc-kpi-card">
            <div class="loc-kpi-top">
                <span class="loc-kpi-label">Total Nilai Aset Stok HPP</span>
                <span class="loc-kpi-badge">Aset Persediaan</span>
            </div>
            <div class="loc-kpi-val">
                {{ $canViewSensitive ? $money($summary['total_stock_value'] ?? null) : 'Akses terbatas' }}
            </div>
            <div class="loc-kpi-note">
                <span>{{ $summary['total_critical_skus'] ?? 0 }} SKU kritis butuh restock</span>
            </div>
        </div>

        {{-- Top Performer Cabang --}}
        <div class="loc-kpi-card">
            <div class="loc-kpi-top">
                <span class="loc-kpi-label">Cabang Juara Omzet (#1)</span>
                <span class="loc-kpi-badge">Kontribusi {{ $summary['top_branch_share'] ?? 0 }}%</span>
            </div>
            <div class="loc-kpi-val fs-5 text-truncate" title="{{ $summary['top_branch_name'] ?? '-' }}">
                {{ $summary['top_branch_name'] ?? '-' }}
            </div>
            <div class="loc-kpi-note">
                <span>Unit kerja dengan penjualan tertinggi</span>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════════════════
         LEADERBOARD TOP 3 PODIA CABANG
         ══════════════════════════════════════════════════════════════════════════ --}}
    @php
        $revChamp   = $leaderboard['revenue_champion'] ?? null;
        $effChamp   = $leaderboard['efficiency_champion'] ?? null;
        $stockChamp = $leaderboard['stock_champion'] ?? null;
    @endphp

    <section class="podia-grid">
        {{-- Juara 1: Omzet --}}
        <div class="podia-card gold">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="podia-crown" title="Juara 1 Omzet">
                        <svg class="ico" style="width:20px;height:20px;"><use href="#i-trophy"/></svg>
                    </div>
                    <div>
                        <span class="badge bg-light-warning text-warning fw-bold fs-8">JUARA OMZET</span>
                        <h4 class="fs-6 fw-bold text-dark m-0 mt-1">{{ $revChamp['name'] ?? '-' }}</h4>
                    </div>
                </div>
                <span class="loc-type-tag {{ $revChamp['type'] ?? 'branch' }}">
                    {{ $revChamp['type_label'] ?? 'Toko' }}
                </span>
            </div>
            <div>
                <div class="fs-4 fw-bold text-dark font-monospace">{{ $money($revChamp['net_revenue'] ?? 0) }}</div>
                <div class="d-flex align-items-center justify-content-between text-muted fs-8 mt-1">
                    <span>Pangsa Omzet: <strong>{{ $revChamp['contribution_percent'] ?? 0 }}%</strong></span>
                    <span>Transaksi: <strong>{{ $revChamp['transactions_count'] ?? 0 }}</strong></span>
                </div>
            </div>
        </div>

        {{-- Juara 2: Efisiensi Kas --}}
        <div class="podia-card silver">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="podia-crown" title="Juara Efisiensi Kas">
                        <svg class="ico" style="width:20px;height:20px;"><use href="#i-medal"/></svg>
                    </div>
                    <div>
                        <span class="badge bg-light-success text-success fw-bold fs-8">JUARA EFISIENSI KAS</span>
                        <h4 class="fs-6 fw-bold text-dark m-0 mt-1">{{ $effChamp['name'] ?? '-' }}</h4>
                    </div>
                </div>
                <span class="loc-type-tag {{ $effChamp['type'] ?? 'branch' }}">
                    {{ $effChamp['type_label'] ?? 'Toko' }}
                </span>
            </div>
            <div>
                <div class="fs-4 fw-bold text-success font-monospace">{{ $effChamp['expense_ratio'] ?? 0 }}%</div>
                <div class="d-flex align-items-center justify-content-between text-muted fs-8 mt-1">
                    <span>Rasio Beban Kas Terendah</span>
                    <span>Beban: <strong>{{ $money($effChamp['total_cash_expense'] ?? 0) }}</strong></span>
                </div>
            </div>
        </div>

        {{-- Juara 3: Aset Persediaan --}}
        <div class="podia-card bronze">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="podia-crown" title="Juara Manajemen Aset">
                        <svg class="ico" style="width:20px;height:20px;"><use href="#i-award"/></svg>
                    </div>
                    <div>
                        <span class="badge bg-light-primary text-primary fw-bold fs-8">JUARA MANAJEMEN ASET</span>
                        <h4 class="fs-6 fw-bold text-dark m-0 mt-1">{{ $stockChamp['name'] ?? '-' }}</h4>
                    </div>
                </div>
                <span class="loc-type-tag {{ $stockChamp['type'] ?? 'warehouse' }}">
                    {{ $stockChamp['type_label'] ?? 'Gudang' }}
                </span>
            </div>
            <div>
                <div class="fs-4 fw-bold text-dark font-monospace">
                    {{ $canViewSensitive ? $money($stockChamp['stock_value'] ?? null) : 'Akses terbatas' }}
                </div>
                <div class="d-flex align-items-center justify-content-between text-muted fs-8 mt-1">
                    <span>Total SKU: <strong>{{ $stockChamp['total_skus'] ?? 0 }}</strong></span>
                    <span class="{{ ($stockChamp['critical_skus'] ?? 0) > 0 ? 'text-danger fw-bold' : '' }}">
                        Kritis: {{ $stockChamp['critical_skus'] ?? 0 }}
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════════════════
         TABEL KOMPARASI PERFORMA CABANG TERPADU
         ══════════════════════════════════════════════════════════════════════════ --}}
    <article class="card-dash panel mb-4">
        <div class="panel-head mb-3">
            <div>
                <h3>Matriks Komparasi Performa Seluruh Cabang</h3>
                <p>Bandingkan performa penjualan, efisiensi operasional kas, dan nilai aset stok antar-lokasi.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                {{-- TAB FILTER CEPAT --}}
                <div class="branch-filter-tabs">
                    <button type="button" class="branch-tab-btn active" data-filter="all">Semua ({{ $branchesCount }})</button>
                    <button type="button" class="branch-tab-btn" data-filter="branch">Toko ({{ $storesCount }})</button>
                    <button type="button" class="branch-tab-btn" data-filter="warehouse">Gudang ({{ $warehousesCount }})</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="branch-matrix-table" id="table-branches">
                <thead>
                    <tr>
                        <th style="width: 45px;" class="text-center">Rank</th>
                        <th>Unit Operasional</th>
                        <th class="text-end" style="width: 140px;">Omzet Bersih</th>
                        <th style="width: 110px;">Pangsa Omzet</th>
                        <th class="text-end" style="width: 120px;">Beban Kas Shift</th>
                        <th class="text-end" style="width: 110px;">Selisih Kasir</th>
                        <th class="text-center" style="width: 90px;">Rasio Biaya</th>
                        <th class="text-end" style="width: 140px;">Nilai Aset Stok HPP</th>
                        <th class="text-center" style="width: 85px;">SKU Kritis</th>
                        <th class="text-center action-col" style="width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($branches as $b)
                        @php
                            $rankBadge = match($b['rank']) {
                                1 => 'top-1',
                                2 => 'top-2',
                                3 => 'top-3',
                                default => '',
                            };
                        @endphp
                        <tr class="branch-row" data-type="{{ $b['type'] }}">
                            <td class="text-center">
                                <span class="rank-badge {{ $rankBadge }}">#{{ $b['rank'] }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="fw-bold text-dark fs-7">{{ $b['name'] }}</div>
                                    <span class="loc-type-tag {{ $b['type'] }}">{{ $b['type_label'] }}</span>
                                </div>
                                <div class="text-muted fs-8">{{ $b['transactions_count'] }} transaksi</div>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                {{ $money($b['net_revenue']) }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center justify-content-between fs-8 font-monospace">
                                    <span>{{ $b['contribution_percent'] }}%</span>
                                </div>
                                <div class="progress-share">
                                    <div class="progress-share-bar" style="width: {{ min(100, (float)$b['contribution_percent']) }}%;"></div>
                                </div>
                            </td>
                            <td class="text-end font-monospace text-muted">
                                {{ $money($b['shift_expenses']) }}
                            </td>
                            <td class="text-end font-monospace {{ (float)$b['cash_shortage'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                                {{ (float)$b['cash_shortage'] > 0 ? '- '.$money($b['cash_shortage']) : 'Rp 0' }}
                            </td>
                            <td class="text-center font-monospace">
                                <span class="badge {{ (float)$b['expense_ratio'] > 15 ? 'bg-light-danger text-danger' : 'bg-light-success text-success' }} fs-8">
                                    {{ $b['expense_ratio'] }}%
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                {{ $canViewSensitive ? $money($b['stock_value']) : 'Akses terbatas' }}
                            </td>
                            <td class="text-center">
                                @if($b['critical_skus'] > 0)
                                    <span class="badge bg-light-danger text-danger fw-bold fs-8">
                                        {{ $b['critical_skus'] }} SKU
                                    </span>
                                @else
                                    <span class="badge bg-light-success text-success fs-8">Aman</span>
                                @endif
                            </td>
                            <td class="text-center action-col">
                                <button type="button" class="btn-dash clear py-1 px-2 btn-detail" 
                                        style="min-height:28px; font-size:11px;"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modal-branch-detail"
                                        data-branch="{{ json_encode($b) }}">
                                    <svg class="ico me-1" style="width:12px;height:12px;"><use href="#i-eye"/></svg>
                                    Rincian
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                Tidak ada data cabang operasional yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     MODAL POPUP RINCIAN CABANG (BOOTSTRAP 5)
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modal-branch-detail" tabindex="-1" aria-labelledby="modalBranchTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="sq blue" style="width:34px;height:34px;"><svg class="ico" style="width:18px;height:18px;"><use href="#i-building"/></svg></span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark m-0" id="modalBranchTitle">Rincian Operasional Cabang</h5>
                        <p class="text-muted fs-8 m-0" id="modalBranchSubtitle">Toko Retail / Gudang</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Quick Metrics Row --}}
                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted fs-8 fw-bold text-uppercase">Omzet Bersih</span>
                            <div class="fs-5 fw-bold text-dark mt-1 font-monospace" id="modal-rev">Rp 0</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted fs-8 fw-bold text-uppercase">Total Beban Kas</span>
                            <div class="fs-5 fw-bold text-danger mt-1 font-monospace" id="modal-exp">Rp 0</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted fs-8 fw-bold text-uppercase">Nilai Aset Stok</span>
                            <div class="fs-5 fw-bold text-success mt-1 font-monospace" id="modal-stock">Rp 0</div>
                        </div>
                    </div>
                </div>

                {{-- Tab Navigation inside Modal --}}
                <ul class="nav nav-pills mb-3 gap-2" id="branchDetailTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-2 px-3 fs-8 fw-bold" id="tab-expenses-btn" data-bs-toggle="pill" data-bs-target="#tab-expenses" type="button" role="tab">Breakdown Beban Kas Shift</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-3 fs-8 fw-bold" id="tab-skus-btn" data-bs-toggle="pill" data-bs-target="#tab-skus" type="button" role="tab">Daftar SKU Kritis (<span id="modal-crit-count">0</span>)</button>
                    </li>
                </ul>

                <div class="tab-content border rounded-3 p-3 bg-white" id="branchDetailTabContent">
                    {{-- Tab 1: Shift Expenses --}}
                    <div class="tab-pane fade show active" id="tab-expenses" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped m-0 fs-8">
                                <thead>
                                    <tr>
                                        <th>Kategori Pengeluaran</th>
                                        <th class="text-center">Frekuensi</th>
                                        <th class="text-end">Total Nominal</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-expense-rows">
                                    <tr><td colspan="3" class="text-center py-3 text-muted">Tidak ada pengeluaran kas shift.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 2: Critical SKUs --}}
                    <div class="tab-pane fade" id="tab-skus" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped m-0 fs-8">
                                <thead>
                                    <tr>
                                        <th>Nama Produk</th>
                                        <th>SKU</th>
                                        <th class="text-end">Sisa Fisik</th>
                                        <th class="text-end">Batas Minimum</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-sku-rows">
                                    <tr><td colspan="4" class="text-center py-3 text-muted">Tidak ada stok produk yang kritis.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-4">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     INTERACTIVE JAVASCRIPT: INSTANT TAB FILTER & MODAL DATA POPULATE
     ══════════════════════════════════════════════════════════════════════════ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Instant Tab Filtering (Semua / Toko / Gudang)
    const filterBtns = document.querySelectorAll('.branch-tab-btn');
    const tableRows  = document.querySelectorAll('.branch-row');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const filter = this.getAttribute('data-filter');
            tableRows.forEach(row => {
                const type = row.getAttribute('data-type');
                if (filter === 'all' || filter === type) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    // 2. Modal Data Population
    const modalDetail = document.getElementById('modal-branch-detail');
    if (modalDetail) {
        modalDetail.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const dataStr = button.getAttribute('data-branch');
            if (!dataStr) return;

            const b = JSON.parse(dataStr);
            document.getElementById('modalBranchTitle').textContent = b.name;
            document.getElementById('modalBranchSubtitle').textContent = b.type_label + ' • Peringkat #' + b.rank;

            const moneyFormat = (val) => val === null ? 'Akses terbatas' : 'Rp ' + Number(val).toLocaleString('id-ID');

            document.getElementById('modal-rev').textContent = moneyFormat(b.net_revenue);
            document.getElementById('modal-exp').textContent = moneyFormat(b.total_cash_expense);
            document.getElementById('modal-stock').textContent = moneyFormat(b.stock_value);
            document.getElementById('modal-crit-count').textContent = b.critical_skus;

            // Populate Expense Categories
            const expTbody = document.getElementById('modal-expense-rows');
            expTbody.innerHTML = '';
            if (b.expense_categories && b.expense_categories.length > 0) {
                b.expense_categories.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="fw-bold text-dark">${item.category}</td>
                        <td class="text-center">${item.count}x</td>
                        <td class="text-end font-monospace text-danger">${moneyFormat(item.total)}</td>
                    `;
                    expTbody.appendChild(tr);
                });
            } else {
                expTbody.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-muted">Tidak ada pengeluaran kas shift pada periode ini.</td></tr>';
            }

            // Populate Critical SKUs
            const skuTbody = document.getElementById('modal-sku-rows');
            skuTbody.innerHTML = '';
            if (b.critical_products && b.critical_products.length > 0) {
                b.critical_products.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="fw-bold text-dark">${p.name}</td>
                        <td class="font-monospace text-muted">${p.sku}</td>
                        <td class="text-end font-monospace text-danger fw-bold">${p.on_hand}</td>
                        <td class="text-end font-monospace text-muted">${p.min_stock}</td>
                    `;
                    skuTbody.appendChild(tr);
                });
            } else {
                skuTbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Semua stok produk berada di atas batas minimum.</td></tr>';
            }
        });
    }
});
</script>
