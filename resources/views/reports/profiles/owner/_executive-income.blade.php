@php
    use App\Support\CurrencyFormatter;

    $summary                 = $report['summary'] ?? [];
    $pnlStatement            = $report['pnl_statement'] ?? [];
    $branchProfitability     = $report['branch_profitability'] ?? [];
    $shiftExpensesBreakdown  = $report['shift_expenses_breakdown'] ?? [];
    $recentShiftExpenses     = $report['recent_shift_expenses'] ?? [];
    $taxSummary              = $report['tax_summary'] ?? [];

    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y')
                 .' – '
                 .\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');

    $money = fn($v): string => $v === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string)$v);
    $fmtPercent = fn($v): string => $v === null ? 'Akses terbatas' : str_replace('.', ',', (string)$v).'%';

    $netRev = (float) ($summary['net_revenue'] ?? 0);
    $cogsVal = (float) ($summary['cogs'] ?? 0);
    $grossVal = (float) ($summary['gross_margin'] ?? 0);
    $netOpVal = (float) ($summary['net_operating_profit'] ?? 0);
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<style>
/* ══════════════════════════════════════════════════════════════════════════
   CUSTOM STYLES FOR EXECUTIVE INCOME & PROFITABILITY STATEMENT
   ══════════════════════════════════════════════════════════════════════════ */
.pnl-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
}
.pnl-table th {
    background: #f7f9fc;
    color: #4a5d78;
    font-size: 11px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 12px 16px;
    border-bottom: 1px solid var(--line);
}
.pnl-table td {
    padding: 11px 16px;
    border-bottom: 1px solid #edf2f7;
    color: var(--dark);
    vertical-align: middle;
}
.pnl-table tr:hover td {
    background: #fbfcfe;
}
.pnl-section-header td {
    background: #edf3f9;
    font-weight: 800;
    color: #1a365d;
    font-size: 12px;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding-top: 14px;
    padding-bottom: 10px;
    border-top: 2px solid #d8e2ee;
    border-bottom: 1px solid #d8e2ee;
}
.pnl-row-deduction td.amount-col {
    color: #b44136;
}
.pnl-row-subtotal td {
    background: #f8fafc;
    font-weight: 700;
    border-top: 1px dashed #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
}
.pnl-row-total td {
    background: #f1f5f9;
    font-weight: 800;
    font-size: 14px;
    border-top: 2px solid #94a3b8;
    border-bottom: 2px solid #94a3b8;
}
.pnl-row-highlight td {
    background: #ecfdf5;
    font-weight: 800;
    font-size: 14px;
    color: #065f46;
    border-top: 2px solid #6ee7b7;
    border-bottom: 2px solid #6ee7b7;
}
.pnl-row-final td {
    background: #eff6ff;
    font-weight: 850;
    font-size: 15px;
    color: #1e3a8a;
    border-top: 2px solid #93c5fd;
    border-bottom: 3px double #1e3a8a;
}
.badge-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 7px;
    border-radius: 6px;
    background: #edf2f7;
    color: #475569;
}
.profit-card-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 18px;
}
@media (max-width: 1024px) {
    .profit-card-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 640px) {
    .profit-card-grid {
        grid-template-columns: 1fr;
    }
}
.profit-kpi-card {
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

.profit-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.profit-kpi-label {
    font-size: 12px;
    font-weight: 750;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.profit-kpi-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    background: #f1f5f9;
    color: #334155;
}
.profit-kpi-val {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -.02em;
    line-height: 1.15;
    margin-bottom: 6px;
}
.profit-kpi-note {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
}
.two-col-financial {
    display: grid;
    grid-template-columns: 1.35fr 1fr;
    gap: 16px;
    margin-top: 18px;
}
@media (max-width: 991px) {
    .two-col-financial {
        grid-template-columns: 1fr;
    }
}
@media print {
    body { background: #fff !important; }
    .hero-actions, .filters, .btn-dash, #kt_app_sidebar, #kt_app_header { display: none !important; }
    .gudang-dashboard-wrap { max-width: 100% !important; padding: 0 !important; }
    .hero { background: #102945 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .profit-card-grid { grid-template-columns: repeat(4, 1fr) !important; page-break-inside: avoid; }
    .card { page-break-inside: avoid; box-shadow: none !important; border: 1px solid #ccc !important; }
}
</style>

{{-- SVG ICONS SYMBOL DEFINITIONS --}}
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-crown" viewBox="0 0 24 24"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></symbol>
    <symbol id="i-layer" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></symbol>
    <symbol id="i-box" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></symbol>
    <symbol id="i-coins" viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.66 3.58 3 8 3s8-1.34 8-3V6M4 12v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></symbol>
    <symbol id="i-print" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></symbol>
    <symbol id="i-arrow-left" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></symbol>
    <symbol id="i-filter" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>
    <symbol id="i-building" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="9" y1="22" x2="9" y2="2"/><line x1="8" y1="6" x2="10" y2="6"/><line x1="14" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="10" y2="10"/><line x1="14" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="10" y2="14"/><line x1="14" y1="14" x2="16" y2="14"/></symbol>
</svg>

<div class="gudang-dashboard-wrap">
    {{-- ══════════════════════════════════════════════════════════════════════════
         HERO SECTION (EXECUTIVE BUSINESS REPORT)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <header class="hero">
        <svg class="hero-decor" viewBox="0 0 460 210" aria-hidden="true">
            <path d="M70 180 C 140 100, 260 220, 360 90 L460 140 L460 210 L70 210 Z" fill="rgba(255,255,255,0.04)" />
            <path d="M130 190 C 220 120, 300 170, 420 60 L460 100 L460 210 L130 210 Z" fill="rgba(237,186,82,0.08)" />
            <path d="M20 205 C 160 170, 240 120, 460 160 L460 210 L20 210 Z" fill="rgba(255,255,255,0.02)" />
        </svg>

        <div>
            <div class="eyebrow">
                <svg class="ico"><use href="#i-crown"/></svg>
                <span>EXECUTIVE BUSINESS REPORT</span>
            </div>

            <h1>Laba Rugi &amp; Finansial</h1>
            <p>
                Laporan resmi laba rugi komprehensif berjenjang, beban pokok penjualan (HPP), efisiensi operasional cabang, dan estimasi pajak usaha.
            </p>

            <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'overview', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-chart"/></svg>
                    Ringkasan Eksekutif
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'income', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash gold py-1 px-3" style="min-height:30px; font-size:11px;">
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
                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'income']) }}" class="d-inline-flex gap-2">
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

                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'income']) }}" class="d-inline-flex">
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
    <form method="GET" action="{{ route('reports.profiles.owner.show', ['report' => 'income']) }}" class="filters">
        <div class="field">
            <label for="f-start">Tanggal Mulai</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-calendar"/></svg>
                <input type="date" id="f-start" name="start_date" value="{{ $filters['start_date'] }}">
            </div>
        </div>

        <div class="field">
            <label for="f-end">Tanggal Selesai</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-calendar"/></svg>
                <input type="date" id="f-end" name="end_date" value="{{ $filters['end_date'] }}">
            </div>
        </div>

        <div class="field">
            <label for="f-loc">Cakupan Lokasi</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-building"/></svg>
                <select id="f-loc" name="work_location_id">
                    <option value="">Semua Lokasi &amp; Toko (Konsolidasi)</option>
                    @foreach($workLocations as $loc)
                        <option value="{{ $loc->id }}" @selected(($selectedLocation?->id) == $loc->id)>
                            {{ $loc->name }} ({{ ucfirst($loc->type) }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label for="f-range">Granularitas</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-chart"/></svg>
                <select id="f-range" name="range">
                    <option value="daily" @selected(($filters['range'] ?? '') === 'daily')>Harian</option>
                    <option value="monthly" @selected(($filters['range'] ?? '') === 'monthly')>Bulanan</option>
                    <option value="yearly" @selected(($filters['range'] ?? '') === 'yearly')>Tahunan</option>
                </select>
            </div>
        </div>

        <div class="filters-end">
            <button type="submit" class="btn-dash green">
                <svg class="ico"><use href="#i-filter"/></svg>
                <span>Terapkan</span>
            </button>
            <a href="{{ route('reports.profiles.owner.show', ['report' => 'income']) }}" class="btn-dash clear">
                <span>Reset</span>
            </a>
        </div>
    </form>

    {{-- ══════════════════════════════════════════════════════════════════════════
         4 KARTU RINGKASAN FINANSIAL UTAMA (BERSIH & MODERN TANPA GRAFIK GARIS)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <section class="profit-card-grid">
        {{-- Net Revenue --}}
        <div class="profit-kpi-card">
            <div class="profit-kpi-top">
                <span class="profit-kpi-label">Penjualan Bersih</span>
            </div>
            <div class="profit-kpi-val">{{ $money($summary['net_revenue'] ?? 0) }}</div>
            <div class="profit-kpi-note">
                <span>Total omzet setelah retur ({{ $money($summary['returns_amount'] ?? 0) }})</span>
            </div>
        </div>

        {{-- COGS / HPP --}}
        <div class="profit-kpi-card">
            <div class="profit-kpi-top">
                <span class="profit-kpi-label">Beban Pokok (HPP)</span>
            </div>
            <div class="profit-kpi-val">
                {{ $canViewSensitive ? $money($summary['cogs'] ?? null) : 'Akses terbatas' }}
            </div>
            <div class="profit-kpi-note">
                @if($canViewSensitive && $netRev > 0)
                    <span>Rasio HPP: {{ round(($cogsVal / $netRev) * 100, 1) }}% dari penjualan</span>
                @else
                    <span>Lapisan batch persediaan aktif</span>
                @endif
            </div>
        </div>

        {{-- Gross Profit --}}
        <div class="profit-kpi-card">
            <div class="profit-kpi-top">
                <span class="profit-kpi-label">Laba Kotor</span>
            </div>
            <div class="profit-kpi-val">
                {{ $canViewSensitive ? $money($summary['gross_margin'] ?? null) : 'Akses terbatas' }}
            </div>
            <div class="profit-kpi-note">
                <span>Gross Margin: {{ $fmtPercent($summary['margin_percent'] ?? null) }}</span>
            </div>
        </div>

        {{-- Net Operating Profit --}}
        <div class="profit-kpi-card">
            <div class="profit-kpi-top">
                <span class="profit-kpi-label">Laba Operasional Bersih</span>
            </div>
            <div class="profit-kpi-val">
                {{ $canViewSensitive ? $money($summary['net_operating_profit'] ?? null) : 'Akses terbatas' }}
            </div>
            <div class="profit-kpi-note">
                <span>Setelah beban shift kasir: {{ $money($summary['total_expenses'] ?? 0) }}</span>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════════════════
         TABEL LABA RUGI KOMPREHENSIF BERJENJANG (MULTI-STEP P&L STATEMENT)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <article class="card p-0 mb-4" style="overflow:hidden;">
        <div class="d-flex justify-content-between align-items-center p-4 border-bottom flex-wrap gap-2" style="background:#fff;">
            <div>
                <h3 class="card-title m-0" style="font-size:16px; font-weight:800; color:#0f172a;">
                    Laporan Laba Rugi Berjenjang (Income Statement)
                </h3>
                <span class="text-muted fs-8">Penyajian akuntansi komprehensif sesuai transaksi riil unit operasional</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge" style="background:#eff6ff; color:#1d4ed8; font-weight:700; border:1px solid #bfdbfe; font-size:11px; padding:6px 12px; border-radius:20px;">
                    Mata Uang: IDR (Rp)
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="pnl-table">
                <thead>
                    <tr>
                        <th style="width: 100px;">Kode</th>
                        <th>Komponen Laba Rugi</th>
                        <th>Keterangan / Basis Perhitungan</th>
                        <th class="text-end" style="width: 220px;">Nominal (Rp)</th>
                        <th class="text-center" style="width: 140px;">Rasio / Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @php $currentSection = null; @endphp
                    @foreach($pnlStatement as $item)
                        @if(($item['section'] ?? '') !== $currentSection)
                            @php $currentSection = $item['section'] ?? ''; @endphp
                            <tr class="pnl-section-header">
                                <td colspan="5">
                                    @switch($currentSection)
                                        @case('REVENUE')
                                            I. PENDAPATAN USAHA (OPERATING REVENUE)
                                            @break
                                        @case('COGS')
                                            II. BEBAN POKOK PENJUALAN (COST OF GOODS SOLD)
                                            @break
                                        @case('GROSS_PROFIT')
                                            III. HASIL LABA KOTOR USAHA (GROSS PROFIT)
                                            @break
                                        @case('EXPENSES')
                                            IV. BEBAN OPERASIONAL SHIFT CABANG (OPERATING EXPENSES)
                                            @break
                                        @case('NET_PROFIT')
                                            V. LABA OPERASIONAL BERSIH (NET OPERATING PROFIT)
                                            @break
                                        @default
                                            {{ $currentSection }}
                                    @endswitch
                                </td>
                            </tr>
                        @endif

                        @php
                            $rowClass = match(true) {
                                !empty($item['is_highlight']) && ($item['code'] ?? '') === 'NP-TOT' => 'pnl-row-final',
                                !empty($item['is_highlight']) => 'pnl-row-highlight',
                                !empty($item['is_total']) => 'pnl-row-total',
                                !empty($item['is_deduction']) => 'pnl-row-deduction',
                                default => '',
                            };
                        @endphp

                        <tr class="{{ $rowClass }}">
                            <td>
                                <span class="badge-code">{{ $item['code'] }}</span>
                            </td>
                            <td>
                                <span style="font-weight: {{ !empty($item['is_total']) || !empty($item['is_highlight']) ? '800' : '600' }};">
                                    {{ $item['name'] }}
                                </span>
                            </td>
                            <td class="text-muted fs-8">
                                {{ $item['notes'] }}
                            </td>
                            <td class="text-end amount-col" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-weight: 750;">
                                @if($item['amount'] === null)
                                    <span class="badge badge-light-secondary text-muted">Akses terbatas</span>
                                @elseif(!empty($item['is_deduction']) && (float)$item['amount'] > 0)
                                    ({{ CurrencyFormatter::rupiah((string)$item['amount']) }})
                                @else
                                    {{ CurrencyFormatter::rupiah((string)$item['amount']) }}
                                @endif
                            </td>
                            <td class="text-center">
                                @if(!empty($item['ratio']))
                                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:750; font-size:11px;">
                                        {{ $item['ratio'] }}
                                    </span>
                                @else
                                    <span class="text-muted fs-8">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </article>

    {{-- ══════════════════════════════════════════════════════════════════════════
         KOMPARASI PROFITABILITAS ANTAR CABANG / UNIT KERJA
         ══════════════════════════════════════════════════════════════════════════ --}}
    <article class="card p-0 mb-4" style="overflow:hidden;">
        <div class="d-flex justify-content-between align-items-center p-4 border-bottom flex-wrap gap-2" style="background:#fff;">
            <div>
                <h3 class="card-title m-0" style="font-size:16px; font-weight:800; color:#0f172a;">
                    Perbandingan Profitabilitas Antar Cabang / Lokasi
                </h3>
                <span class="text-muted fs-8">Evaluasi kontribusi pendapatan, HPP, margin kotor, dan laba operasional unit kerja</span>
            </div>
            <span class="badge badge-light-primary fw-bold fs-8">
                {{ count($branchProfitability) }} Lokasi Kerja
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-row-bordered table-row-gray-200 align-middle gs-4 gy-3 m-0" style="font-size:12.5px;">
                <thead>
                    <tr class="fw-bold fs-8 text-uppercase text-gray-500 bg-light">
                        <th class="ps-4">Lokasi Kerja</th>
                        <th>Tipe</th>
                        <th class="text-end">Penjualan POS</th>
                        <th class="text-end">Penjualan B2B</th>
                        <th class="text-end">Total Omzet</th>
                        <th class="text-end">HPP / COGS</th>
                        <th class="text-end">Laba Kotor</th>
                        <th class="text-center">Gross %</th>
                        <th class="text-end">Beban Kasir</th>
                        <th class="text-end">Laba Bersih</th>
                        <th class="text-center pe-4">Kontribusi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($branchProfitability as $idx => $b)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-circle badge-light-dark fs-8 fw-bold" style="width:20px;height:20px;">
                                        {{ $idx + 1 }}
                                    </span>
                                    <span class="fw-bold text-gray-900">{{ $b['name'] }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-light-secondary fs-8 text-capitalize">{{ $b['type'] }}</span>
                            </td>
                            <td class="text-end font-monospace">{{ CurrencyFormatter::rupiah((string)$b['pos_revenue']) }}</td>
                            <td class="text-end font-monospace">{{ CurrencyFormatter::rupiah((string)$b['b2b_revenue']) }}</td>
                            <td class="text-end font-monospace fw-bold text-gray-900">{{ CurrencyFormatter::rupiah((string)$b['net_revenue']) }}</td>
                            <td class="text-end font-monospace text-muted">
                                {{ $canViewSensitive ? CurrencyFormatter::rupiah((string)($b['cogs'] ?? 0)) : 'Akses terbatas' }}
                            </td>
                            <td class="text-end font-monospace fw-bold {{ $canViewSensitive && (float)($b['gross_profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $canViewSensitive ? CurrencyFormatter::rupiah((string)($b['gross_profit'] ?? 0)) : 'Akses terbatas' }}
                            </td>
                            <td class="text-center">
                                @if($canViewSensitive && $b['gross_margin_percent'] !== null)
                                    <span class="badge badge-light-success fs-8 fw-bold">
                                        {{ str_replace('.', ',', (string)$b['gross_margin_percent']) }}%
                                    </span>
                                @else
                                    <span class="text-muted fs-8">-</span>
                                @endif
                            </td>
                            <td class="text-end font-monospace text-danger">
                                {{ CurrencyFormatter::rupiah((string)$b['expenses']) }}
                            </td>
                            <td class="text-end font-monospace fw-bolder {{ $canViewSensitive && (float)($b['net_profit'] ?? 0) >= 0 ? 'text-primary' : 'text-danger' }}">
                                {{ $canViewSensitive ? CurrencyFormatter::rupiah((string)($b['net_profit'] ?? 0)) : 'Akses terbatas' }}
                            </td>
                            <td class="text-center pe-4">
                                @if($canViewSensitive && $b['contribution_percent'] !== null)
                                    <span class="badge badge-light-primary fs-8 fw-bold">
                                        {{ str_replace('.', ',', (string)$b['contribution_percent']) }}%
                                    </span>
                                @else
                                    <span class="text-muted fs-8">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-6 text-muted">
                                Tidak ada data aktivitas transaksi pada periode dan lokasi ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    {{-- ══════════════════════════════════════════════════════════════════════════
         DUA KOLOM: BEBAN OPERASIONAL SHIFT & ESTIMASI PERPAJAKAN
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="two-col-financial">
        {{-- Kolom Kiri: Rincian Beban Operasional Shift Toko --}}
        <article class="card p-0" style="overflow:hidden;">
            <div class="d-flex justify-content-between align-items-center p-3 px-4 border-bottom" style="background:#fff;">
                <div>
                    <h4 class="card-title m-0" style="font-size:14px; font-weight:800; color:#0f172a;">
                        Rincian Beban Kasir Shift Toko
                    </h4>
                    <span class="text-muted fs-8">Pengeluaran kas operasional yang dicatat kasir</span>
                </div>
                <span class="badge badge-light-danger fw-bold fs-8">
                    Total: {{ CurrencyFormatter::rupiah((string)($summary['shift_expenses'] ?? 0)) }}
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-200 align-middle gs-4 gy-2 m-0" style="font-size:12px;">
                    <thead>
                        <tr class="fw-bold fs-8 text-uppercase text-gray-400 bg-light">
                            <th class="ps-4">Kategori Beban</th>
                            <th class="text-center">Transaksi</th>
                            <th class="text-end">Total Nominal</th>
                            <th class="text-center pe-4">Porsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shiftExpensesBreakdown as $exp)
                            <tr>
                                <td class="ps-4 fw-bold text-gray-800">
                                    {{ ucwords(str_replace('_', ' ', $exp['category'])) }}
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light fs-8">{{ $exp['count'] }}x</span>
                                </td>
                                <td class="text-end font-monospace fw-bold text-gray-900">
                                    {{ CurrencyFormatter::rupiah((string)$exp['total']) }}
                                </td>
                                <td class="text-center pe-4">
                                    <span class="badge badge-light-secondary fs-8">
                                        {{ str_replace('.', ',', (string)$exp['percent']) }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted fs-8">
                                    Tidak ada pengeluaran kasir pada periode terpilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(count($recentShiftExpenses) > 0)
                <div class="p-3 bg-light border-top">
                    <span class="fw-bold text-gray-700 fs-8 text-uppercase d-block mb-2">5 Transaksi Kasir Terakhir</span>
                    <div class="d-flex flex-column gap-2">
                        @foreach(array_slice($recentShiftExpenses, 0, 5) as $re)
                            <div class="d-flex justify-content-between align-items-center bg-white p-2 px-3 rounded border">
                                <div>
                                    <div class="fw-bold fs-8 text-gray-900">{{ $re['notes'] }}</div>
                                    <div class="text-muted fs-8">{{ $re['location_name'] }} &bull; {{ $re['creator_name'] }}</div>
                                </div>
                                <span class="font-monospace fw-bold text-danger fs-8">
                                    -{{ CurrencyFormatter::rupiah((string)$re['amount']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>

        {{-- Kolom Kanan: Ringkasan Perpajakan & Status Fiskal --}}
        <article class="card p-4" style="background:#fff;">
            <div class="d-flex align-items-center gap-2 mb-3">
                <svg class="ico text-primary"><use href="#i-shield"/></svg>
                <h4 class="card-title m-0" style="font-size:14px; font-weight:800; color:#0f172a;">
                    Estimasi Kewajiban Perpajakan
                </h4>
            </div>
            <p class="text-muted fs-8 mb-4">
                Informasi perhitungan pajak berdasarkan transaksi komersial periode berjalan untuk perencanaan arus kas dan SPT.
            </p>

            <div class="d-flex flex-column gap-3 mb-4">
                <div class="p-3 rounded border" style="background:#f8fafc;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted fs-8 fw-bold">PPN Keluaran Terpungut</span>
                        <span class="badge badge-light-success fs-8">Faktur Selesai</span>
                    </div>
                    <div class="font-monospace fs-5 fw-bolder text-gray-900">
                        {{ CurrencyFormatter::rupiah((string)($taxSummary['ppn_collected'] ?? 0)) }}
                    </div>
                    <span class="text-muted fs-8">PPN yang telah dipungut pada faktur penjualan POS &amp; B2B</span>
                </div>

                <div class="p-3 rounded border" style="background:#f0fdf4; border-color:#bbf7d0 !important;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-success fs-8 fw-bold">Estimasi PPh Final UMKM (0,5%)</span>
                        <span class="badge" style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700;">PP 55/2022</span>
                    </div>
                    <div class="font-monospace fs-5 fw-bolder text-success">
                        {{ CurrencyFormatter::rupiah((string)($taxSummary['pph_final_estimate'] ?? 0)) }}
                    </div>
                    <span class="text-muted fs-8">Dihitung dari 0,5% omzet bruto penjualan bersih</span>
                </div>
            </div>

            <div class="p-3 rounded bg-light-warning border border-warning border-dashed">
                <div class="fw-bold text-gray-800 fs-8 mb-1">Catatan Manajemen Pajak:</div>
                <ul class="m-0 ps-3 text-muted fs-8">
                    <li>Kewajiban PPN disetor paling lambat akhir bulan berikutnya sebelum pelaporan SPT Masa PPN.</li>
                    <li>PPh Final 0,5% disetor mandiri via billing DJP kode 411128-420 paling lambat tanggal 15 setiap bulan.</li>
                </ul>
            </div>
        </article>
    </div>
</div>
