@extends('layouts.metronic.app')

@php
    $statusLabels = [
        'pending_approval' => 'Menunggu persetujuan',
        'approved' => 'Disetujui, belum dibeli',
        'purchased' => 'Sudah dibeli untuk pelanggan',
        'unallocated' => 'Siap dijual di toko',
        'completed' => 'Selesai diproses',
        'cancelled' => 'Dibatalkan',
        'rejected' => 'Ditolak',
        'supplier_returned' => 'Dikembalikan ke pemasok',
        'warehouse_pending' => 'Menunggu penerimaan gudang',
        'warehouse_received' => 'Sudah diterima gudang',
    ];
    $statusTones = [
        'pending_approval' => 'warning',
        'approved' => 'primary',
        'purchased' => 'info',
        'unallocated' => 'success',
        'completed' => 'success',
        'cancelled' => 'danger',
        'rejected' => 'danger',
        'supplier_returned' => 'secondary',
        'warehouse_pending' => 'warning',
        'warehouse_received' => 'success',
    ];
    $requestCount = $purchases->count();
    $saleCount = (int) ($allocations->sale_count ?? 0);
@endphp

@section('title', 'Laporan Belanja Darurat Toko')
@section('page_title', 'Laporan Belanja Darurat Toko')

@section('page_guide')
    <x-metronic.page-guide id="emergency-purchase-report" title="Panduan Laporan Belanja Darurat Toko">
        <x-slot:function><p>Halaman ini membantu kepala toko melihat uang yang dipakai untuk belanja darurat, barang yang sudah terjual, sisa barang yang masih dapat dijual, dan seberapa sering stok toko tidak mencukupi permintaan.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih toko dan rentang tanggal.</li><li>Baca empat angka utama untuk melihat biaya dan penyebab belanja darurat.</li><li>Periksa sisa barang per produk agar dapat dijual lebih dahulu.</li><li>Buka rincian permintaan yang perlu ditindaklanjuti.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Uang keluar bersih:</strong> biaya pembelian dikurangi pengembalian uang dari pemasok.</li><li><strong>Biaya barang terjual:</strong> biaya asli barang darurat yang sudah masuk penjualan kasir dan belum dikembalikan pelanggan.</li><li><strong>Selisih biaya:</strong> perbedaan biaya barang darurat terhadap biaya pokok reguler produk.</li><li><strong>Stok tidak cukup:</strong> kejadian saat staf mengonfirmasi permintaan melebihi stok yang tersedia.</li></ul></x-slot:parts>
        <x-slot:warnings><div class="alert alert-warning mb-0">Daftar permintaan mengikuti tanggal pengajuan. Angka uang keluar mengikuti tanggal pembelian atau pengembalian pemasok, sedangkan nilai barang terjual mengikuti tanggal transaksi POS.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Laporan Belanja Darurat Toko" description="Pantau biaya, pemakaian, dan sisa barang yang dibeli di luar stok reguler.">
        <x-slot:actions>
            <a href="{{ route('retail.emergency.index', ['branch_id' => $branchId]) }}" class="btn btn-light-primary">
                <i class="ki-outline ki-notepad fs-5 me-2"></i>Daftar Pembelian
            </a>
        </x-slot:actions>
    </x-metronic.page-title>

    <x-metronic.card class="mb-5 emergency-report-filter">
        <form method="GET" class="row g-4 align-items-end">
            <div class="col-xl-4 col-md-6">
                <label class="form-label fw-semibold">Toko yang dilihat</label>
                <select name="branch_id" class="form-select form-select-solid">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-3 col-md-6">
                <label class="form-label fw-semibold">Tanggal mulai</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-solid">
            </div>
            <div class="col-xl-3 col-md-6">
                <label class="form-label fw-semibold">Tanggal selesai</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-solid">
            </div>
            <div class="col-xl-2 col-md-6">
                <button class="btn btn-primary w-100"><i class="ki-outline ki-filter fs-5 me-2"></i>Tampilkan</button>
            </div>
        </form>
    </x-metronic.card>

    <div class="emergency-report-scope d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-5" role="status">
        <div class="d-flex align-items-start gap-3">
            <span class="emergency-report-scope-icon"><i class="ki-outline ki-calendar-8 fs-2"></i></span>
            <div>
                <div class="fw-bold text-gray-900">{{ $selectedBranch?->name ?? 'Belum ada toko yang dapat dipilih' }}</div>
                <div class="text-muted fs-7">Periode {{ \Illuminate\Support\Carbon::parse($from)->translatedFormat('d M Y') }} sampai {{ \Illuminate\Support\Carbon::parse($to)->translatedFormat('d M Y') }}</div>
            </div>
        </div>
        <div class="text-muted fs-8 emergency-report-scope-note">Sisa stok darurat menampilkan posisi saat ini. Angka lainnya mengikuti periode di atas.</div>
    </div>

    @php
    $primaryMetrics = [
        ['Uang keluar bersih', \App\Support\CurrencyFormatter::rupiah($spent), 'Pembelian darurat dikurangi uang yang dikembalikan pemasok.', 'ki-wallet', 'primary'],
        ['Biaya barang darurat terjual', \App\Support\CurrencyFormatter::rupiah($allocations->cost ?? 0), 'Biaya asli barang yang sudah terjual, setelah dikurangi barang yang dikembalikan pelanggan.', 'ki-basket-ok', 'success'],
        ['Tambahan biaya dari harga pokok biasa', \App\Support\CurrencyFormatter::rupiah($allocations->lost_margin ?? 0), 'Nilai positif berarti belanja darurat lebih mahal daripada biaya pokok yang biasa dipakai toko.', 'ki-chart-line-down-2', 'warning'],
        ['Saat stok toko tidak cukup', number_format($stockouts, 0, ',', '.').' kejadian', 'Dicatat ketika staf mengonfirmasi kebutuhan yang belum dapat dipenuhi.', 'ki-information-5', 'danger'],
    ];
    @endphp
    <div class="row g-5 mb-5">
        @foreach($primaryMetrics as [$label, $value, $help, $icon, $tone])
            <div class="col-xl-3 col-md-6">
                <div class="card emergency-report-kpi h-100">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-5">
                            <span class="emergency-report-kpi-icon bg-light-{{ $tone }} text-{{ $tone }}"><i class="ki-outline {{ $icon }} fs-2"></i></span>
                            <span class="badge badge-light-{{ $tone }}">Periode terpilih</span>
                        </div>
                        <div class="text-muted text-uppercase fs-8 fw-bold mb-2">{{ $label }}</div>
                        <div class="fs-2 fw-bolder text-gray-900 mb-3">{{ $value }}</div>
                        <div class="text-muted fs-8 lh-base">{{ $help }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @php
    $activityMetrics = [
        ['Permintaan dibuat', $requestCount, 'Semua pengajuan yang dibuat dalam periode.', 'ki-document'],
        ['Karena pelanggan', (int) $purposeCounts->get('customer_request', 0), 'Dibuat untuk memenuhi transaksi pelanggan tertentu.', 'ki-people'],
        ['Belanja stok berjaga-jaga', (int) $purposeCounts->get('proactive_restock', 0), 'Dibeli lebih dulu untuk mengantisipasi kebutuhan toko.', 'ki-package'],
        ['Penjualan memakai barang darurat', $saleCount, 'Jumlah transaksi POS yang memakai barang darurat.', 'ki-shop'],
    ];
    @endphp
    <div class="row g-4 mb-5">
        @foreach($activityMetrics as [$label, $value, $help, $icon])
            <div class="col-xl-3 col-sm-6">
                <div class="emergency-report-activity h-100">
                    <span class="emergency-report-activity-icon"><i class="ki-outline {{ $icon }} fs-3"></i></span>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-baseline justify-content-between gap-3"><span class="fw-semibold text-gray-800">{{ $label }}</span><strong class="fs-3 text-gray-900">{{ number_format($value, 0, ',', '.') }}</strong></div>
                        <div class="text-muted fs-8 mt-1">{{ $help }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-5 mb-5">
        <div class="col-xl-8">
            <x-metronic.card title="Barang darurat yang masih siap dijual" subtitle="Saldo saat ini, dipisahkan per produk dan satuan dasar." class="h-100">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 bg-light-success rounded p-4 mb-4">
                    <div><div class="text-muted fs-8 text-uppercase fw-bold">Nilai barang tersisa</div><div class="fs-2 fw-bolder text-success">{{ \App\Support\CurrencyFormatter::rupiah($poolCostValue) }}</div></div>
                    <span class="badge badge-light-success fs-7">{{ count($poolProducts) }} produk siap dijual</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0" data-mobile-primary="Produk" data-mobile-highlight="Jumlah tersedia">
                        <thead><tr class="text-muted text-uppercase fs-8 fw-bold"><th>Produk</th><th>Jumlah tersedia</th><th>Asal pembelian</th><th class="text-end">Nilai biaya</th></tr></thead>
                        <tbody>
                            @forelse($poolProducts as $product)
                                <tr>
                                    <td><div class="fw-semibold text-gray-900">{{ $product['product_name'] }}</div><div class="text-muted fs-8">{{ $product['sku'] }}</div></td>
                                    <td><span class="badge badge-light-success fs-7">{{ qty($product['quantity']) }} {{ $product['unit_symbol'] }}</span></td>
                                    <td>{{ $product['lot_count'] }} kali pembelian</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\CurrencyFormatter::rupiah($product['cost_value']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-metronic.empty-state title="Tidak ada barang darurat tersisa" description="Semua barang darurat sudah terjual, diretur ke pemasok, dialihkan, atau belum ada pembelian." icon="ki-outline ki-check-circle" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-xl-4">
            <x-metronic.card title="Cara membaca laporan" class="h-100">
                <div class="emergency-report-help-item"><span>1</span><div><strong>Periksa selisih biaya</strong><p>Jika nilainya besar, evaluasi pemasok atau harga jual agar keuntungan toko tetap terjaga.</p></div></div>
                <div class="emergency-report-help-item"><span>2</span><div><strong>Jual barang yang masih tersisa</strong><p>Barang pada daftar saldo dapat dipakai POS setelah stok reguler habis.</p></div></div>
                <div class="emergency-report-help-item"><span>3</span><div><strong>Tinjau kejadian berulang</strong><p>Stok yang sering tidak cukup dapat menjadi dasar penyesuaian stok minimum dan jadwal restok.</p></div></div>
            </x-metronic.card>
        </div>
    </div>

    <x-metronic.card title="Rincian permintaan yang dibuat" subtitle="Daftar ini mengikuti tanggal pengajuan dalam periode terpilih.">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle mb-0" data-mobile-primary="Permintaan" data-mobile-secondary="Kebutuhan" data-mobile-subtitle="Tanggal diajukan" data-mobile-highlight="Tahap saat ini">
                <thead><tr class="text-muted text-uppercase fs-8 fw-bold"><th>Tanggal diajukan</th><th>Permintaan</th><th>Kebutuhan</th><th>Barang</th><th>Tahap saat ini</th><th class="text-end">Nilai pembelian</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @forelse($purchases as $purchase)
                        @php
                            $purposeLabel = $purchase->purpose === 'proactive_restock' ? 'Belanja stok berjaga-jaga' : 'Permintaan pelanggan';
                            $purposeTone = $purchase->purpose === 'proactive_restock' ? 'info' : 'primary';
                            $statusLabel = $statusLabels[$purchase->status] ?? ucfirst(str_replace('_', ' ', $purchase->status));
                            $statusTone = $statusTones[$purchase->status] ?? 'secondary';
                            $productNames = $purchase->items->pluck('product.name')->filter()->unique()->values();
                        @endphp
                        <tr>
                            <td><div class="fw-semibold">{{ $purchase->created_at?->format('d/m/Y') }}</div><div class="text-muted fs-8">{{ $purchase->created_at?->format('H:i') }}</div></td>
                            <td><a class="fw-bold" href="{{ route('retail.emergency.show', $purchase) }}">{{ $purchase->number }}</a><div class="text-muted fs-8">{{ $purchase->branch?->name }}</div></td>
                            <td><span class="badge badge-light-{{ $purposeTone }}">{{ $purposeLabel }}</span></td>
                            <td><div class="fw-semibold text-gray-800">{{ $productNames->take(2)->join(', ') ?: 'Produk tidak ditemukan' }}</div>@if($productNames->count() > 2)<div class="text-muted fs-8">+{{ $productNames->count() - 2 }} produk lainnya</div>@endif</td>
                            <td><span class="badge badge-light-{{ $statusTone }}">{{ $statusLabel }}</span></td>
                            <td class="text-end"><div class="fw-semibold">{{ \App\Support\CurrencyFormatter::rupiah($purchase->total_cost) }}</div><div class="text-muted fs-8">{{ $purchase->purchased_at ? 'Dibeli '.$purchase->purchased_at->format('d/m/Y') : 'Belum ada pembelian' }}</div></td>
                            <td class="text-end"><a href="{{ route('retail.emergency.show', $purchase) }}" class="btn btn-sm btn-light-primary">Lihat rincian</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-metronic.empty-state title="Belum ada permintaan pada periode ini" description="Coba ubah rentang tanggal atau pilih toko lain yang dapat Anda akses." icon="ki-outline ki-calendar-remove" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
@endsection

@push('styles')
    <style>
        .emergency-report-filter{border:1px solid var(--bs-gray-200);box-shadow:none}.emergency-report-scope{background:linear-gradient(100deg,var(--bs-primary-light),var(--bs-body-bg));border:1px solid rgba(var(--bs-primary-rgb),.18);border-radius:.85rem;padding:1rem 1.25rem}.emergency-report-scope-icon,.emergency-report-kpi-icon,.emergency-report-activity-icon{align-items:center;border-radius:.7rem;display:inline-flex;flex:0 0 auto;height:44px;justify-content:center;width:44px}.emergency-report-scope-icon{background:var(--bs-body-bg);color:var(--bs-primary)}.emergency-report-scope-note{max-width:470px}.emergency-report-kpi{border:1px solid var(--bs-gray-200);box-shadow:none}.emergency-report-kpi .card-body{display:flex;flex-direction:column}.emergency-report-kpi .lh-base{margin-top:auto}.emergency-report-activity{align-items:flex-start;background:var(--bs-body-bg);border:1px solid var(--bs-gray-200);border-radius:.85rem;display:flex;gap:1rem;padding:1.15rem}.emergency-report-activity-icon{background:var(--bs-gray-100);color:var(--bs-primary);height:40px;width:40px}.emergency-report-help-item{align-items:flex-start;display:flex;gap:1rem;padding:1rem 0}.emergency-report-help-item+.emergency-report-help-item{border-top:1px dashed var(--bs-gray-300)}.emergency-report-help-item>span{align-items:center;background:var(--bs-primary-light);border-radius:50%;color:var(--bs-primary);display:inline-flex;flex:0 0 auto;font-weight:700;height:32px;justify-content:center;width:32px}.emergency-report-help-item strong{color:var(--bs-gray-900)}.emergency-report-help-item p{color:var(--bs-gray-600);font-size:.85rem;line-height:1.55;margin:.25rem 0 0}@media(max-width:767.98px){.emergency-report-scope{padding:1rem}.emergency-report-scope-note{border-top:1px dashed var(--bs-gray-300);padding-top:.75rem}.emergency-report-kpi .fs-2{font-size:1.55rem!important}}
    </style>
@endpush
