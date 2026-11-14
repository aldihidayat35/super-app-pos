@php
    use App\Support\CurrencyFormatter;
    use App\Support\Decimal;

    $summary          = $report['summary'] ?? [];
    $sptMasaLines     = $report['spt_masa_lines'] ?? [];
    $coretaxDocs      = $report['coretax_documents'] ?? [];
    $reconcileStats   = $report['reconciliation_stats'] ?? [];
    $deadlines        = $report['deadlines'] ?? [];

    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y')
                 .' – '
                 .\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');

    $money = fn($v): string => $v === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string)$v);
    $fmtPercent = fn($v): string => $v === null ? 'Akses terbatas' : str_replace('.', ',', (string)$v).'%';

    $taxStatus = $summary['tax_status'] ?? 'nihil';
    $finalTaxPos = (float) ($summary['final_tax_position'] ?? 0);
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<style>
/* ══════════════════════════════════════════════════════════════════════════
   CUSTOM STYLES FOR EXECUTIVE TAX & CORETAX REPORT
   ══════════════════════════════════════════════════════════════════════════ */
.tax-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
@media (max-width: 991px) {
    .tax-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 575px) {
    .tax-kpi-grid {
        grid-template-columns: 1fr;
    }
}
.tax-kpi-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 125px;
    position: relative;
    overflow: hidden;
}
.tax-kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--card-accent, #387bd5);
}
.tax-kpi-card.tone-blue  { --card-accent: #2563eb; }
.tax-kpi-card.tone-green { --card-accent: #059669; }
.tax-kpi-card.tone-red   { --card-accent: #dc2626; }
.tax-kpi-card.tone-gold  { --card-accent: #d97706; }
.tax-kpi-card.tone-slate { --card-accent: #64748b; }

.tax-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.tax-kpi-label {
    font-size: 12px;
    font-weight: 750;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.tax-kpi-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    background: #f1f5f9;
    color: #334155;
}
.tax-kpi-val {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -.02em;
    line-height: 1.15;
    margin-bottom: 6px;
    font-variant-numeric: tabular-nums;
}
.tax-kpi-note {
    font-size: 11px;
    color: #64748b;
    line-height: 1.35;
}

/* Tabel SPT Masa PPN 1111 */
.tax-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
}
.tax-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: 12px 16px;
    border-bottom: 1px solid var(--line);
}
.tax-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #edf2f7;
    color: var(--dark);
    vertical-align: middle;
}
.tax-table tr:hover td {
    background: #fbfcfe;
}
.tax-section-title td {
    background: #edf3f9;
    font-weight: 800;
    color: #1a365d;
    font-size: 12px;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding-top: 13px;
    padding-bottom: 9px;
    border-top: 2px solid #d8e2ee;
    border-bottom: 1px solid #d8e2ee;
}
.tax-row-total td {
    background: #f8fafc;
    font-weight: 750;
    color: #0f172a;
    border-top: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
}
.tax-row-highlight td {
    background: #eff6ff;
    font-weight: 800;
    color: #1e3a8a;
    border-top: 2px solid #3b82f6;
    border-bottom: 2px solid #3b82f6;
}
.tax-status-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 750;
    letter-spacing: .02em;
}
.tax-status-tag.kurang-bayar {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
.tax-status-tag.lebih-bayar {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.tax-status-tag.nihil {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

/* Coretax Table Styles */
.coretax-tag {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.coretax-tag.matched { background: #dcfce7; color: #15803d; }
.coretax-tag.reconciled { background: #e0f2fe; color: #0369a1; }
.coretax-tag.unmatched { background: #fee2e2; color: #b91c1c; }
.coretax-tag.pending { background: #fef3c7; color: #b45309; }

.coretax-dir {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 10.5px;
    font-weight: 700;
}
.coretax-dir.out { background: #eff6ff; color: #1d4ed8; }
.coretax-dir.in  { background: #f3f4f6; color: #374151; }

/* Grid Informasi PPh & Jadwal DJP */
.tax-info-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 16px;
    margin-top: 18px;
}
@media (max-width: 991px) {
    .tax-info-grid {
        grid-template-columns: 1fr;
    }
}
.tax-scheme-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: var(--rad);
    padding: 18px 20px;
    box-shadow: var(--shadow);
}
.tax-scheme-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px dashed #e2e8f0;
}
.tax-scheme-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.deadline-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}
.deadline-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.deadline-icon {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    flex: none;
}
.deadline-icon.warning { background: #fef3c7; color: #d97706; }
.deadline-icon.primary { background: #e0f2fe; color: #0284c7; }
.deadline-icon.success { background: #dcfce7; color: #16a34a; }

/* Print Media */
@media print {
    .hero-actions, .filters, .btn-dash, .search-box-wrap { display: none !important; }
    .tax-kpi-card, .tax-scheme-card { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
    .tax-info-grid { grid-template-columns: 1fr 1fr !important; }
}
</style>

{{-- SVG ICONS SYMBOL DEFINITIONS --}}
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-shield-tick" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-coins" viewBox="0 0 24 24"><ellipse cx="10" cy="6" rx="7" ry="3"/><path d="M3 6v5c0 1.7 3.1 3 7 3m7-8v4M3 11v5c0 1.7 3.1 3 7 3m0-5c0-1.7 2.5-3 5.5-3s5.5 1.3 5.5 3-2.5 3-5.5 3-5.5-1.3-5.5-3Zm0 0v4c0 1.7 2.5 3 5.5 3s5.5-1.3 5.5-3v-4"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></symbol>
    <symbol id="i-print" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></symbol>
    <symbol id="i-filter" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></symbol>
    <symbol id="i-building" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="9" y1="22" x2="9" y2="2"/><line x1="8" y1="6" x2="10" y2="6"/><line x1="14" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="10" y2="10"/><line x1="14" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="10" y2="14"/><line x1="14" y1="14" x2="16" y2="14"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18M8 16v-4m5 4V8m5 8V5"/></symbol>
    <symbol id="i-file-text" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></symbol>
</svg>

<div class="gudang-dashboard-wrap">

    {{-- ══════════════════════════════════════════════════════════════════════════
         HERO SECTION: EXECUTIVE TAX & COMPLIANCE
         ══════════════════════════════════════════════════════════════════════════ --}}
    <header class="hero" aria-labelledby="title">
        <svg class="hero-decor" viewBox="0 0 460 210" aria-hidden="true">
            <defs>
                <pattern id="tax-grid" width="22" height="22" patternUnits="userSpaceOnUse"><path d="M22 0H0v22" stroke="#b6cce8" stroke-opacity=".12"/></pattern>
            </defs>
            <rect width="460" height="210" fill="url(#tax-grid)"/>
        </svg>

        <div>
            <div class="hero-badge">
                <svg class="ico"><use href="#i-shield-tick"/></svg>
                <span>EXECUTIVE TAX &amp; COMPLIANCE SUITE</span>
            </div>
            <h1 id="title">Rekapitulasi Pajak</h1>
            <p>Rekapitulasi SPT Masa PPN 1111, Rekonsiliasi Faktur Coretax DJP, dan Estimasi PPh Usaha.</p>

            {{-- 3-BUTTON HERO SWITCHER --}}
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
                   class="btn-dash gold py-1 px-3" style="min-height:30px; font-size:11px;">
                    <svg class="ico me-1" style="width:13px;height:13px;"><use href="#i-shield-tick"/></svg>
                    Rekapitulasi Pajak
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
                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'tax']) }}" class="d-inline-flex gap-2">
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

                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'tax']) }}" class="d-inline-flex">
                        @csrf
                        @foreach(request()->except(['format','_token']) as $n => $v)
                            @if(is_scalar($v))<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="hidden" name="format" value="xlsx">
                        <button type="submit" class="btn-dash gold" title="Unduh Spreadsheet Excel">
                            <span>Excel</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'tax']) }}" class="d-inline-flex">
                        @csrf
                        @foreach(request()->except(['format','_token']) as $n => $v)
                            @if(is_scalar($v))<input type="hidden" name="{{ $n }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="hidden" name="format" value="csv">
                        <button type="submit" class="btn-dash clear" style="background:#fff; color:#0f172a;" title="Ekspor Format Impor CSV Coretax DJP">
                            <svg class="ico"><use href="#i-file-text"/></svg>
                            <span>Coretax CSV</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    {{-- ══════════════════════════════════════════════════════════════════════════
         FILTER PARAMETER
         ══════════════════════════════════════════════════════════════════════════ --}}
    <form method="GET" action="{{ route('reports.profiles.owner.show', ['report' => 'tax']) }}" class="filters">
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
            <label for="f-loc">Lokasi Operasional</label>
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
            <a href="{{ route('reports.profiles.owner.show', ['report' => 'tax']) }}" class="btn-dash clear">
                <span>Reset</span>
            </a>
        </div>
    </form>

    {{-- ══════════════════════════════════════════════════════════════════════════
         4 KARTU KPI PERPAJAKAN UTAMA (BERSIH & MODERN TANPA GRAFIK GARIS)
         ══════════════════════════════════════════════════════════════════════════ --}}
    <section class="tax-kpi-grid">
        {{-- PPN Keluaran --}}
        <div class="tax-kpi-card tone-blue">
            <div class="tax-kpi-top">
                <span class="tax-kpi-label">PPN Keluaran</span>
                <span class="tax-kpi-badge">Output Tax</span>
            </div>
            <div class="tax-kpi-val">{{ $money($summary['output_tax'] ?? 0) }}</div>
            <div class="tax-kpi-note">
                <span>DPP Penjualan: {{ $money($summary['output_dpp'] ?? 0) }}</span>
            </div>
        </div>

        {{-- PPN Masukan --}}
        <div class="tax-kpi-card tone-slate">
            <div class="tax-kpi-top">
                <span class="tax-kpi-label">PPN Masukan Dapat Dikreditkan</span>
                <span class="tax-kpi-badge">Creditable Input</span>
            </div>
            <div class="tax-kpi-val">{{ $money($summary['input_tax_creditable'] ?? 0) }}</div>
            <div class="tax-kpi-note">
                <span>DPP Pembelian: {{ $money($summary['input_dpp'] ?? 0) }}</span>
            </div>
        </div>

        {{-- Posisi SPT Masa PPN --}}
        @php
            $tonePos = match($taxStatus) {
                'kurang_bayar' => 'tone-red',
                'lebih_bayar'  => 'tone-green',
                default        => 'tone-slate',
            };
            $tagPos = match($taxStatus) {
                'kurang_bayar' => 'kurang-bayar',
                'lebih_bayar'  => 'lebih-bayar',
                default        => 'nihil',
            };
        @endphp
        <div class="tax-kpi-card {{ $tonePos }}">
            <div class="tax-kpi-top">
                <span class="tax-kpi-label">Posisi SPT Masa PPN</span>
                <span class="tax-status-tag {{ $tagPos }}">{{ $summary['tax_status_label'] ?? 'Nihil' }}</span>
            </div>
            <div class="tax-kpi-val">
                {{ $money(abs($finalTaxPos)) }}
            </div>
            <div class="tax-kpi-note">
                @if($taxStatus === 'kurang_bayar')
                    <span class="text-danger fw-bold">Wajib disetor ke kas negara sebelum akhir bulan</span>
                @elseif($taxStatus === 'lebih_bayar')
                    <span class="text-success fw-bold">Dapat dikompensasikan ke masa pajak berikutnya</span>
                @else
                    <span>Tidak ada pajak kurang atau lebih bayar</span>
                @endif
            </div>
        </div>

        {{-- Estimasi PPh Final UMKM --}}
        <div class="tax-kpi-card tone-gold">
            <div class="tax-kpi-top">
                <span class="tax-kpi-label">Estimasi PPh Final UMKM (0,5%)</span>
                <span class="tax-kpi-badge">PP 55/2022</span>
            </div>
            <div class="tax-kpi-val">{{ $money($summary['pph_final_umkm'] ?? 0) }}</div>
            <div class="tax-kpi-note">
                <span>Berdasarkan Omzet Bruto: {{ $money($summary['gross_turnover'] ?? 0) }}</span>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════════════════
         TABEL REKAPITULASI FORMULIR INDUK SPT MASA PPN 1111
         ══════════════════════════════════════════════════════════════════════════ --}}
    <article class="card-dash panel mb-4">
        <div class="panel-head mb-3">
            <div>
                <h3>Formulir Induk Rekapitulasi SPT Masa PPN 1111</h3>
                <p>Kompilasi resmi penyerahan BKP/JKP, Pajak Keluaran, Pajak Masukan, dan Posisi Kurang/Lebih Bayar.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="tax-status-tag {{ $tagPos }}">
                    Status: {{ $summary['tax_status_label'] ?? 'Nihil' }}
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="tax-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">Kode</th>
                        <th>Uraian Komponen SPT Masa PPN</th>
                        <th class="text-end" style="width: 170px;">Dasar Pengenaan Pajak (DPP)</th>
                        <th class="text-end" style="width: 170px;">Pajak Terutang (PPN)</th>
                        <th style="width: 250px;">Keterangan &amp; Kontrak Peraturan</th>
                    </tr>
                </thead>
                <tbody>
                    @php $currentSection = null; @endphp
                    @foreach($sptMasaLines as $line)
                        @if(($line['section'] ?? null) !== $currentSection)
                            @php $currentSection = $line['section'] ?? null; @endphp
                            <tr class="tax-section-title">
                                <td colspan="5">{{ $currentSection }}</td>
                            </tr>
                        @endif

                        @php
                            $rowClass = '';
                            if (!empty($line['is_highlight'])) {
                                $rowClass = 'tax-row-highlight';
                            } elseif (!empty($line['is_total'])) {
                                $rowClass = 'tax-row-total';
                            }
                        @endphp

                        <tr class="{{ $rowClass }}">
                            <td class="fw-bold text-muted">{{ $line['code'] }}</td>
                            <td>
                                <span class="{{ !empty($line['is_total']) ? 'fw-bold text-dark' : '' }}">
                                    {{ $line['description'] }}
                                </span>
                            </td>
                            <td class="text-end font-monospace">
                                {{ $line['dpp'] !== null ? $money($line['dpp']) : '-' }}
                            </td>
                            <td class="text-end font-monospace {{ !empty($line['is_highlight']) ? 'fw-bold fs-6' : '' }}">
                                {{ $money($line['tax']) }}
                            </td>
                            <td>
                                <span class="text-muted fs-8">{{ $line['notes'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </article>

    {{-- ══════════════════════════════════════════════════════════════════════════
         REKONSILIASI FAKTUR PAJAK CORETAX DJP
         ══════════════════════════════════════════════════════════════════════════ --}}
    <article class="card-dash panel mb-4">
        <div class="panel-head mb-3">
            <div>
                <h3>Rekonsiliasi Faktur Pajak Coretax DJP</h3>
                <p>Daftar faktur pajak keluaran &amp; masukan yang siap divalidasi dan diimpor ke sistem Coretax DJP.</p>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2 font-monospace fs-8 text-muted">
                    <span>Total Dokumen: <strong>{{ $reconcileStats['total_count'] ?? count($coretaxDocs) }}</strong></span>
                    <span>•</span>
                    <span class="text-success">Matched: <strong>{{ $reconcileStats['matched_count'] ?? 0 }}</strong> ({{ $reconcileStats['match_rate_percent'] ?? 100 }}%)</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="tax-table" id="table-coretax">
                <thead>
                    <tr>
                        <th style="width: 105px;">Tanggal</th>
                        <th>Nomor Faktur / Coretax Ref</th>
                        <th style="width: 85px;">Arah</th>
                        <th>Lawan Transaksi</th>
                        <th style="width: 175px;">NPWP / NIK 16 Digit</th>
                        <th class="text-end" style="width: 135px;">DPP</th>
                        <th class="text-end" style="width: 130px;">PPN (11%)</th>
                        <th class="text-center" style="width: 105px;">Status Coretax</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coretaxDocs as $doc)
                        <tr>
                            <td class="text-muted fs-8 font-monospace">
                                {{ \Carbon\Carbon::parse($doc['tax_date'])->format('d/m/Y') }}
                            </td>
                            <td>
                                <div class="fw-bold text-dark font-monospace fs-8">{{ $doc['document_number'] }}</div>
                                <div class="text-muted fs-8 font-monospace">{{ $doc['coretax_reference'] }}</div>
                            </td>
                            <td>
                                <span class="coretax-dir {{ $doc['direction'] }}">
                                    {{ $doc['direction_label'] }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $doc['counterparty_name'] }}</div>
                                <div class="text-muted fs-8">{{ $doc['location_name'] }}</div>
                            </td>
                            <td class="font-monospace fs-8 text-muted">
                                {{ $doc['counterparty_tax_number'] }}
                            </td>
                            <td class="text-end font-monospace">
                                {{ $money($doc['dpp_amount']) }}
                            </td>
                            <td class="text-end font-monospace fw-bold">
                                {{ $money($doc['tax_amount']) }}
                            </td>
                            <td class="text-center">
                                @php
                                    $tagStatus = match($doc['reconciliation_status']) {
                                        'reconciled' => 'reconciled',
                                        'unmatched'  => 'unmatched',
                                        'pending'    => 'pending',
                                        default      => 'matched',
                                    };
                                    $tagLabel = match($doc['reconciliation_status']) {
                                        'reconciled' => 'Reconciled',
                                        'unmatched'  => 'Unmatched',
                                        'pending'    => 'Pending',
                                        default      => 'Matched',
                                    };
                                @endphp
                                <span class="coretax-tag {{ $tagStatus }}">
                                    {{ $tagLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                Belum ada faktur pajak atau transaksi kena pajak pada periode terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    {{-- ══════════════════════════════════════════════════════════════════════════
         INFORMASI KOMPARASI SKEMA PPh & KALENDER BATAS WAKTU DJP
         ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="tax-info-grid mb-4">
        {{-- Komparasi Skema PPh --}}
        <div class="tax-scheme-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h3 class="fs-6 fw-bold text-dark m-0">Simulasi &amp; Komparasi Skema Pajak Penghasilan (PPh)</h3>
                    <p class="text-muted fs-8 m-0">Evaluasi efisiensi beban pajak usaha antara skema UMKM vs PPh Badan.</p>
                </div>
                <span class="badge bg-light-primary text-primary fw-bold fs-8">Perencanaan Pajak</span>
            </div>

            <div class="tax-scheme-item">
                <div>
                    <div class="fw-bold text-dark">Skema 1: PPh Final UMKM 0,5% (PP 55/2022)</div>
                    <div class="text-muted fs-8">0,5% langsung dari total peredaran bruto tanpa memperhatikan biaya/beban</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold fs-6 text-dark font-monospace">{{ $money($summary['pph_final_umkm'] ?? 0) }}</div>
                    <span class="badge bg-light-success text-success fs-8">Aktif / Disarankan</span>
                </div>
            </div>

            <div class="tax-scheme-item">
                <div>
                    <div class="fw-bold text-dark">Skema 2: Fasilitas Pasal 31E UU HPP (Tarif Efektif 11%)</div>
                    <div class="text-muted fs-8">Pengurangan tarif 50% dari 22% atas laba kena pajak s.d. omzet Rp 4,8 M</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold fs-6 text-dark font-monospace">
                        {{ $canViewSensitive ? $money($summary['pph_badan_31e'] ?? 0) : 'Akses terbatas' }}
                    </div>
                    <span class="text-muted fs-8">Simulasi Badan</span>
                </div>
            </div>

            <div class="tax-scheme-item">
                <div>
                    <div class="fw-bold text-dark">Skema 3: PPh Badan Normal (Tarif 22%)</div>
                    <div class="text-muted fs-8">Tarif umum badan usaha dihitung dari laba bersih komersial setelah koreksi fiskal</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold fs-6 text-dark font-monospace">
                        {{ $canViewSensitive ? $money($summary['pph_badan_normal'] ?? 0) : 'Akses terbatas' }}
                    </div>
                    <span class="text-muted fs-8">Tarif Normal</span>
                </div>
            </div>

            @if(Decimal::compare((string)($summary['withholding_pph21'] ?? 0), '0', 2) > 0 || Decimal::compare((string)($summary['withholding_pph23'] ?? 0), '0', 2) > 0)
                <div class="tax-scheme-item">
                    <div>
                        <div class="fw-bold text-dark">Withholding Taxes (PPh 21 / PPh 23)</div>
                        <div class="text-muted fs-8">Pemotongan pajak atas gaji karyawan atau jasa/sewa pihak ketiga</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-dark font-monospace">
                            PPh 21: {{ $money($summary['withholding_pph21'] ?? 0) }} | PPh 23: {{ $money($summary['withholding_pph23'] ?? 0) }}
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Kalender Batas Waktu Setor/Lapor DJP --}}
        <div class="tax-scheme-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h3 class="fs-6 fw-bold text-dark m-0">Kalender Kepatuhan Pajak (DJP)</h3>
                    <p class="text-muted fs-8 m-0">Jadwal resmi batas waktu penyetoran &amp; pelaporan SPT.</p>
                </div>
                <span class="badge bg-light-warning text-warning fw-bold fs-8">Waspada Denda</span>
            </div>

            @foreach($deadlines as $d)
                <div class="deadline-item">
                    <div class="deadline-icon {{ $d['status'] }}">
                        <svg class="ico" style="width:18px;height:18px;"><use href="#i-calendar"/></svg>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-bold text-dark fs-7">{{ $d['title'] }}</span>
                            <span class="badge bg-light-dark text-dark font-monospace fs-8">{{ $d['date'] }}</span>
                        </div>
                        <div class="text-muted fs-8 mt-1">{{ $d['description'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</div>
