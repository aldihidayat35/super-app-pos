@php
    use App\Support\CurrencyFormatter;

    $summary        = $report['summary'] ?? [];
    $agingBuckets   = $report['aging_buckets'] ?? [];
    $customerMatrix = $report['customers_matrix'] ?? [];
    $priorityList   = $report['priority_collection_list'] ?? [];

    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y')
                 .' – '
                 .\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');

    $money = fn($v): string => $v === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string)$v);
    $fmtPercent = fn($v): string => $v === null ? 'Akses terbatas' : str_replace('.', ',', (string)$v).'%';

    $overdueRatio = (float) ($summary['overdue_ratio'] ?? 0);
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<style>
/* ══════════════════════════════════════════════════════════════════════════
   CUSTOM STYLES FOR EXECUTIVE AGING RECEIVABLES SUITE
   ══════════════════════════════════════════════════════════════════════════ */
.ar-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
@media (max-width: 991px) {
    .ar-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 575px) {
    .ar-kpi-grid {
        grid-template-columns: 1fr;
    }
}
.ar-kpi-card {
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

.ar-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.ar-kpi-label {
    font-size: 12px;
    font-weight: 750;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.ar-kpi-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    background: #f1f5f9;
    color: #334155;
}
.ar-kpi-val {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -.02em;
    line-height: 1.15;
    margin-bottom: 6px;
    font-variant-numeric: tabular-nums;
}
.ar-kpi-note {
    font-size: 11px;
    color: #64748b;
    line-height: 1.35;
}

/* 5 Aging Buckets Horizontal Grid */
.aging-banner-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    margin-bottom: 22px;
}
@media (max-width: 991px) {
    .aging-banner-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 575px) {
    .aging-banner-grid {
        grid-template-columns: 1fr;
    }
}
.aging-bucket-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 14px 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.aging-bucket-name {
    font-size: 11px;
    font-weight: 750;
    color: #475569;
    margin-bottom: 4px;
}
.aging-bucket-val {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}
.aging-bucket-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11px;
    color: #64748b;
    margin-top: 6px;
}

/* Priority Action Cards Grid */
.priority-action-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 22px;
}
@media (max-width: 1199px) {
    .priority-action-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 575px) {
    .priority-action-grid {
        grid-template-columns: 1fr;
    }
}
.priority-action-card {
    background: #fff;
    border: 1px solid #fee2e2;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 2px 4px rgba(220,38,38,0.06);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform .15s ease, box-shadow .15s ease;
}
.priority-action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(220,38,38,0.12);
}
.btn-whatsapp {
    background: #25d366;
    color: #fff;
    font-size: 11px;
    font-weight: 750;
    padding: 6px 12px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
    transition: background .15s;
    width: 100%;
}
.btn-whatsapp:hover {
    background: #1ebc59;
    color: #fff;
}

/* Aging Matrix Table */
.aging-matrix-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 12.5px;
}
.aging-matrix-table th {
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
.aging-matrix-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #edf2f7;
    color: var(--dark);
    vertical-align: middle;
}
.aging-matrix-table tr:hover td {
    background: #fbfcfe;
}
.risk-tag {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 750;
}
.risk-tag.safe    { background: #dcfce7; color: #166534; }
.risk-tag.warning { background: #fef3c7; color: #b45309; }
.risk-tag.danger  { background: #fee2e2; color: #991b1b; }

.util-bar-wrap {
    width: 90px;
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 4px;
}
.util-bar-fill {
    height: 100%;
    border-radius: 3px;
}
.util-bar-fill.safe    { background: #059669; }
.util-bar-fill.warning { background: #d97706; }
.util-bar-fill.danger  { background: #dc2626; }

@media print {
    .hero-actions, .filters, .btn-dash, .btn-whatsapp, .action-col { display: none !important; }
    .ar-kpi-card, .aging-bucket-card, .priority-action-card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
}
</style>

{{-- SVG ICONS SYMBOL DEFINITIONS --}}
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>
    <symbol id="i-print" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></symbol>
    <symbol id="i-filter" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></symbol>
    <symbol id="i-building" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="9" y1="22" x2="9" y2="2"/><line x1="8" y1="6" x2="10" y2="6"/><line x1="14" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="10" y2="10"/><line x1="14" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="10" y2="14"/><line x1="14" y1="14" x2="16" y2="14"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18M8 16v-4m5 4V8m5 8V5"/></symbol>
    <symbol id="i-coins" viewBox="0 0 24 24"><ellipse cx="10" cy="6" rx="7" ry="3"/><path d="M3 6v5c0 1.7 3.1 3 7 3m7-8v4M3 11v5c0 1.7 3.1 3 7 3m0-5c0-1.7 2.5-3 5.5-3s5.5 1.3 5.5 3-2.5 3-5.5 3-5.5-1.3-5.5-3Zm0 0v4c0 1.7 2.5 3 5.5 3s5.5-1.3 5.5-3v-4"/></symbol>
    <symbol id="i-shield-tick" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3 2 20h20L12 3Zm0 6v5m0 3h.01"/></symbol>
    <symbol id="i-whatsapp" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></symbol>
</svg>

<div class="gudang-dashboard-wrap">

    {{-- ══════════════════════════════════════════════════════════════════════════
         HERO SECTION: EXECUTIVE RECEIVABLES & AGING SUITE
         ══════════════════════════════════════════════════════════════════════════ --}}
    <header class="hero" aria-labelledby="title">
        <svg class="hero-decor" viewBox="0 0 460 210" aria-hidden="true">
            <defs>
                <pattern id="rec-grid" width="22" height="22" patternUnits="userSpaceOnUse"><path d="M22 0H0v22" stroke="#b6cce8" stroke-opacity=".12"/></pattern>
            </defs>
            <rect width="460" height="210" fill="url(#rec-grid)"/>
        </svg>

        <div>
            <div class="hero-badge">
                <svg class="ico"><use href="#i-wallet"/></svg>
                <span>EXECUTIVE RECEIVABLES &amp; AGING SUITE</span>
            </div>
            <h1 id="title">Piutang &amp; Umur Tagihan</h1>
            <p>Pengawasan Tagihan Pelanggan B2B, Mitigasi Risiko Kredit Macet, dan Penagihan Prioritas.</p>

            {{-- 5-BUTTON HERO SWITCHER --}}
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
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-building"/></svg>
                    Performa Cabang
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'receivables', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash gold py-1 px-3" style="min-height:30px; font-size:11px;">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-wallet"/></svg>
                    Piutang &amp; Aging
                </a>
            </div>
        </div>

        <div class="hero-actions">
            <div class="hero-date">
                <svg class="ico"><use href="#i-calendar"/></svg>
                <span>Posisi per: {{ $summary['as_of_date'] ?? $periodLabel }}</span>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <button type="button" class="btn-dash clear" onclick="window.print()">
                    <svg class="ico"><use href="#i-print"/></svg>
                    <span>Cetak A4</span>
                </button>

                @if($canExport)
                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'receivables']) }}" class="d-inline-flex gap-2">
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

                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'receivables']) }}" class="d-inline-flex">
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
    <form method="GET" action="{{ route('reports.profiles.owner.show', ['report' => 'receivables']) }}" class="filters">
        <div class="field">
            <label for="f-start">Tanggal Mulai Tagihan</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-calendar"/></svg>
                <input type="date" id="f-start" name="start_date" value="{{ $filters['start_date'] }}">
            </div>
        </div>
        <div class="field">
            <label for="f-end">Posisi Evaluasi (Cut-off)</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-calendar"/></svg>
                <input type="date" id="f-end" name="end_date" value="{{ $filters['end_date'] }}">
            </div>
        </div>
        <div class="field">
            <label for="f-loc">Lokasi Operasional Penerbit</label>
            <div class="input-wrap">
                <svg class="ico"><use href="#i-building"/></svg>
                <select id="f-loc" name="work_location_id">
                    <option value="">Semua Lokasi Operasional</option>
                    @foreach($workLocations as $loc)
                        <option value="{{ $loc->id }}" @selected(($selectedLocation?->id) == $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field-actions">
            <button type="submit" class="btn-dash green">
                <svg class="ico"><use href="#i-filter"/></svg>
                <span>Terapkan</span>
            </button>
            <a href="{{ route('reports.profiles.owner.show', ['report' => 'receivables']) }}" class="btn-dash clear">
                <span>Reset</span>
            </a>
        </div>
    </form>

    {{-- ══════════════════════════════════════════════════════════════════════════
         4 KARTU KPI RINGKASAN PIUTANG (BERSIH TANPA GRAFIK GARIS)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <section class="ar-kpi-grid">
        {{-- Total Piutang Aktif --}}
        <div class="ar-kpi-card">
            <div class="ar-kpi-top">
                <span class="ar-kpi-label">Total Piutang Berjalan</span>
                <span class="ar-kpi-badge">Total Outstanding</span>
            </div>
            <div class="ar-kpi-val">{{ $money($summary['total_outstanding'] ?? 0) }}</div>
            <div class="ar-kpi-note">
                <span>Dari {{ $summary['open_accounts_count'] ?? count($customerMatrix) }} akun pelanggan aktif</span>
            </div>
        </div>

        {{-- Piutang Lancar --}}
        <div class="ar-kpi-card">
            <div class="ar-kpi-top">
                <span class="ar-kpi-label">Piutang Lancar</span>
                <span class="ar-kpi-badge">Belum Jatuh Tempo</span>
            </div>
            <div class="ar-kpi-val">{{ $money($summary['total_not_due'] ?? 0) }}</div>
            <div class="ar-kpi-note">
                <span>{{ $agingBuckets['not_due']['percent'] ?? 0 }}% dari total saldo piutang</span>
            </div>
        </div>

        {{-- Piutang Jatuh Tempo --}}
        <div class="ar-kpi-card">
            <div class="ar-kpi-top">
                <span class="ar-kpi-label">Piutang Lewat Jatuh Tempo</span>
                <span class="ar-kpi-badge text-danger fw-bold">Overdue: {{ $summary['overdue_ratio'] ?? 0 }}%</span>
            </div>
            <div class="ar-kpi-val {{ $overdueRatio > 25 ? 'text-danger' : '' }}">
                {{ $money($summary['total_overdue'] ?? 0) }}
            </div>
            <div class="ar-kpi-note">
                <span>Tagihan membutuhkan follow-up penagihan</span>
            </div>
        </div>

        {{-- Piutang Kritis (>60 Hari) --}}
        <div class="ar-kpi-card">
            <div class="ar-kpi-top">
                <span class="ar-kpi-label">Piutang Kritis (>60 Hari)</span>
                <span class="ar-kpi-badge text-danger fw-bold">Risiko Macet</span>
            </div>
            <div class="ar-kpi-val text-danger">
                {{ $money($summary['total_critical_over_60'] ?? 0) }}
            </div>
            <div class="ar-kpi-note">
                <span>Wajib evaluasi plafon &amp; penahanan pesanan</span>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════════════════
         5 KOLOM DISTRIBUSI UMUR PIUTANG (AGING BUCKETS)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <section class="aging-banner-grid">
        @foreach($agingBuckets as $key => $bucket)
            <div class="aging-bucket-card">
                <div class="aging-bucket-name">{{ $bucket['label'] }}</div>
                <div class="aging-bucket-val">{{ $money($bucket['amount']) }}</div>
                <div class="aging-bucket-meta">
                    <span>{{ $bucket['count'] }} tagihan</span>
                    <strong>{{ $bucket['percent'] }}%</strong>
                </div>
            </div>
        @endforeach
    </section>

    {{-- ══════════════════════════════════════════════════════════════════════════
         PANEL TOP PRIORITAS PENAGIHAN HARI INI (DENGAN AKSI WHATSAPP 1-KLIK)
         ══════════════════════════════════════════════════════════════════════════ --}}
    @if(!empty($priorityList))
        <article class="card-dash panel mb-4">
            <div class="panel-head mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="sq red" style="width:34px;height:34px;"><svg class="ico" style="width:18px;height:18px;"><use href="#i-alert"/></svg></span>
                    <div>
                        <h3 class="text-danger m-0">Top Prioritas Penagihan Hari Ini</h3>
                        <p class="m-0">Tagihan lewat jatuh tempo tertua dan terbesar yang memerlukan tindakan penagihan segera.</p>
                    </div>
                </div>
                <span class="badge bg-light-danger text-danger fw-bold fs-8">Wajib Ditagih</span>
            </div>

            <div class="priority-action-grid">
                @foreach($priorityList as $p)
                    <div class="priority-action-card">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-light-danger text-danger fw-bold fs-8">
                                    Lewat {{ $p['days_late'] }} Hari
                                </span>
                                <span class="font-monospace fs-8 text-muted">Jatuh Tempo: {{ \Carbon\Carbon::parse($p['due_date'])->format('d/m/Y') }}</span>
                            </div>
                            <h5 class="fs-6 fw-bold text-dark m-0 mb-1 text-truncate" title="{{ $p['customer_name'] }}">
                                {{ $p['customer_name'] }}
                            </h5>
                            <div class="text-muted fs-8 mb-2">
                                <span>No: <strong>{{ $p['number'] }}</strong></span> • <span>PIC: {{ $p['pic_name'] }}</span>
                            </div>
                            <div class="fs-5 fw-bold text-danger font-monospace mb-3">
                                {{ $money($p['outstanding']) }}
                            </div>
                        </div>

                        <div>
                            @if(!empty($p['wa_link']))
                                <a href="{{ $p['wa_link'] }}" target="_blank" class="btn-whatsapp" title="Buka WhatsApp dengan pesan penagihan otomatis">
                                    <svg class="ico" style="width:14px;height:14px;"><use href="#i-whatsapp"/></svg>
                                    <span>Ingatkan via WhatsApp</span>
                                </a>
                            @else
                                <button type="button" class="btn btn-sm btn-light-secondary w-100 fs-8 text-muted" disabled>
                                    Nomor WA Belum Ada
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════════
         MATRIKS AGING RECEIVABLES PER PELANGGAN B2B
         ══════════════════════════════════════════════════════════════════════════ --}}
    <article class="card-dash panel mb-4">
        <div class="panel-head mb-3">
            <div>
                <h3>Matriks Umur Piutang per Pelanggan B2B</h3>
                <p>Pengawasan saldo tagihan per bucket umur, plafon kredit (*Credit Limit*), dan tingkat risiko kredit.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-wrap" style="width: 220px;">
                    <input type="text" id="search-customer" class="form-control form-control-sm" placeholder="Cari nama pelanggan / PIC...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="aging-matrix-table" id="table-aging">
                <thead>
                    <tr>
                        <th>Pelanggan B2B &amp; PIC</th>
                        <th style="width: 140px;">Plafon Kredit</th>
                        <th class="text-end" style="width: 130px;">Total Saldo</th>
                        <th class="text-end" style="width: 120px;">Belum Tempo</th>
                        <th class="text-end" style="width: 105px;">1 – 7 Hari</th>
                        <th class="text-end" style="width: 105px;">8 – 30 Hari</th>
                        <th class="text-end" style="width: 105px;">31 – 60 Hari</th>
                        <th class="text-end" style="width: 105px;">&gt; 60 Hari</th>
                        <th class="text-center" style="width: 125px;">Status Risiko</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customerMatrix as $c)
                        @php
                            $utilNum = (float) $c['utilization_percent'];
                            $utilClass = match(true) {
                                $utilNum >= 95.0 => 'danger',
                                $utilNum >= 80.0 => 'warning',
                                default          => 'safe',
                            };
                        @endphp
                        <tr class="customer-row">
                            <td>
                                <div class="fw-bold text-dark fs-7 customer-name">{{ $c['customer_name'] }}</div>
                                <div class="text-muted fs-8">
                                    <span>PIC: {{ $c['pic_name'] }}</span>
                                    @if($c['whatsapp_number'] !== '-')
                                        <span> • <span class="font-monospace">{{ $c['whatsapp_number'] }}</span></span>
                                    @endif
                                    <span> • TOP: {{ $c['payment_term_days'] }} Hari</span>
                                </div>
                            </td>
                            <td>
                                <div class="font-monospace fs-8 text-dark">
                                    {{ (float)$c['credit_limit'] > 0 ? $money($c['credit_limit']) : 'Tanpa Limit' }}
                                </div>
                                @if((float)$c['credit_limit'] > 0)
                                    <div class="d-flex align-items-center justify-content-between fs-8 font-monospace text-muted mt-1">
                                        <span>Utilitas: <strong>{{ $c['utilization_percent'] }}%</strong></span>
                                    </div>
                                    <div class="util-bar-wrap">
                                        <div class="util-bar-fill {{ $utilClass }}" style="width: {{ min(100, $utilNum) }}%;"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark fs-7">
                                {{ $money($c['total_outstanding']) }}
                            </td>
                            <td class="text-end font-monospace {{ (float)$c['not_due'] > 0 ? 'text-success fw-bold' : 'text-muted' }}">
                                {{ (float)$c['not_due'] > 0 ? $money($c['not_due']) : '-' }}
                            </td>
                            <td class="text-end font-monospace {{ (float)$c['1_7'] > 0 ? 'text-primary fw-bold' : 'text-muted' }}">
                                {{ (float)$c['1_7'] > 0 ? $money($c['1_7']) : '-' }}
                            </td>
                            <td class="text-end font-monospace {{ (float)$c['8_30'] > 0 ? 'text-warning fw-bold' : 'text-muted' }}">
                                {{ (float)$c['8_30'] > 0 ? $money($c['8_30']) : '-' }}
                            </td>
                            <td class="text-end font-monospace {{ (float)$c['31_60'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                                {{ (float)$c['31_60'] > 0 ? $money($c['31_60']) : '-' }}
                            </td>
                            <td class="text-end font-monospace {{ (float)$c['over_60'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                                {{ (float)$c['over_60'] > 0 ? $money($c['over_60']) : '-' }}
                            </td>
                            <td class="text-center">
                                <span class="risk-tag {{ $c['risk_status'] }}">
                                    {{ $c['risk_label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                Tidak ada data piutang berjalan pada periode evaluasi terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     INTERACTIVE JAVASCRIPT: INSTANT SEARCH PELANGGAN
     ══════════════════════════════════════════════════════════════════════════ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('search-customer');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.customer-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>
