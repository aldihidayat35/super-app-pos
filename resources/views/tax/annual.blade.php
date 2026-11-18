@extends('layouts.metronic.app')

@section('title', 'Pusat Pajak & Keuangan Fiskal Terpadu')

@push('styles')
<style>
/* ══════════════════════════════════════════════════════════════════════════
   UNIFIED TAX HUB STYLING (METRONIC 8 ENHANCED)
   ══════════════════════════════════════════════════════════════════════════ */
.tax-hub-header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 14px;
    padding: 24px 28px;
    color: #fff;
    margin-bottom: 24px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
}
.tax-hub-header h1 {
    font-size: 24px;
    font-weight: 800;
    color: #f8fafc;
    letter-spacing: -0.02em;
    margin-bottom: 6px;
}
.tax-hub-header p {
    color: #94a3b8;
    font-size: 13.5px;
    margin-bottom: 0;
}

/* 4 Executive KPI Cards */
.tax-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 991px) {
    .tax-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 575px) {
    .tax-kpi-grid { grid-template-columns: 1fr; }
}

.tax-kpi-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 140px;
    position: relative;
    overflow: hidden;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.tax-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
}
.tax-kpi-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}
.tax-kpi-card.card-turnover::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.tax-kpi-card.card-vat::before { background: linear-gradient(90deg, #ef4444, #f87171); }
.tax-kpi-card.card-vat.lebih-bayar::before { background: linear-gradient(90deg, #10b981, #34d399); }
.tax-kpi-card.card-vat.nihil::before { background: linear-gradient(90deg, #64748b, #94a3b8); }
.tax-kpi-card.card-income::before { background: linear-gradient(90deg, #10b981, #059669); }
.tax-kpi-card.card-compliance::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

.tax-kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.tax-kpi-label {
    font-size: 11.5px;
    font-weight: 750;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.tax-kpi-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
}
.tax-kpi-val {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -.02em;
    line-height: 1.2;
    margin-bottom: 6px;
    font-variant-numeric: tabular-nums;
}
.tax-kpi-note {
    font-size: 11.5px;
    color: #64748b;
    line-height: 1.35;
}

/* Nav Tabs */
.tax-nav-tabs .nav-link {
    font-size: 13.5px;
    font-weight: 700;
    color: #64748b;
    padding: 14px 20px;
    border: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
}
.tax-nav-tabs .nav-link:hover {
    color: #2563eb;
}
.tax-nav-tabs .nav-link.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
    background: transparent;
}
.tax-nav-tabs .nav-link .badge {
    font-size: 10.5px;
    margin-left: 6px;
}

/* Table SPT Masa */
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
    border-bottom: 1px solid #e2e8f0;
}
.tax-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
}
.tax-table tr:hover td {
    background: #f8fafc;
}
.tax-section-title td {
    background: #f1f5f9;
    font-weight: 800;
    color: #1e293b;
    font-size: 12px;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding-top: 14px;
    padding-bottom: 10px;
    border-top: 2px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
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

/* Status Tags */
.tax-status-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 750;
}
.tax-status-tag.kurang-bayar { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.tax-status-tag.lebih-bayar { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.tax-status-tag.nihil { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

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
</style>
@endpush

@section('content')
@php
    use App\Support\CurrencyFormatter;
    use App\Support\Decimal;

    // Helper formatter
    $money = fn($v): string => $v === null ? 'Rp 0' : CurrencyFormatter::rupiah((string)$v);
    $fmtPercent = fn($v): string => $v === null ? '0%' : str_replace('.', ',', (string)$v).'%';

    $sptSummary = $executiveTax['summary'] ?? [];
    $sptLines = $executiveTax['spt_masa_lines'] ?? [];
    $coretaxDocs = $executiveTax['coretax_documents'] ?? [];
    $reconcileStats = $executiveTax['reconciliation_stats'] ?? [];
    $deadlines = $executiveTax['deadlines'] ?? [];

    $taxStatus = $sptSummary['tax_status'] ?? 'nihil';
    $tagPos = match($taxStatus) {
        'kurang_bayar' => 'kurang-bayar',
        'lebih_bayar'  => 'lebih-bayar',
        default        => 'nihil',
    };

    $activeTab = in_array($tab, ['spt', 'financial', 'rates', 'exports'], true) ? $tab : (
        in_array($tab, ['monthly', 'profit', 'balance', 'fiscal', 'summary'], true) ? 'financial' : 'spt'
    );
@endphp

{{-- ══════════════════════════════════════════════════════════════════════════
     HERO HEADER & QUICK ACTIONS
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="tax-hub-header d-flex flex-wrap align-items-center justify-content-between gap-4">
    <div>
        <div class="d-flex align-items-center gap-3 mb-1">
            <h1>Pusat Pajak & Keuangan Fiskal Terpadu</h1>
            <span class="badge badge-light-{{ $year->status === App\Enums\FinancialYearStatus::LOCKED ? 'warning' : 'success' }} fs-8 fw-bold">
                Tahun {{ $year->year }} · {{ $year->status?->label() ?? 'Draft' }}
            </span>
        </div>
        <p>Keuangan &amp; Pajak Tahunan · Monitoring SPT PPN 1111, Rekonsiliasi Faktur Coretax, Simulasi PPh Badan, dan Pengaturan Persentase Tarif Pajak.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('tax.index', array_merge(request()->query(), ['tab' => 'rates', 'year' => $year->year])) }}"
           class="btn btn-primary d-flex align-items-center gap-2 fw-bold shadow-sm">
            <i class="ki-outline ki-percentage fs-4"></i>
            <span>Ubah Persentase Pajak</span>
        </a>
        <a href="{{ route('tax.years.pdf', $year) }}" class="btn btn-outline-light d-flex align-items-center gap-2">
            <i class="ki-outline ki-document fs-4"></i>
            <span>Cetak PDF</span>
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success d-flex align-items-center mb-6">
        <i class="ki-outline ki-check-circle fs-2hx text-success me-4"></i>
        <div><strong>Sukses!</strong> {{ session('success') }}</div>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger d-flex align-items-center mb-6">
        <i class="ki-outline ki-cross-circle fs-2hx text-danger me-4"></i>
        <div>
            <strong>Terjadi kendala pemrosesan:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════
     FILTER BAR (YEAR, DATE RANGE, LOCATION)
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="card card-flush mb-6">
    <div class="card-body py-4">
        <form method="get" action="{{ route('tax.index') }}" class="d-flex flex-wrap align-items-end gap-3">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            
            <div style="min-width: 140px;">
                <label class="form-label fs-7 fw-bold text-gray-700 mb-1">Tahun Laporan</label>
                <select name="year" class="form-select form-select-sm form-select-solid" onchange="this.form.submit()">
                    @foreach($years as $item)
                        <option value="{{ $item->year }}" @selected($year->id === $item->id)>
                            {{ $item->year }} ({{ $item->status?->label() ?? 'Draft' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="min-width: 150px;">
                <label class="form-label fs-7 fw-bold text-gray-700 mb-1">Mulai Tanggal</label>
                <input type="date" name="start_date" class="form-control form-control-sm form-control-solid"
                       value="{{ $filters['start_date'] }}">
            </div>

            <div style="min-width: 150px;">
                <label class="form-label fs-7 fw-bold text-gray-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control form-control-sm form-control-solid"
                       value="{{ $filters['end_date'] }}">
            </div>

            <div style="min-width: 180px;">
                <label class="form-label fs-7 fw-bold text-gray-700 mb-1">Lokasi / Cabang</label>
                <select name="work_location_id" class="form-select form-select-sm form-select-solid">
                    <option value="">Semua Lokasi Konsolidasian</option>
                    @foreach($workLocations as $loc)
                        <option value="{{ $loc->id }}" @selected($filters['work_location_id'] == $loc->id)>
                            {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-sm btn-light-primary fw-bold">
                    <i class="ki-outline ki-filter fs-6 me-1"></i> Terapkan
                </button>
            </div>
            @if(request()->hasAny(['start_date', 'end_date', 'work_location_id']))
                <div>
                    <a href="{{ route('tax.index', ['year' => $year->year, 'tab' => $activeTab]) }}" class="btn btn-sm btn-light text-muted">
                        Reset
                    </a>
                </div>
            @endif
        </form>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     TOP 4 EXECUTIVE TAX KPI CARDS
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="tax-kpi-grid">
    {{-- KPI 1: Total Omzet --}}
    <div class="tax-kpi-card card-turnover">
        <div class="tax-kpi-top">
            <span class="tax-kpi-label">Peredaran Bruto (Omzet)</span>
            <span class="tax-kpi-badge bg-light-primary text-primary">Periode Aktif</span>
        </div>
        <div class="tax-kpi-val">{{ $money($sptSummary['gross_turnover'] ?? 0) }}</div>
        <div class="tax-kpi-note">
            Batas Fasilitas PP 55: Rp 4,8 Miliar.<br>
            @if(Decimal::compare((string)($sptSummary['gross_turnover'] ?? 0), '4800000000', 2) <= 0)
                <span class="text-success fw-bold">Memenuhi syarat PPh Final 0,5%</span>
            @else
                <span class="text-warning fw-bold">Wajib PPh Tarif Umum / 31E</span>
            @endif
        </div>
    </div>

    {{-- KPI 2: Posisi PPN (SPT 1111) --}}
    <div class="tax-kpi-card card-vat {{ $tagPos }}">
        <div class="tax-kpi-top">
            <span class="tax-kpi-label">Posisi PPN (SPT 1111)</span>
            <span class="tax-status-tag {{ $tagPos }}">{{ $sptSummary['tax_status_label'] ?? 'Nihil' }}</span>
        </div>
        <div class="tax-kpi-val">
            {{ $money(abs((float)($sptSummary['final_tax_position'] ?? 0))) }}
        </div>
        <div class="tax-kpi-note">
            Pajak Keluaran: {{ $money($sptSummary['output_tax'] ?? 0) }}<br>
            Masukan Kredit: {{ $money($sptSummary['input_creditable_tax'] ?? 0) }}
        </div>
    </div>

    {{-- KPI 3: Estimasi PPh Terutang --}}
    <div class="tax-kpi-card card-income">
        <div class="tax-kpi-top">
            <span class="tax-kpi-label">Estimasi PPh Terutang</span>
            <span class="tax-kpi-badge bg-light-success text-success">Tarif Aktif</span>
        </div>
        <div class="tax-kpi-val">
            @if($year->tax_scheme === App\Enums\IncomeTaxScheme::FINAL_05)
                {{ $money($sptSummary['pph_final_umkm'] ?? 0) }}
            @elseif($year->tax_scheme === App\Enums\IncomeTaxScheme::ARTICLE_31E)
                {{ $money($sptSummary['pph_badan_31e'] ?? 0) }}
            @elseif($year->tax_scheme === App\Enums\IncomeTaxScheme::GENERAL)
                {{ $money($sptSummary['pph_badan_normal'] ?? 0) }}
            @else
                {{ $money($report['tax'] ?? 0) }}
            @endif
        </div>
        <div class="tax-kpi-note">
            Skema: <strong>{{ $year->tax_scheme?->label() ?? 'Belum ditentukan' }}</strong><br>
            Tarif: Final {{ $year->final_tax_rate_percent }}% · 31E {{ $year->facility_tax_rate_percent }}% · Umum {{ $year->general_tax_rate_percent }}%
        </div>
    </div>

    {{-- KPI 4: Kepatuhan & Kesiapan Coretax --}}
    <div class="tax-kpi-card card-compliance">
        <div class="tax-kpi-top">
            <span class="tax-kpi-label">Kesiapan Dokumen DJP</span>
            <span class="tax-kpi-badge bg-light-info text-info">Coretax</span>
        </div>
        <div class="tax-kpi-val">
            {{ $reconcileStats['matched_count'] ?? 0 }} / {{ $reconcileStats['total_documents'] ?? 0 }}
        </div>
        <div class="tax-kpi-note">
            Rekonsiliasi Faktur: <strong>{{ $reconcileStats['reconciled_percentage'] ?? 0 }}%</strong><br>
            Tutup Buku: <strong>{{ $year->months->where('status', App\Enums\FinancialMonthStatus::LOCKED)->count() }}/12 Bulan Terkunci</strong>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     MAIN UNIFIED HUB TABS
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="card">
    <div class="card-header border-0 pt-2">
        <ul class="nav nav-tabs nav-line-tabs tax-nav-tabs card-header-tabs fs-6">
            <li class="nav-item">
                <a class="nav-link @if($activeTab === 'spt') active @endif"
                   href="{{ route('tax.index', array_merge(request()->query(), ['tab' => 'spt', 'year' => $year->year])) }}">
                    <i class="ki-outline ki-document fs-5 me-2"></i>
                    Kepatuhan SPT PPN 1111 & Coretax
                    <span class="badge badge-light-primary">{{ count($coretaxDocs) }} Dokumen</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if($activeTab === 'financial') active @endif"
                   href="{{ route('tax.index', array_merge(request()->query(), ['tab' => 'financial', 'year' => $year->year])) }}">
                    <i class="ki-outline ki-chart-simple fs-5 me-2"></i>
                    Simulasi PPh Usaha & Pembukuan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if($activeTab === 'rates') active @endif"
                   href="{{ route('tax.index', array_merge(request()->query(), ['tab' => 'rates', 'year' => $year->year])) }}">
                    <i class="ki-outline ki-percentage fs-5 me-2"></i>
                    Konfigurasi Tarif Pajak
                    <span class="badge badge-primary">Input Tarif</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if($activeTab === 'exports') active @endif"
                   href="{{ route('tax.index', array_merge(request()->query(), ['tab' => 'exports', 'year' => $year->year])) }}">
                    <i class="ki-outline ki-file-down fs-5 me-2"></i>
                    Dokumen & Ekspor Coretax
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body pt-5">
        {{-- ──────────────────────────────────────────────────────────────
             TAB 1: KEPATUHAN SPT PPN 1111 & CORETAX
             ────────────────────────────────────────────────────────────── --}}
        @if($activeTab === 'spt')
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6">
                <div>
                    <h3 class="fw-bolder mb-1">Formulir Induk Rekapitulasi SPT Masa PPN 1111</h3>
                    <p class="text-muted fs-7 mb-0">Perhitungan pajak keluaran, pajak masukan, kompensasi kelebihan, dan posisi kurang/lebih bayar.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted fs-7">Status SPT:</span>
                    <span class="tax-status-tag {{ $tagPos }} fs-7">{{ $sptSummary['tax_status_label'] ?? 'Nihil' }}</span>
                </div>
            </div>

            <div class="table-responsive rounded border mb-8">
                <table class="tax-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Kode</th>
                            <th>Uraian Penyerahan / Perhitungan Pajak</th>
                            <th class="text-end" style="width: 200px;">DPP (Dasar Pengenaan)</th>
                            <th class="text-end" style="width: 200px;">PPN Terutang (Rupiah)</th>
                            <th style="width: 260px;">Keterangan & Dasar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $currentSection = null; @endphp
                        @foreach($sptLines as $line)
                            @if($currentSection !== $line['section'])
                                @php $currentSection = $line['section']; @endphp
                                <tr class="tax-section-title">
                                    <td colspan="5">{{ $currentSection }}</td>
                                </tr>
                            @endif
                            <tr @class([
                                'tax-row-total' => !empty($line['is_total']) && empty($line['is_highlight']),
                                'tax-row-highlight' => !empty($line['is_highlight']),
                            ])>
                                <td class="fw-bold">{{ $line['code'] }}</td>
                                <td>{{ $line['description'] }}</td>
                                <td class="text-end font-monospace">
                                    {{ $line['dpp'] !== null ? $money($line['dpp']) : '—' }}
                                </td>
                                <td class="text-end font-monospace fw-bold">
                                    @if(!empty($line['is_highlight']))
                                        <span class="tax-status-tag {{ $tagPos }}">
                                            {{ $money(abs((float)$line['tax'])) }}
                                        </span>
                                    @else
                                        {{ $money($line['tax']) }}
                                    @endif
                                </td>
                                <td class="text-muted fs-7">{{ $line['notes'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Rekonsiliasi Dokumen Faktur Coretax --}}
            <div class="card card-bordered mb-8">
                <div class="card-header bg-light py-4">
                    <div class="card-title d-flex flex-column">
                        <h4 class="mb-1">Daftar Dokumen Faktur Pajak Coretax</h4>
                        <span class="text-muted fs-7">Faktur Keluaran (Sales) & Faktur Masukan (Purchase) yang terdaftar pada sistem DJP.</span>
                    </div>
                    <div class="card-toolbar">
                        <span class="badge badge-light-success fs-7">Tervalidasi: {{ $reconcileStats['matched_count'] ?? 0 }} Dokumen</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered align-middle gs-4 gy-3 mb-0">
                            <thead class="bg-light-secondary fs-7 text-uppercase fw-bold text-gray-600">
                                <tr>
                                    <th>Arah</th>
                                    <th>Nomor Faktur / Dokumen</th>
                                    <th>Lawan Transaksi</th>
                                    <th>NPWP / NIK</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">DPP</th>
                                    <th class="text-end">PPN</th>
                                    <th>Status Coretax</th>
                                </tr>
                            </thead>
                            <tbody class="fs-7">
                                @forelse($coretaxDocs as $doc)
                                    <tr>
                                        <td>
                                            <span class="coretax-dir {{ $doc['direction'] }}">
                                                {{ $doc['direction'] === 'out' ? 'Keluaran' : 'Masukan' }}
                                            </span>
                                        </td>
                                        <td class="fw-bold font-monospace">{{ $doc['document_number'] }}</td>
                                        <td>{{ $doc['counterparty_name'] }}</td>
                                        <td class="font-monospace text-muted">{{ $doc['counterparty_tax_id'] ?: '—' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($doc['tax_date'])->translatedFormat('d M Y') }}</td>
                                        <td class="text-end font-monospace">{{ $money($doc['dpp_amount']) }}</td>
                                        <td class="text-end font-monospace fw-bold">{{ $money($doc['tax_amount']) }}</td>
                                        <td>
                                            <span class="coretax-tag {{ $doc['reconciliation_status'] }}">
                                                {{ ucfirst($doc['reconciliation_status']) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-6 text-muted">
                                            Belum ada dokumen register pajak Coretax pada rentang tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Kalender Jatuh Tempo Perpajakan DJP --}}
            <div class="card card-bordered">
                <div class="card-header bg-light py-3">
                    <h5 class="card-title mb-0">Kalender Jatuh Tempo Perpajakan Resmi DJP</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        @foreach($deadlines as $dl)
                            <div class="col-md-4">
                                <div class="p-4 border rounded bg-light-secondary d-flex align-items-start gap-3">
                                    <div class="badge badge-circle badge-light-{{ $dl['is_urgent'] ?? false ? 'danger' : 'primary' }} p-3">
                                        <i class="ki-outline ki-calendar fs-4"></i>
                                    </div>
                                    <div>
                                        <strong class="text-gray-800 fs-7 d-block mb-1">{{ $dl['title'] }}</strong>
                                        <span class="badge badge-light-dark fs-8 mb-1">Tgl {{ $dl['date'] }}</span>
                                        <p class="text-muted fs-8 mb-0">{{ $dl['description'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        {{-- ──────────────────────────────────────────────────────────────
             TAB 2: SIMULASI PPh USAHA & PEMBUKUAN
             ────────────────────────────────────────────────────────────── --}}
        @elseif($activeTab === 'financial')
            {{-- Komparasi 3 Skema PPh Badan --}}
            <div class="card card-bordered mb-8">
                <div class="card-header bg-light py-4">
                    <div class="card-title d-flex flex-column">
                        <h4 class="mb-1">Komparasi 3 Skema Pajak Penghasilan (PPh) Badan</h4>
                        <span class="text-muted fs-7">Bandingkan beban pajak antara skema UMKM PP 55, Fasilitas Pasal 31E, dan Tarif Normal UU HPP.</span>
                    </div>
                    <div class="card-toolbar">
                        <a href="{{ route('tax.index', array_merge(request()->query(), ['tab' => 'rates', 'year' => $year->year])) }}"
                           class="btn btn-sm btn-light-primary fw-bold">
                            <i class="ki-outline ki-setting-3 fs-6 me-1"></i> Edit Persentase Tarif
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        {{-- Skema 1: PP 55 / UMKM --}}
                        <div class="col-lg-4">
                            <div class="p-5 border rounded h-100 @if($year->tax_scheme === App\Enums\IncomeTaxScheme::FINAL_05) border-primary bg-light-primary @else bg-light-secondary @endif">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge badge-light-dark fw-bold">Skema 1</span>
                                    @if($year->tax_scheme === App\Enums\IncomeTaxScheme::FINAL_05)
                                        <span class="badge badge-primary">SKEMA AKTIF</span>
                                    @endif
                                </div>
                                <h5 class="fw-bolder mb-1">PPh Final PP 55 / UMKM</h5>
                                <div class="text-muted fs-7 mb-3">Tarif: <strong>{{ $year->final_tax_rate_percent }}%</strong> dari Peredaran Bruto Neto</div>
                                <div class="fs-2 fw-bolder text-dark mb-3">{{ $money($sptSummary['pph_final_umkm'] ?? 0) }}</div>
                                <p class="fs-8 text-muted mb-0">Hanya berlaku untuk omzet per tahun hingga Rp 4,8 Miliar. Tidak memperhitungkan beban operasional.</p>
                            </div>
                        </div>

                        {{-- Skema 2: Fasilitas Pasal 31E --}}
                        <div class="col-lg-4">
                            <div class="p-5 border rounded h-100 @if($year->tax_scheme === App\Enums\IncomeTaxScheme::ARTICLE_31E) border-primary bg-light-primary @else bg-light-secondary @endif">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge badge-light-dark fw-bold">Skema 2</span>
                                    @if($year->tax_scheme === App\Enums\IncomeTaxScheme::ARTICLE_31E)
                                        <span class="badge badge-primary">SKEMA AKTIF</span>
                                    @endif
                                </div>
                                <h5 class="fw-bolder mb-1">Fasilitas Pasal 31E UU PPh</h5>
                                <div class="text-muted fs-7 mb-3">Tarif: <strong>{{ $year->facility_tax_rate_percent }}%</strong> (Diskon 50% dari 22%)</div>
                                <div class="fs-2 fw-bolder text-dark mb-3">{{ $money($sptSummary['pph_badan_31e'] ?? 0) }}</div>
                                <p class="fs-8 text-muted mb-0">Mendapat diskon tarif 50% atas PKP bagian omzet hingga Rp 4,8 Miliar dari total omzet maks Rp 50 Miliar.</p>
                            </div>
                        </div>

                        {{-- Skema 3: Tarif Umum UU HPP --}}
                        <div class="col-lg-4">
                            <div class="p-5 border rounded h-100 @if($year->tax_scheme === App\Enums\IncomeTaxScheme::GENERAL) border-primary bg-light-primary @else bg-light-secondary @endif">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge badge-light-dark fw-bold">Skema 3</span>
                                    @if($year->tax_scheme === App\Enums\IncomeTaxScheme::GENERAL)
                                        <span class="badge badge-primary">SKEMA AKTIF</span>
                                    @endif
                                </div>
                                <h5 class="fw-bolder mb-1">Tarif Normal Badan (UU HPP)</h5>
                                <div class="text-muted fs-7 mb-3">Tarif: <strong>{{ $year->general_tax_rate_percent }}%</strong> dari Laba Kena Pajak (PKP)</div>
                                <div class="fs-2 fw-bolder text-dark mb-3">{{ $money($sptSummary['pph_badan_normal'] ?? 0) }}</div>
                                <p class="fs-8 text-muted mb-0">Tarif standar korporasi atas Penghasilan Kena Pajak setelah rekonsiliasi fiskal positif/negatif.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Laba Rugi Komersial & Fiskal --}}
            <div class="row g-6 mb-8">
                <div class="col-lg-6">
                    <div class="card card-bordered h-100">
                        <div class="card-header bg-light py-3">
                            <h5 class="card-title mb-0">Laporan Laba Rugi Fiskal (Tahun {{ $year->year }})</h5>
                        </div>
                        <div class="card-body p-0">
                            @php
                                $profitRows = [
                                    ['Penjualan Bersih', $report['sales'], false],
                                    ['Harga Pokok Penjualan (HPP)', '-'.$report['cogs'], false],
                                    ['Laba Kotor', $report['gross_profit'], true],
                                    ['Beban Operasional', '-'.$report['expenses'], false],
                                    ['Laba Komersial Sebelum Pajak', $report['profit'], true],
                                    ['Koreksi Fiskal Positif', $year->fiscal_positive_adjustment ?? '0', false],
                                    ['Koreksi Fiskal Negatif', '-'.($year->fiscal_negative_adjustment ?? '0'), false],
                                    ['Penghasilan Kena Pajak (PKP)', $report['taxable'], true],
                                    ['Beban PPh Terutang', '-'.$report['tax'], false],
                                    ['Laba Bersih Setelah Pajak', $report['netProfit'], true],
                                ];
                            @endphp
                            <table class="table table-row-bordered align-middle gs-4 gy-3 mb-0">
                                <tbody>
                                    @foreach($profitRows as [$label, $val, $isBold])
                                        <tr @class(['bg-light-primary fw-bolder' => $isBold])>
                                            <td @class(['fw-bold' => $isBold])>{{ $label }}</td>
                                            <td class="text-end font-monospace @class(['text-primary' => $isBold])">
                                                {{ $money($val) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Neraca Ringkas --}}
                <div class="col-lg-6">
                    <div class="card card-bordered h-100">
                        <div class="card-header bg-light py-3">
                            <h5 class="card-title mb-0">Neraca Ringkas per 31 Desember {{ $year->year }}</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold text-uppercase text-muted fs-7 mb-3">1. ASET</h6>
                            <table class="table table-row-dashed gs-0 gy-2 mb-4">
                                <tr>
                                    <td>Kas dan Setara Kas</td>
                                    <td class="text-end font-monospace">{{ $money($report['accounts']['cash_bank'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>Piutang Usaha</td>
                                    <td class="text-end font-monospace">
                                        {{ $money(Decimal::add((string)($year->months->last()?->auto_receivable_balance ?? '0'), (string)($report['accounts']['receivable_adjustment'] ?? '0'), 2)) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>Persediaan Barang</td>
                                    <td class="text-end font-monospace">
                                        {{ $money(Decimal::add((string)($year->months->last()?->auto_inventory_balance ?? '0'), (string)($report['accounts']['inventory_adjustment'] ?? '0'), 2)) }}
                                    </td>
                                </tr>
                                <tr class="fw-bolder bg-light">
                                    <td>Total Aset</td>
                                    <td class="text-end font-monospace text-primary">{{ $money($report['assets'] ?? 0) }}</td>
                                </tr>
                            </table>

                            <h6 class="fw-bold text-uppercase text-muted fs-7 mb-3">2. KEWAJIBAN & EKUITAS</h6>
                            <table class="table table-row-dashed gs-0 gy-2 mb-4">
                                <tr>
                                    <td>Total Kewajiban (Hutang)</td>
                                    <td class="text-end font-monospace">{{ $money($report['liabilities'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <td>Modal Saham & Laba Berjalan</td>
                                    <td class="text-end font-monospace">{{ $money($report['equity'] ?? 0) }}</td>
                                </tr>
                                <tr class="fw-bolder bg-light">
                                    <td>Total Kewajiban & Ekuitas</td>
                                    <td class="text-end font-monospace text-primary">
                                        {{ $money(Decimal::add((string)($report['liabilities'] ?? 0), (string)($report['equity'] ?? 0), 2)) }}
                                    </td>
                                </tr>
                            </table>

                            <div class="d-flex justify-content-between align-items-center p-3 rounded @if(bccomp((string)($report['balance_difference'] ?? '0'), '0', 2) === 0) bg-light-success text-success @else bg-light-danger text-danger @endif">
                                <span class="fw-bold">Selisih Keseimbangan Neraca:</span>
                                <span class="font-monospace fw-bolder">{{ $money($report['balance_difference'] ?? 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rekapitulasi 12 Bulan Pembukuan --}}
            <div class="card card-bordered">
                <div class="card-header bg-light py-4">
                    <div class="card-title d-flex flex-column">
                        <h4 class="mb-1">Rekapitulasi 12 Bulan Pembukuan (Closing Status)</h4>
                        <span class="text-muted fs-7">Proses snapshot bulanan, penginputan koreksi manual, dan penguncian laporan tiap masa pajak.</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered align-middle gs-4 gy-3 mb-0">
                            <thead class="bg-light-secondary fs-7 text-uppercase fw-bold text-gray-600">
                                <tr>
                                    <th>Bulan</th>
                                    <th>Status</th>
                                    <th class="text-end">Penjualan Bersih</th>
                                    <th class="text-end">HPP</th>
                                    <th class="text-end">Beban</th>
                                    <th class="text-end">Laba / Rugi</th>
                                    <th class="text-center" style="width: 280px;">Aksi Pembukuan</th>
                                </tr>
                            </thead>
                            <tbody class="fs-7">
                                @foreach($report['months'] as $row)
                                    <tr>
                                        <td class="fw-bold">{{ DateTime::createFromFormat('!m', $row['month'])->format('F') }}</td>
                                        <td>
                                            <span class="badge badge-light-{{ $row['model']->status === App\Enums\FinancialMonthStatus::LOCKED ? 'success' : ($row['model']->status === App\Enums\FinancialMonthStatus::SUBMITTED ? 'warning' : 'primary') }}">
                                                {{ $row['model']->status->label() }}
                                            </span>
                                        </td>
                                        <td class="text-end font-monospace">{{ $money($row['net_sales']) }}</td>
                                        <td class="text-end font-monospace">{{ $money($row['cogs']) }}</td>
                                        <td class="text-end font-monospace">{{ $money($row['expenses']) }}</td>
                                        <td class="text-end font-monospace fw-bold">{{ $money($row['profit']) }}</td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1 flex-wrap">
                                                @can('finance_annual.manage')
                                                    <form method="post" action="{{ route('tax.months.snapshot', $row['model']) }}">
                                                        @csrf
                                                        <button class="btn btn-xs btn-light-primary">Snapshot</button>
                                                    </form>
                                                    <button class="btn btn-xs btn-light" data-bs-toggle="collapse" data-bs-target="#entry-{{ $row['month'] }}">
                                                        Input
                                                    </button>
                                                @endcan

                                                @if($row['model']->status === App\Enums\FinancialMonthStatus::DRAFT)
                                                    @can('finance_annual.manage')
                                                        <form method="post" action="{{ route('tax.months.transition', $row['model']) }}">
                                                            @csrf
                                                            <input type="hidden" name="action" value="submit">
                                                            <button class="btn btn-xs btn-light-success">Ajukan</button>
                                                        </form>
                                                    @endcan
                                                @else
                                                    @can('finance_annual.approve')
                                                        <form method="post" action="{{ route('tax.months.transition', $row['model']) }}">
                                                            @csrf
                                                            <input type="hidden" name="action" value="{{ $row['model']->status === App\Enums\FinancialMonthStatus::SUBMITTED ? 'lock' : 'reopen' }}">
                                                            @if($row['model']->status === App\Enums\FinancialMonthStatus::LOCKED)
                                                                <input type="hidden" name="reason" value="Dibuka kembali untuk koreksi Kepala Keuangan">
                                                            @endif
                                                            <button class="btn btn-xs btn-light-{{ $row['model']->status === App\Enums\FinancialMonthStatus::SUBMITTED ? 'success' : 'warning' }}">
                                                                {{ $row['model']->status === App\Enums\FinancialMonthStatus::SUBMITTED ? 'Kunci' : 'Buka' }}
                                                            </button>
                                                        </form>
                                                    @endcan
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="collapse bg-light" id="entry-{{ $row['month'] }}">
                                        <td colspan="7" class="p-4">
                                            <form method="post" enctype="multipart/form-data" action="{{ route('tax.months.entries.store', $row['model']) }}" class="row g-2">
                                                @csrf
                                                <div class="col-md-3">
                                                    <select name="account_code" class="form-select form-select-sm">
                                                        @foreach($accounts as $code => $label)
                                                            <option value="{{ $code }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="Nominal" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <input name="reason" class="form-control form-control-sm" placeholder="Alasan atau sumber angka" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="file" name="proof" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-md-1">
                                                    <button class="btn btn-sm btn-primary w-100">Simpan</button>
                                                </div>
                                            </form>
                                            @if($row['model']->entries->isNotEmpty())
                                                <div class="small mt-3">
                                                    @foreach($row['model']->entries as $e)
                                                        <span class="badge badge-light-dark me-2 mb-1">
                                                            {{ $e->label }}: {{ $money($e->amount) }} · {{ $e->reason }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        {{-- ──────────────────────────────────────────────────────────────
             TAB 3: KONFIGURASI PERSENTASE TARIF PAJAK
             ────────────────────────────────────────────────────────────── --}}
        @elseif($activeTab === 'rates')
            @php
                $isLocked = $year->status === App\Enums\FinancialYearStatus::LOCKED;
            @endphp

            @if($isLocked)
                <div class="alert alert-warning d-flex align-items-center mb-6">
                    <i class="ki-outline ki-shield-cross fs-2hx text-warning me-4"></i>
                    <div class="d-flex flex-column">
                        <h5 class="mb-1 text-warning">Tahun Laporan Telah Dikunci (LOCKED)</h5>
                        <span>Persentase tarif PPh Badan untuk tahun {{ $year->year }} dilindungi dan tidak dapat diubah kecuali dibuka kembali (reopen) dengan otorisasi Kepala Keuangan.</span>
                    </div>
                </div>
            @else
                <div class="alert alert-light-primary d-flex align-items-center mb-6">
                    <i class="ki-outline ki-information-5 fs-2hx text-primary me-4"></i>
                    <div class="d-flex flex-column">
                        <h5 class="mb-1 text-primary">Konfigurasi Tarif Pajak & Parameter Fiskal Dinamis</h5>
                        <span>Persentase pajak sering mengalami perubahan regulasi. Atur persentase tarif PPh Badan dan batas peredaran bruto di bawah ini. Nilai laporan tahun berjalan, PPh terutang, dan neraca akan langsung dihitung ulang.</span>
                    </div>
                </div>
            @endif

            {{-- Formulir 1: Parameter Tarif PPh Badan Tahun Berjalan --}}
            <div class="card card-bordered mb-8">
                <div class="card-header bg-light py-4">
                    <div class="card-title d-flex flex-column">
                        <h4 class="mb-1">1. Parameter Tarif PPh Badan (Tahun Buku {{ $year->year }})</h4>
                        <span class="fs-7 text-muted">Sesuaikan persentase tarif pajak penghasilan badan sesuai kebijakan atau regulasi tahun berjalan.</span>
                    </div>
                    @if(!$isLocked)
                        <div class="card-toolbar d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="applyHppPreset()">
                                <i class="ki-outline ki-arrows-circle fs-6 me-1"></i> Standar UU HPP
                            </button>
                            <button type="button" class="btn btn-sm btn-light-success" onclick="applyPp55Preset()">
                                <i class="ki-outline ki-check-circle fs-6 me-1"></i> Standar PP 55 (UMKM)
                            </button>
                        </div>
                    @endif
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('tax.years.rates.update', $year) }}" id="form-tax-rates">
                        @csrf
                        @method('PUT')

                        <div class="row g-5">
                            <div class="col-md-4">
                                <label class="form-label required fw-bold">Tarif PPh Final (PP 55 / UMKM) (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" max="100"
                                           name="final_rate" id="final_rate"
                                           class="form-control font-monospace"
                                           value="{{ old('final_rate', $year->final_rate ?? '0.50') }}"
                                           @disabled($isLocked) required>
                                    <span class="input-group-text">%</span>
                                </div>
                                <span class="fs-8 text-muted mt-1 d-block">Standar nasional: <strong>0,50%</strong> dari peredaran bruto neto tahunan.</span>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label required fw-bold">Tarif Fasilitas Pasal 31E (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" max="100"
                                           name="facility_rate" id="facility_rate"
                                           class="form-control font-monospace"
                                           value="{{ old('facility_rate', $year->facility_rate ?? '11.00') }}"
                                           @disabled($isLocked) required>
                                    <span class="input-group-text">%</span>
                                </div>
                                <span class="fs-8 text-muted mt-1 d-block">Standar nasional: <strong>11,00%</strong> (diskon 50% dari tarif normal 22%).</span>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label required fw-bold">Tarif Umum PPh Badan (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" max="100"
                                           name="general_rate" id="general_rate"
                                           class="form-control font-monospace"
                                           value="{{ old('general_rate', $year->general_rate ?? '22.00') }}"
                                           @disabled($isLocked) required>
                                    <span class="input-group-text">%</span>
                                </div>
                                <span class="fs-8 text-muted mt-1 d-block">Standar normal UU Harmonisasi Peraturan Perpajakan (UU HPP): <strong>22,00%</strong>.</span>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required fw-bold">Batas Omzet Fasilitas Penuh (Rupiah)</label>
                                <input type="number" step="1" min="0"
                                       name="turnover_threshold_facility" id="threshold_facility"
                                       class="form-control font-monospace"
                                       value="{{ old('turnover_threshold_facility', $year->turnover_threshold_facility ?? '4800000000') }}"
                                       @disabled($isLocked) required>
                                <span class="fs-8 text-muted mt-1 d-block">Default batas PP 55 & batas fasilitas penuh Pasal 31E: <strong>Rp 4.800.000.000</strong>.</span>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label required fw-bold">Batas Omzet Fasilitas Sebagian (Rupiah)</label>
                                <input type="number" step="1" min="0"
                                       name="turnover_threshold_partial" id="threshold_partial"
                                       class="form-control font-monospace"
                                       value="{{ old('turnover_threshold_partial', $year->turnover_threshold_partial ?? '50000000000') }}"
                                       @disabled($isLocked) required>
                                <span class="fs-8 text-muted mt-1 d-block">Batas atas peredaran bruto untuk memperoleh fasilitas Pasal 31E: <strong>Rp 50.000.000.000</strong>.</span>
                            </div>

                            @if(!$isLocked)
                                <div class="col-12 text-end pt-3">
                                    <button type="button" class="btn btn-primary fw-bold" onclick="confirmSaveRates()">
                                        <i class="ki-outline ki-check fs-5 me-1"></i> Simpan & Hitung Ulang Laporan
                                    </button>
                                </div>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- Formulir 2: Master Aturan Pajak Transaksi Bertanggal Efektif (PPN/PBJT) --}}
            <div class="card card-bordered">
                <div class="card-header bg-light py-4">
                    <div class="card-title d-flex flex-column">
                        <h4 class="mb-1">2. Master Aturan Pajak Transaksi (Bertanggal Efektif)</h4>
                        <span class="fs-7 text-muted">Daftar tarif PPN, PBJT, dan PPh pemotongan bertanggal efektif untuk transaksi kasir (POS) dan faktur B2B.</span>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-light-success fw-bold" data-bs-toggle="collapse" data-bs-target="#collapse-add-rule">
                            <i class="ki-outline ki-plus fs-6 me-1"></i> Tambah Aturan Baru
                        </button>
                    </div>
                </div>

                {{-- Form Tambah Aturan Pajak Baru (Collapsible) --}}
                <div class="collapse border-bottom bg-light-primary p-6" id="collapse-add-rule">
                    <form method="post" action="{{ route('tax.rules.store') }}" id="form-rule-store">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year->year }}">
                        <input type="hidden" name="is_active" value="1">
                        <input type="hidden" name="is_creditable" value="1">
                        <h5 class="mb-4 text-primary">Tambah Aturan Tarif Transaksi Baru</h5>
                        <div class="row g-4">
                            <div class="col-md-2">
                                <label class="form-label required fw-bold">Kode Aturan</label>
                                <input name="code" id="rule_code" class="form-control form-control-sm font-monospace text-uppercase" placeholder="contoh: PPN_12" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required fw-bold">Nama Aturan</label>
                                <input name="name" id="rule_name" class="form-control form-control-sm" placeholder="contoh: PPN BKP/JKP 12%" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required fw-bold">Tipe Pajak</label>
                                <select name="tax_type" class="form-select form-select-sm" required>
                                    @foreach($taxTypes as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required fw-bold">Arah Transaksi</label>
                                <select name="direction" class="form-select form-select-sm" required>
                                    <option value="both">Masukan & Keluaran</option>
                                    <option value="output">Keluaran Saja</option>
                                    <option value="input">Masukan Saja</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label required fw-bold">Tarif (%)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.0001" min="0" max="100" name="rate" id="rule_rate" class="form-control font-monospace" placeholder="12" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required fw-bold">Faktor DPP</label>
                                <input type="number" step="0.00000001" min="0.00000001" max="1" name="dpp_factor" class="form-control form-control-sm font-monospace" value="1.00000000" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required fw-bold">Berlaku Mulai</label>
                                <input type="date" name="effective_from" class="form-control form-control-sm" value="{{ now('Asia/Jakarta')->toDateString() }}" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Berlaku Sampai</label>
                                <input type="date" name="effective_until" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Keterangan / Dasar Regulasi</label>
                                <input name="notes" class="form-control form-control-sm" placeholder="contoh: Penyesuaian tarif PPN sesuai UU Harmonisasi Peraturan Perpajakan">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-success w-100 fw-bold" onclick="confirmSaveRule()">
                                    Tambahkan
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered align-middle gs-6 gy-4 mb-0">
                            <thead class="bg-light-secondary fs-7 text-uppercase fw-bold text-gray-600">
                                <tr>
                                    <th>Kode / Nama</th>
                                    <th>Jenis / Arah</th>
                                    <th class="text-center">Tarif (%)</th>
                                    <th>Masa Berlaku</th>
                                    <th>Keterangan</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="fs-7">
                                @forelse($taxRules as $rule)
                                    <tr>
                                        <td>
                                            <div class="fw-bolder font-monospace text-primary">{{ $rule->code }}</div>
                                            <div class="text-muted fs-8">{{ $rule->name }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $rule->tax_type->label() }}</div>
                                            <span class="badge badge-light-secondary fs-8">
                                                {{ match($rule->direction) { 'output' => 'Keluaran', 'input' => 'Masukan', default => 'Keluaran & Masukan' } }}
                                            </span>
                                        </td>
                                        <td class="text-center font-monospace fw-bold fs-6">
                                            {{ (float)$rule->rate }}%
                                        </td>
                                        <td>
                                            {{ \Carbon\Carbon::parse($rule->effective_from)->translatedFormat('d M Y') }}
                                            @if($rule->effective_until)
                                                s/d {{ \Carbon\Carbon::parse($rule->effective_until)->translatedFormat('d M Y') }}
                                            @else
                                                <span class="text-muted">(Seterusnya)</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $rule->notes ?: '—' }}</td>
                                        <td class="text-center">
                                            @if($rule->is_active)
                                                <span class="badge badge-light-success fw-bold">Aktif</span>
                                            @else
                                                <span class="badge badge-light-danger fw-bold">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <form method="post" action="{{ route('tax.rules.toggle', $rule) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="year" value="{{ $year->year }}">
                                                <button class="btn btn-xs btn-light-{{ $rule->is_active ? 'warning' : 'success' }}">
                                                    {{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-6 text-muted">
                                            Belum ada aturan tarif transaksi bertanggal efektif. Klik tombol "Tambah Aturan Baru" di atas.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        {{-- ──────────────────────────────────────────────────────────────
             TAB 4: DOKUMEN & EKSPOR CORETAX
             ────────────────────────────────────────────────────────────── --}}
        @elseif($activeTab === 'exports')
            <div class="row g-6 mb-8">
                <div class="col-lg-6">
                    <div class="card card-bordered h-100">
                        <div class="card-header bg-light py-4">
                            <h4 class="card-title mb-0">Unduh Dokumen Laporan Resmi</h4>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div>
                                <p class="text-muted fs-7">
                                    Dokumen laporan keuangan tahunan dan pembukuan resmi yang dapat digunakan sebagai lampiran SPT Tahunan Badan 1771 dan arsip Coretax.
                                </p>
                                <div class="d-flex flex-column gap-3 mb-6">
                                    <div class="p-3 border rounded d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="badge badge-light-danger p-3"><i class="ki-outline ki-document fs-3"></i></div>
                                            <div>
                                                <strong class="d-block">Laporan Keuangan & Fiskal (PDF)</strong>
                                                <span class="text-muted fs-8">Format siap cetak A4 dengan lembar pengesahan</span>
                                            </div>
                                        </div>
                                        <a href="{{ route('tax.years.pdf', $year) }}" class="btn btn-sm btn-danger">
                                            Unduh PDF
                                        </a>
                                    </div>

                                    <div class="p-3 border rounded d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="badge badge-light-success p-3"><i class="ki-outline ki-file-sheet fs-3"></i></div>
                                            <div>
                                                <strong class="d-block">Kertas Kerja Keuangan (Excel XLSX)</strong>
                                                <span class="text-muted fs-8">Multi-sheet: Ringkasan, Laba Rugi, Neraca, 12 Bulan</span>
                                            </div>
                                        </div>
                                        <a href="{{ route('tax.years.excel', $year) }}" class="btn btn-sm btn-success">
                                            Unduh Excel
                                        </a>
                                    </div>
                                </div>
                            </div>

                            @can('finance_annual.approve')
                                <div class="border-top pt-4">
                                    <h5 class="mb-3">Otorisasi & Siklus Tutup Buku (Status: {{ $year->status?->label() ?? 'Draft' }})</h5>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach(['review' => 'Periksa (Review)', 'approve' => 'Setujui (Approve)', 'lock' => 'Kunci Permanen'] as $act => $actLabel)
                                            <form method="post" action="{{ route('tax.years.transition', $year) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="{{ $act }}">
                                                <button class="btn btn-sm btn-primary">{{ $actLabel }}</button>
                                            </form>
                                        @endforeach
                                        <form method="post" action="{{ route('tax.years.transition', $year) }}" class="d-flex gap-2">
                                            @csrf
                                            <input type="hidden" name="action" value="reopen">
                                            <input name="reason" class="form-control form-control-sm" placeholder="Alasan buka kembali" required>
                                            <button class="btn btn-sm btn-warning">Buka</button>
                                        </form>
                                    </div>
                                </div>
                            @endcan
                        </div>
                    </div>
                </div>

                {{-- Panduan Kesiapan Coretax DJP --}}
                <div class="col-lg-6">
                    <div class="card card-bordered h-100">
                        <div class="card-header bg-light py-4">
                            <h4 class="card-title mb-0">Panduan Kepatuhan Coretax DJP</h4>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush fs-7">
                                <li class="list-group-item px-0 py-3 d-flex align-items-start gap-3">
                                    <i class="ki-outline ki-check-circle fs-3 text-success mt-1"></i>
                                    <div>
                                        <strong>1. Rekonsiliasi NIK/NPWP 16 Digit</strong>
                                        <p class="text-muted fs-8 mb-0">Pastikan NPWP mitra B2B dan vendor telah tervalidasi 16 digit format Coretax.</p>
                                    </div>
                                </li>
                                <li class="list-group-item px-0 py-3 d-flex align-items-start gap-3">
                                    <i class="ki-outline ki-check-circle fs-3 text-success mt-1"></i>
                                    <div>
                                        <strong>2. Pajak Masukan PO & Faktur Pembelian</strong>
                                        <p class="text-muted fs-8 mb-0">Semua faktur masukan wajib memiliki status 'Approved' sebelum dikreditkan pada SPT Masa.</p>
                                    </div>
                                </li>
                                <li class="list-group-item px-0 py-3 d-flex align-items-start gap-3">
                                    <i class="ki-outline ki-check-circle fs-3 text-success mt-1"></i>
                                    <div>
                                        <strong>3. SPT Masa PPN 1111 Tepat Waktu</strong>
                                        <p class="text-muted fs-8 mb-0">Pelaporan SPT Masa PPN dilakukan paling lambat akhir bulan berikutnya setelah masa pajak berakhir.</p>
                                    </div>
                                </li>
                                <li class="list-group-item px-0 py-3 d-flex align-items-start gap-3">
                                    <i class="ki-outline ki-check-circle fs-3 text-success mt-1"></i>
                                    <div>
                                        <strong>4. SPT Tahunan Badan (Formulir 1771)</strong>
                                        <p class="text-muted fs-8 mb-0">Jatuh tempo pelaporan SPT Tahunan Badan adalah 30 April setiap tahunnya.</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function applyHppPreset() {
    document.getElementById('final_rate').value = '0.50';
    document.getElementById('facility_rate').value = '11.00';
    document.getElementById('general_rate').value = '22.00';
    document.getElementById('threshold_facility').value = '4800000000';
    document.getElementById('threshold_partial').value = '50000000000';
}

function applyPp55Preset() {
    document.getElementById('final_rate').value = '0.50';
    document.getElementById('facility_rate').value = '11.00';
    document.getElementById('general_rate').value = '22.00';
    document.getElementById('threshold_facility').value = '4800000000';
    document.getElementById('threshold_partial').value = '50000000000';
}

async function confirmSaveRates() {
    const form = document.getElementById('form-tax-rates');
    const finalRate = document.getElementById('final_rate')?.value || '0';
    const facilityRate = document.getElementById('facility_rate')?.value || '0';
    const generalRate = document.getElementById('general_rate')?.value || '0';

    const messageHtml = `
        <div class="text-start fs-7">
            <p>Anda akan memperbarui persentase tarif pajak tahun buku ini:</p>
            <ul>
                <li><strong>Tarif PPh Final:</strong> ${finalRate}%</li>
                <li><strong>Tarif Fasilitas 31E:</strong> ${facilityRate}%</li>
                <li><strong>Tarif Umum:</strong> ${generalRate}%</li>
            </ul>
            <p class="text-primary mb-0">Laporan Laba Rugi dan Neraca tahun berjalan akan otomatis dihitung ulang.</p>
        </div>
    `;

    if (window.Swal) {
        const result = await window.Swal.fire({
            title: 'Simpan perubahan tarif pajak?',
            html: messageHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Simpan & Hitung Ulang',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-light'
            }
        });
        if (result.isConfirmed) form.submit();
    } else {
        if (confirm('Simpan perubahan tarif pajak? Laporan keuangan tahun ini akan dihitung ulang.')) {
            form.submit();
        }
    }
}

async function confirmSaveRule() {
    const form = document.getElementById('form-rule-store');
    const code = document.getElementById('rule_code')?.value;
    const rate = document.getElementById('rule_rate')?.value;

    if (!code || !rate) {
        alert('Mohon lengkapi kode dan tarif aturan pajak.');
        return;
    }

    if (window.Swal) {
        const result = await window.Swal.fire({
            title: 'Tambah aturan tarif transaksi?',
            text: `Aturan ${code} dengan tarif ${rate}% akan disimpan sebagai aturan bertanggal efektif.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Tambahkan',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-light'
            }
        });
        if (result.isConfirmed) form.submit();
    } else {
        if (confirm(`Tambah aturan ${code} dengan tarif ${rate}%?`)) {
            form.submit();
        }
    }
}
</script>
@endpush
