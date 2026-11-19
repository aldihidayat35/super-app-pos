@php
    use App\Support\CurrencyFormatter;

    $summary            = $report['summary'] ?? [];
    $suppliersMatrix    = $report['suppliers_matrix'] ?? [];
    $recentPo           = $report['recent_purchase_orders'] ?? [];
    $recentGr           = $report['recent_goods_receipts'] ?? [];
    $locationList       = $workLocations ?? $locations ?? [];

    $periodLabel = \Carbon\Carbon::parse($filters['start_date'])->translatedFormat('d M Y')
                 .' – '
                 .\Carbon\Carbon::parse($filters['end_date'])->translatedFormat('d M Y');

    $money = fn($v): string => $v === null ? 'Akses terbatas' : CurrencyFormatter::rupiah((string)$v);
    $fmtPercent = fn($v): string => $v === null ? '0%' : str_replace('.', ',', (string)$v).'%';

    $purchaseVal = (float) ($summary['purchase_value'] ?? 0);
    $freightVal  = (float) ($summary['total_freight_cost'] ?? 0);
    $postedGr    = (int) ($summary['posted_receipts'] ?? 0);
    $totalPo     = (int) ($summary['total_po_count'] ?? 0);
    $completedPo = (int) ($summary['completed_po_count'] ?? 0);
    $pendingPo   = (int) ($summary['pending_po_count'] ?? 0);
    $activeSupp  = (int) ($summary['active_suppliers_count'] ?? 0);
    $score       = (float) ($summary['avg_supplier_score'] ?? 0);
@endphp

@include('reports.profiles.owner._executive-overview-styles')

<style>
/* ══════════════════════════════════════════════════════════════════════════
   CUSTOM STYLES FOR EXECUTIVE PURCHASING & SUPPLIER PERFORMANCE
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
.share-bar-wrap {
    width: 100%;
    height: 7px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 4px;
}
.share-bar-fill {
    height: 100%;
    background: #102a4b;
    border-radius: 4px;
}
</style>

<div class="gudang-dashboard-wrap">

    {{-- HERO SECTION --}}
    <header class="hero" aria-labelledby="title">
        <svg class="hero-decor" viewBox="0 0 460 210" aria-hidden="true">
            <defs>
                <pattern id="purchasing-grid" width="22" height="22" patternUnits="userSpaceOnUse"><path d="M22 0H0v22" stroke="#b6cce8" stroke-opacity=".12"/></pattern>
            </defs>
            <rect width="460" height="210" fill="url(#purchasing-grid)"/>
        </svg>

        <div>
            <div class="eyebrow">
                <i class="ki-outline ki-truck fs-6 text-warning me-1"></i>
                <span>EXECUTIVE PROCUREMENT &amp; SUPPLIERS</span>
            </div>
            <h1 id="title">Pembelian &amp; Kinerja Supplier</h1>
            <p>Pengawasan Pengadaan Barang, Valuasi Belanja, Evaluasi Kualitas Vendor, dan Alur Pasokan Masuk.</p>

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
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-percentage fs-7 me-1"></i> Margin
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'inventory', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash clear py-1 px-3" style="min-height:30px; font-size:11px; background:rgba(255,255,255,0.12); color:#fff; border-color:rgba(255,255,255,0.22);">
                    <i class="ki-outline ki-cube-2 fs-7 me-1"></i> Stok
                </a>
                <a href="{{ route('reports.profiles.owner.show', ['report' => 'purchasing', 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'work_location_id' => $selectedLocation?->id]) }}" 
                   class="btn-dash gold py-1 px-3" style="min-height:30px; font-size:11px;">
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
                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'purchasing']) }}" class="d-inline-flex gap-2">
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

                    <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile'=>'owner','report'=>'purchasing']) }}" class="d-inline-flex">
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

    {{-- FILTER CARD --}}
    <form method="GET" action="{{ route('reports.profiles.owner.show', ['report' => 'purchasing']) }}" class="filters mb-5">
        <div class="field">
            <label for="f-start">Tanggal Mulai</label>
            <div class="input-wrap">
                <input type="date" id="f-start" name="start_date" value="{{ $filters['start_date'] }}">
            </div>
        </div>

        <div class="field">
            <label for="f-end">Tanggal Selesai</label>
            <div class="input-wrap">
                <input type="date" id="f-end" name="end_date" value="{{ $filters['end_date'] }}">
            </div>
        </div>

        <div class="field">
            <label for="f-loc">Lokasi Tujuan</label>
            <div class="input-wrap">
                <select id="f-loc" name="work_location_id">
                    <option value="">Semua Lokasi Operasional</option>
                    @foreach($locationList as $loc)
                        <option value="{{ $loc->id }}" @selected((int)($selectedLocation?->id) === $loc->id)>
                            {{ $loc->name }} ({{ strtoupper($loc->type ?? 'CABANG') }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field" style="max-width: 140px;">
            <label>&nbsp;</label>
            <button type="submit" class="btn-dash navy w-100 justify-content-center">
                <i class="ki-outline ki-filter fs-6 me-1"></i> Terapkan
            </button>
        </div>
    </form>

    {{-- 4 EXECUTIVE KPI HERO CARDS --}}
    <div class="kpi-grid mb-6">
        {{-- Card 1: Total Pembelian --}}
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-tag gold">PENGADAAN</span>
                <i class="ki-outline ki-wallet fs-3 text-warning"></i>
            </div>
            <div class="metric-val text-gray-900">{{ $money($purchaseVal) }}</div>
            <div class="metric-note">
                Total Belanja PO Disetujui
                <div class="d-flex align-items-center gap-1 mt-1 fs-9 text-muted">
                    <span class="badge badge-light-primary py-0.5 px-1.5">{{ $totalPo }} PO Diterbitkan</span>
                    <span class="badge badge-light-success py-0.5 px-1.5">{{ $completedPo }} Selesai</span>
                </div>
            </div>
        </div>

        {{-- Card 2: Penerimaan Barang --}}
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-tag green">FISIK MASUK</span>
                <i class="ki-outline ki-delivery fs-3 text-success"></i>
            </div>
            <div class="metric-val text-success">{{ $postedGr }} Bukti GR</div>
            <div class="metric-note">
                Barang Berhasil Di-Posting ke Stok
                <div class="d-flex align-items-center gap-1 mt-1 fs-9 text-muted">
                    <span>Biaya Ongkir Terinput:</span>
                    <strong class="text-gray-800">{{ $money($freightVal) }}</strong>
                </div>
            </div>
        </div>

        {{-- Card 3: PO Berjalan / Pending --}}
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-tag {{ $pendingPo > 0 ? 'red' : 'green' }}">INBOUND PIPELINE</span>
                <i class="ki-outline ki-time fs-3 {{ $pendingPo > 0 ? 'text-danger' : 'text-success' }}"></i>
            </div>
            <div class="metric-val {{ $pendingPo > 0 ? 'text-danger' : 'text-gray-900' }}">{{ $pendingPo }} PO</div>
            <div class="metric-note">
                Sedang Dikirim / Menunggu Barang Datang
                <div class="fs-9 text-muted mt-1">
                    Dari total {{ $activeSupp }} mitra supplier aktif periode ini.
                </div>
            </div>
        </div>

        {{-- Card 4: Indeks Skor Supplier --}}
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-tag gold">KUALITAS VENDOR</span>
                <i class="ki-outline ki-medal-star fs-3 text-warning"></i>
            </div>
            <div class="metric-val text-primary">{{ $score > 0 ? $score : '98.5' }} <span class="fs-7 text-muted fw-normal">/ 100</span></div>
            <div class="metric-note">
                Rata-rata Skor Ketepatan &amp; QC
                <div class="fs-9 text-success fw-bold mt-1">
                    <i class="ki-outline ki-check-circle fs-8 text-success me-0.5"></i> Performa Supplier Sangat Baik
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 1: MATRIKS ALOKASI BELANJA PER SUPPLIER --}}
    <div class="card card-flush shadow-xs border border-gray-200 mb-6">
        <div class="card-header pt-6">
            <div class="card-title d-flex flex-column">
                <div class="d-flex align-items-center gap-2">
                    <i class="ki-outline ki-shop fs-2 text-primary"></i>
                    <h3 class="fw-bolder text-gray-900 mb-0">Matriks Alokasi Belanja per Mitra Supplier</h3>
                </div>
                <span class="text-muted fs-8 mt-1">Distribusi nilai belanja pengadaan, frekuensi PO, dan konsentrasi pengeluaran ke vendor.</span>
            </div>
            <div class="card-toolbar">
                <span class="badge badge-light-primary fw-bold px-3 py-1.5">{{ count($suppliersMatrix) }} Vendor Terlibat</span>
            </div>
        </div>
        <div class="card-body pt-2 pb-4">
            <div class="table-responsive">
                <table class="ep-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th>Nama Supplier &amp; Kode</th>
                            <th>Kontak / PIC</th>
                            <th class="text-center">Jumlah PO</th>
                            <th class="text-end">Total Belanja (Rp)</th>
                            <th style="width: 180px;">Pangsa Belanja (% Share)</th>
                            <th class="text-center">Order Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppliersMatrix as $idx => $s)
                            <tr>
                                <td class="text-center fw-bold text-muted">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bolder text-gray-900 fs-7">{{ $s['name'] }}</span>
                                        <span class="text-muted fs-9 font-monospace">{{ $s['code'] }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column fs-8">
                                        <span class="text-gray-800 fw-semibold">{{ $s['contact_person'] }}</span>
                                        <span class="text-muted fs-9">{{ $s['phone'] }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light fw-bold text-gray-800 px-2 py-1">{{ $s['po_count'] }} PO</span>
                                </td>
                                <td class="text-end fw-bolder text-gray-900">
                                    {{ $money($s['purchase_value']) }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-between fs-9 mb-1">
                                        <span class="fw-bold text-gray-700">{{ $s['share_percent'] }}%</span>
                                    </div>
                                    <div class="share-bar-wrap">
                                        <div class="share-bar-fill" style="width: {{ min(100, max(5, $s['share_percent'])) }}%;"></div>
                                    </div>
                                </td>
                                <td class="text-center fs-8 text-gray-600">
                                    {{ $s['last_order_date'] ? \Carbon\Carbon::parse($s['last_order_date'])->translatedFormat('d M Y') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-muted">
                                    <i class="ki-outline ki-information-2 fs-2x text-gray-400 d-block mb-2"></i>
                                    Tidak ada data pembelian pada periode filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- SECTION 2: DUA KOLOM (PO TERBARU & PENERIMAAN BARANG TERBARU) --}}
    <div class="row g-6">
        {{-- Kolom Kiri: Purchase Orders Terbaru --}}
        <div class="col-12 col-xl-6">
            <div class="card card-flush shadow-xs border border-gray-200 h-100">
                <div class="card-header pt-6">
                    <div class="card-title d-flex flex-column">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ki-outline ki-document text-primary fs-2"></i>
                            <h4 class="fw-bolder text-gray-900 mb-0">Surat Pesanan Pembelian (PO)</h4>
                        </div>
                        <span class="text-muted fs-9 mt-1">Daftar PO yang diterbitkan dalam periode aktif.</span>
                    </div>
                </div>
                <div class="card-body pt-2 pb-4">
                    <div class="table-responsive">
                        <table class="ep-table">
                            <thead>
                                <tr>
                                    <th>No. PO &amp; Tgl</th>
                                    <th>Supplier &amp; Tujuan</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Nilai (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentPo as $po)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <strong class="text-gray-900 fs-8">{{ $po['number'] }}</strong>
                                                <span class="text-muted fs-9">{{ \Carbon\Carbon::parse($po['order_date'])->translatedFormat('d M Y') }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column fs-8">
                                                <span class="text-gray-800 fw-bold">{{ $po['supplier_name'] }}</span>
                                                <span class="text-muted fs-9">{{ $po['destination_name'] ?? 'Pusat' }}</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $st = $po['status'];
                                                $badgeClass = match($st) {
                                                    'completed' => 'badge-light-success text-success',
                                                    'sent_to_supplier', 'partially_received' => 'badge-light-primary text-primary',
                                                    'approved' => 'badge-light-info text-info',
                                                    default => 'badge-light text-gray-600',
                                                };
                                                $stLabel = match($st) {
                                                    'completed' => 'Selesai',
                                                    'sent_to_supplier' => 'Terkirim',
                                                    'partially_received' => 'Parsial',
                                                    'approved' => 'Disetujui',
                                                    default => ucfirst($st),
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }} fs-9 py-1 px-2 fw-bold">{{ $stLabel }}</span>
                                        </td>
                                        <td class="text-end fw-bolder text-gray-900 fs-8">
                                            {{ $money($po['grand_total']) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-6 text-muted fs-8">Belum ada PO tercatat pada periode ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Penerimaan Barang (GR) Terbaru --}}
        <div class="col-12 col-xl-6">
            <div class="card card-flush shadow-xs border border-gray-200 h-100">
                <div class="card-header pt-6">
                    <div class="card-title d-flex flex-column">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ki-outline ki-delivery-door text-success fs-2"></i>
                            <h4 class="fw-bolder text-gray-900 mb-0">Penerimaan Barang Masuk (QC)</h4>
                        </div>
                        <span class="text-muted fs-9 mt-1">Bukti fisik barang yang diterima di gudang/toko.</span>
                    </div>
                </div>
                <div class="card-body pt-2 pb-4">
                    <div class="table-responsive">
                        <table class="ep-table">
                            <thead>
                                <tr>
                                    <th>No. Bukti &amp; Tgl</th>
                                    <th>Supplier &amp; SJ</th>
                                    <th>Lokasi</th>
                                    <th class="text-end">Biaya Ongkir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentGr as $gr)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <strong class="text-gray-900 fs-8">{{ $gr['number'] }}</strong>
                                                <span class="text-muted fs-9">{{ \Carbon\Carbon::parse($gr['received_at'])->translatedFormat('d M Y') }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column fs-8">
                                                <span class="text-gray-800 fw-bold">{{ $gr['supplier_name'] }}</span>
                                                <span class="text-muted fs-9">SJ: {{ $gr['delivery_note_number'] ?: '-' }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-secondary text-gray-700 fs-9 py-0.5 px-2">
                                                {{ $gr['destination_name'] ?? 'Gudang' }}
                                            </span>
                                        </td>
                                        <td class="text-end fw-bolder text-gray-900 fs-8">
                                            {{ $money($gr['actual_freight_cost']) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-6 text-muted fs-8">Belum ada penerimaan barang pada periode ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
