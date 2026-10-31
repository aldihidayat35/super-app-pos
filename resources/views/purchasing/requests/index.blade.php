@extends('layouts.metronic.app')

@section('title', 'Pengajuan Belanja (Internal) - ' . config('app.name'))
@section('page_title', 'Pengajuan Belanja (Internal)')

@section('page_guide')
    <x-metronic.page-guide id="purchasing-request-index" title="Panduan Pengajuan Belanja">
        <x-slot:function><p>Pengajuan Belanja (Purchase Request / Permintaan Pembelian) adalah formulir internal kebutuhan barang dari gudang atau toko sebelum diputuskan untuk dibeli dari pemasok luar. Dokumen ini murni kebutuhan internal organisasi.</p></x-slot:function>
        <x-slot:workflow><ol><li>Staf gudang/toko mengajukan daftar barang yang dibutuhkan (bisa banyak barang sekaligus).</li><li>Kepala bagian/manajer meninjau dan menyetujui pengajuan.</li><li>Tim Purchasing memilih supplier rekanan dan mengonversi pengajuan menjadi Pesanan ke Supplier (PO).</li></ol></x-slot:workflow>
        <x-slot:warnings><p>Rekomendasi stok minimum hanya sebagai acuan kebutuhan. Anda dapat menggunakan tombol Auto-Generate untuk langsung membuat draft PO dari stok kritis.</p></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    {{-- 1. Kartu Ringkasan KPI Cepat --}}
    <div class="row g-4 mb-6">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-flush h-100 border border-gray-200 shadow-sm hover-elevate-up">
                <div class="card-body d-flex align-items-center py-4 px-5">
                    <div class="symbol symbol-45px me-4">
                        <span class="symbol-label bg-light-primary text-primary">
                            <i class="ki-outline ki-document fs-2x"></i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-2 fw-bolder text-gray-900">{{ number_format($stats['total'] ?? 0) }}</div>
                        <div class="text-muted fs-7 fw-semibold">Total Pengajuan</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-flush h-100 border border-gray-200 shadow-sm hover-elevate-up">
                <div class="card-body d-flex align-items-center py-4 px-5">
                    <div class="symbol symbol-45px me-4">
                        <span class="symbol-label bg-light-warning text-warning">
                            <i class="ki-outline ki-time fs-2x"></i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-2 fw-bolder text-gray-900">{{ number_format($stats['pending'] ?? 0) }}</div>
                        <div class="text-muted fs-7 fw-semibold">Menunggu Approval</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-flush h-100 border border-gray-200 shadow-sm hover-elevate-up">
                <div class="card-body d-flex align-items-center py-4 px-5">
                    <div class="symbol symbol-45px me-4">
                        <span class="symbol-label bg-light-success text-success">
                            <i class="ki-outline ki-check-circle fs-2x"></i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-2 fw-bolder text-gray-900">{{ number_format($stats['approved'] ?? 0) }}</div>
                        <div class="text-muted fs-7 fw-semibold">Disetujui (Siap PO)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card card-flush h-100 border border-gray-200 shadow-sm hover-elevate-up">
                <div class="card-body d-flex align-items-center py-4 px-5">
                    <div class="symbol symbol-45px me-4">
                        <span class="symbol-label {{ ($stats['critical'] ?? 0) > 0 ? 'bg-light-danger text-danger' : 'bg-light-info text-info' }}">
                            <i class="ki-outline ki-notification-status fs-2x"></i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-2 fw-bolder text-gray-900">{{ number_format($stats['critical'] ?? 0) }}</div>
                        <div class="text-muted fs-7 fw-semibold">Item Stok Menipis</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Card Utama dengan Tab Navigasi Terpadu --}}
    <div class="card card-flush shadow-sm">
        <div class="card-header border-bottom border-gray-200 pt-3">
            <div class="card-title">
                <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold" role="tablist" id="pr_nav_tabs">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link text-active-primary pb-4 active d-flex align-items-center" data-bs-toggle="tab" href="#tab_pr_history" role="tab">
                            <i class="ki-outline ki-abstract-26 fs-4 me-2"></i>
                            Riwayat Pengajuan Belanja
                            <span class="badge badge-light-primary ms-2">{{ number_format($stats['total'] ?? 0) }}</span>
                        </a>
                    </li>
                    @can('create', \App\Models\PurchaseRequest::class)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link text-active-primary pb-4 d-flex align-items-center" data-bs-toggle="tab" href="#tab_pr_create" role="tab">
                                <i class="ki-outline ki-plus-circle fs-4 me-2"></i>
                                Buat Permintaan Internal
                            </a>
                        </li>
                    @endcan
                    <li class="nav-item" role="presentation">
                        <a class="nav-link text-active-primary pb-4 d-flex align-items-center" data-bs-toggle="tab" href="#tab_pr_recommendations" role="tab">
                            <i class="ki-outline ki-flash fs-4 me-2"></i>
                            Rekomendasi dari Stok Menipis
                            @if(($stats['critical'] ?? 0) > 0)
                                <span class="badge badge-danger ms-2">{{ $stats['critical'] }}</span>
                            @endif
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-toolbar">
                @can('create', \App\Models\PurchaseRequest::class)
                    <button type="button" class="btn btn-sm btn-primary" onclick="switchToPrCreateTab()">
                        <i class="ki-outline ki-plus fs-5 me-1"></i> Ajukan Belanja Baru
                    </button>
                @endcan
            </div>
        </div>

        <div class="card-body p-6">
            <div class="tab-content" id="pr_tab_content">
                {{-- ========================================== --}}
                {{-- TAB 1: RIWAYAT PENGAJUAN BELANJA (DEFAULT) --}}
                {{-- ========================================== --}}
                <div class="tab-pane fade show active" id="tab_pr_history" role="tabpanel">
                    {{-- Filter Status Bar --}}
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5 p-4 rounded bg-light-subtle border">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="text-muted fs-7 fw-semibold me-2">Filter Cepat:</span>
                            <a href="{{ route('purchasing.requests.index') }}" class="btn btn-sm {{ empty($status) ? 'btn-primary' : 'btn-light' }}">
                                Semua ({{ $stats['total'] ?? 0 }})
                            </a>
                            <a href="{{ route('purchasing.requests.index', ['status' => 'submitted']) }}" class="btn btn-sm {{ $status === 'submitted' ? 'btn-warning text-white' : 'btn-light' }}">
                                Diajukan ({{ $stats['pending'] ?? 0 }})
                            </a>
                            <a href="{{ route('purchasing.requests.index', ['status' => 'approved']) }}" class="btn btn-sm {{ $status === 'approved' ? 'btn-success' : 'btn-light' }}">
                                Disetujui ({{ $stats['approved'] ?? 0 }})
                            </a>
                            <a href="{{ route('purchasing.requests.index', ['status' => 'converted']) }}" class="btn btn-sm {{ $status === 'converted' ? 'btn-info' : 'btn-light' }}">
                                Jadi PO ({{ $stats['converted'] ?? 0 }})
                            </a>
                        </div>

                        <form method="GET" class="d-flex align-items-center gap-2">
                            <select name="status" class="form-select form-select-sm form-select-solid w-180px" onchange="this.form.submit()">
                                <option value="">Semua status dokumen</option>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if($status)
                                <a href="{{ route('purchasing.requests.index') }}" class="btn btn-sm btn-icon btn-light" title="Reset filter">
                                    <i class="ki-outline ki-cross fs-5"></i>
                                </a>
                            @endif
                        </form>
                    </div>

                    {{-- Tabel Riwayat --}}
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-4 gy-4">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase fs-8 bg-light">
                                    <th class="min-w-160px ps-4 rounded-start">No. Dokumen</th>
                                    <th class="min-w-160px">Gudang Tujuan</th>
                                    <th class="min-w-140px">Pemohon</th>
                                    <th class="min-w-100px">Prioritas</th>
                                    <th class="min-w-120px">Status</th>
                                    <th class="min-w-100px">Jml Item</th>
                                    <th class="min-w-140px text-end pe-4 rounded-end">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($requests as $purchaseRequest)
                                    <tr>
                                        <td class="ps-4">
                                            <a href="{{ route('purchasing.requests.show', $purchaseRequest) }}" class="fw-bolder text-primary fs-6 hover-primary">
                                                {{ $purchaseRequest->number }}
                                            </a>
                                            <div class="text-muted fs-8">{{ $purchaseRequest->submitted_at?->format('d M Y, H:i') ?: '-' }}</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-gray-800">{{ $purchaseRequest->warehouse?->name }}</div>
                                            <div class="text-muted fs-8">{{ $purchaseRequest->warehouse?->code }}</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-gray-800">{{ $purchaseRequest->requester?->name }}</div>
                                            <div class="text-muted fs-8">{{ $purchaseRequest->requester?->roles->first()?->name ?? 'Staff' }}</div>
                                        </td>
                                        <td>
                                            @php
                                                $priorityClass = match($purchaseRequest->priority) {
                                                    'urgent' => 'badge-light-danger text-danger',
                                                    'high' => 'badge-light-warning text-warning',
                                                    'low' => 'badge-light-secondary text-muted',
                                                    default => 'badge-light-info text-info',
                                                };
                                                $priorityLabel = match($purchaseRequest->priority) {
                                                    'urgent' => 'Mendesak',
                                                    'high' => 'Tinggi',
                                                    'low' => 'Rendah',
                                                    default => 'Normal',
                                                };
                                            @endphp
                                            <span class="badge {{ $priorityClass }} fw-bold">{{ $priorityLabel }}</span>
                                        </td>
                                        <td>
                                            <x-metronic.status-badge :status="$purchaseRequest->status" />
                                        </td>
                                        <td>
                                            <span class="badge badge-light fw-bold text-gray-700 fs-7">
                                                {{ $purchaseRequest->items->count() }} item
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="{{ route('purchasing.requests.show', $purchaseRequest) }}" class="btn btn-sm btn-icon btn-light-primary" title="Buka Detail">
                                                    <i class="ki-outline ki-eye fs-5"></i>
                                                </a>
                                                @if ($purchaseRequest->convertedPurchaseOrder)
                                                    @can('view', $purchaseRequest->convertedPurchaseOrder)
                                                        <a href="{{ route('purchasing.purchase-orders.show', $purchaseRequest->convertedPurchaseOrder) }}" class="btn btn-sm btn-light-success d-inline-flex align-items-center" title="Buka Purchase Order">
                                                            <i class="ki-outline ki-document fs-6 me-1"></i> Lihat PO
                                                        </a>
                                                    @endcan
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8">
                                            <x-metronic.empty-state title="Belum Ada Dokumen Pengajuan" description="Pengajuan kebutuhan belanja internal yang diajukan gudang atau toko akan tercatat di sini." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $requests->links() }}
                    </div>
                </div>

                {{-- ========================================== --}}
                {{-- TAB 2: BUAT PENGAJUAN BELANJA (FULL-WIDTH) --}}
                {{-- ========================================== --}}
                @can('create', \App\Models\PurchaseRequest::class)
                    <div class="tab-pane fade" id="tab_pr_create" role="tabpanel">
                        <div class="mb-5">
                            <h3 class="fs-4 fw-bolder mb-1 text-gray-900">Buat Permintaan Internal - Pengajuan Belanja</h3>
                            <div class="text-muted fs-7">Ajukan daftar kebutuhan pembelian untuk gudang atau toko cabang di wilayah kerja Anda.</div>
                        </div>

                        <form method="POST" action="{{ route('purchasing.requests.store') }}" id="form-purchase-request">
                            @csrf
                            <div class="card card-bordered mb-6 bg-light-subtle">
                                <div class="card-body p-5">
                                    <div class="row g-5">
                                        <div class="col-md-4">
                                            <x-metronic.form-group name="warehouse_id" label="Gudang / Lokasi Tujuan" required tooltip="Gudang yang membutuhkan barang dan akan menjadi tujuan penerimaan barang.">
                                                <select name="warehouse_id" class="form-select form-select-solid" required>
                                                    <option value="">Pilih gudang</option>
                                                    @foreach ($warehouses as $warehouse)
                                                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', count($warehouses) === 1 ? $warehouses->first()->id : '') == $warehouse->id)>
                                                            {{ $warehouse->code }} - {{ $warehouse->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </x-metronic.form-group>
                                        </div>
                                        <div class="col-md-3">
                                            <x-metronic.form-group name="priority" label="Tingkat Prioritas" required tooltip="Rendah: dapat dijadwalkan. Normal: kebutuhan rutin. Tinggi: perlu segera diproses. Mendesak: stok kritis/berisiko operasional.">
                                                <select name="priority" class="form-select form-select-solid" required>
                                                    <option value="low" @selected(old('priority') === 'low')>Rendah</option>
                                                    <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                                                    <option value="high" @selected(old('priority') === 'high')>Tinggi</option>
                                                    <option value="urgent" @selected(old('priority') === 'urgent')>Mendesak</option>
                                                </select>
                                            </x-metronic.form-group>
                                        </div>
                                        <div class="col-md-5">
                                            <x-metronic.form-group name="reason" label="Alasan / Keperluan Pengajuan" required tooltip="Jelaskan kebutuhan pengajuan barang, misalnya stok menipis, kebutuhan promosi, atau permintaan stok rutin toko.">
                                                <input type="text" name="reason" value="{{ old('reason') }}" class="form-control form-control-solid" required placeholder="Contoh: Stok beras dan minyak goreng mendekati habis untuk kebutuhan akhir pekan.">
                                            </x-metronic.form-group>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Tabel Daftar Barang yang Diajukan (Lega & Leluasa) --}}
                            <div class="card card-bordered mb-6">
                                <div class="card-header border-bottom border-gray-200 py-3">
                                    <div class="card-title">
                                        <i class="ki-outline ki-basket fs-4 text-primary me-2"></i>
                                        <span class="fs-6 fw-bold">Daftar Barang yang Diajukan</span>
                                        <span class="badge badge-light-primary ms-2" id="pr-items-counter">1 item</span>
                                    </div>
                                    <div class="card-toolbar">
                                        <button type="button" class="btn btn-sm btn-light-primary" id="btn-add-pr-item">
                                            <i class="ki-outline ki-plus fs-6 me-1"></i> Tambah Baris Barang
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-row-dashed table-row-gray-200 align-middle mb-0 gs-4 gy-3" id="pr-items-table">
                                            <thead>
                                                <tr class="text-muted fw-bold text-uppercase fs-8 bg-light">
                                                    <th class="w-40px ps-4">#</th>
                                                    <th class="min-w-300px">Pilih Produk</th>
                                                    <th class="min-w-140px">Jumlah Order</th>
                                                    <th class="min-w-220px">Catatan Keperluan (Opsional)</th>
                                                    <th class="w-60px text-end pe-4">Hapus</th>
                                                </tr>
                                            </thead>
                                            <tbody id="pr-items-body">
                                                @php
                                                    $oldItems = old('items', [['product_id' => '', 'quantity' => 1, 'reason' => '']]);
                                                @endphp
                                                @foreach($oldItems as $idx => $item)
                                                    <tr class="pr-item-row" data-index="{{ $idx }}">
                                                        <td class="ps-4 text-muted fw-semibold pr-row-num">
                                                            {{ $idx + 1 }}
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-2 align-items-center">
                                                                <div class="flex-grow-1">
                                                                    <select name="items[{{ $idx }}][product_id]" class="form-select form-select-sm form-select-solid pr-product-select" required>
                                                                        <option value="">-- Pilih produk yang diajukan --</option>
                                                                        @foreach ($products as $product)
                                                                            <option value="{{ $product->id }}" 
                                                                                    data-sku="{{ $product->sku }}" 
                                                                                    data-name="{{ $product->name }}" 
                                                                                    data-unit="{{ $product->baseUnit?->name ?? 'Pcs' }}"
                                                                                    data-category="{{ $product->category?->name ?? 'Tanpa Kategori' }}" 
                                                                                    data-image="{{ $product->main_image_url }}"
                                                                                    @selected(($item['product_id'] ?? '') == $product->id)>
                                                                                {{ $product->sku }} - {{ $product->name }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <button type="button" class="btn btn-icon btn-sm btn-light-primary btn-pr-catalog flex-shrink-0" data-bs-toggle="modal" data-bs-target="#visualCatalogModal" data-row-index="{{ $idx }}" title="Pilih dari Katalog Visual">
                                                                    <i class="ki-outline ki-element-11 fs-6"></i>
                                                                </button>
                                                            </div>
                                                            <span class="text-muted fs-8 pr-unit-label d-block mt-1"></span>
                                                        </td>
                                                        <td>
                                                            <div class="input-group input-group-sm input-group-solid">
                                                                <input name="items[{{ $idx }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" type="number" step="any" min="0.0001" class="form-control form-control-sm form-control-solid text-end" required>
                                                                <span class="input-group-text pr-unit-addon">Pcs</span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <input name="items[{{ $idx }}][reason]" value="{{ $item['reason'] ?? '' }}" class="form-control form-control-sm form-control-solid" placeholder="Contoh: Stok pengaman toko">
                                                        </td>
                                                        <td class="text-end pe-4">
                                                            <button type="button" class="btn btn-icon btn-sm btn-light-danger btn-remove-pr-item" title="Hapus baris">
                                                                <i class="ki-outline ki-trash fs-6"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-3 bg-light-subtle border-top text-center">
                                        <button type="button" class="btn btn-sm btn-link text-primary fw-semibold" id="btn-add-pr-item-bottom">
                                            <i class="ki-outline ki-plus fs-6 me-1"></i> Tambah Baris Barang Baru
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-3">
                                <button type="button" class="btn btn-light" onclick="switchToPrHistoryTab()">Batal</button>
                                <button type="submit" class="btn btn-primary px-8">
                                    <i class="ki-outline ki-send fs-5 me-2"></i>Kirim Pengajuan Belanja
                                </button>
                            </div>
                        </form>
                    </div>
                @endcan

                {{-- ========================================== --}}
                {{-- TAB 3: REKOMENDASI DARI STOK MENIPIS       --}}
                {{-- ========================================== --}}
                <div class="tab-pane fade" id="tab_pr_recommendations" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
                        <div>
                            <h3 class="fs-4 fw-bolder mb-1 text-gray-900">Rekomendasi dari Stok Menipis</h3>
                            <div class="text-muted fs-7">Rekomendasi untuk kebutuhan internal gudang dan toko berdasarkan batas safety stock.</div>
                        </div>

                        @if ($recommendations->isNotEmpty())
                            @can('create', \App\Models\PurchaseOrder::class)
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#autoGeneratePoModal">
                                    <i class="ki-outline ki-flash fs-5 me-1"></i> Auto-Generate Order ke Supplier
                                </button>
                            @endcan
                        @endif
                    </div>

                    @if ($recommendations->isNotEmpty())
                        <div class="alert alert-light-primary d-flex align-items-center mb-5 border border-primary-subtle p-4">
                            <i class="ki-outline ki-information-5 fs-2x text-primary me-4"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-gray-900">Deteksi Kebutuhan Restok Otomatis</div>
                                <div class="text-muted fs-7">Terdapat <strong>{{ $recommendations->count() }} produk</strong> dengan sisa stok di bawah batas aman. Anda dapat menggunakan tombol <strong>Auto-Generate Order ke Supplier</strong> untuk langsung memecah pesanan PO per supplier dalam 1 klik.</div>
                            </div>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-4 gy-4">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase fs-8 bg-light">
                                    <th class="min-w-220px ps-4 rounded-start">Produk</th>
                                    <th class="min-w-160px">Lokasi Stok</th>
                                    <th class="min-w-100px text-end">Stok Fisik</th>
                                    <th class="min-w-110px text-end">Sisa Tersedia</th>
                                    <th class="min-w-100px text-end">Batas Min</th>
                                    <th class="min-w-110px text-end">Saran Order</th>
                                    <th class="min-w-160px pe-4 rounded-end">Supplier Rekanan Terakhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recommendations as $stock)
                                    @php
                                        $available = isset($stock->available_quantity) ? (float) $stock->available_quantity : ((float) $stock->quantity_on_hand - (float) $stock->quantity_reserved - (float) $stock->quantity_damaged);
                                        $target = (float) ($stock->product?->safety_stock > 0 ? $stock->product->safety_stock : (($stock->product?->minimum_stock ?: 0) * 2));
                                        $suggested = max(1, ceil($target - $available));
                                        $lastSupplier = $stock->product?->supplierProducts?->sortByDesc('last_supplied_at')->first()?->supplier;
                                    @endphp
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-40px me-3">
                                                    <x-metronic.image-preview url="{{ $stock->product?->main_image_url }}" title="{{ $stock->product?->name }}" />
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-gray-800 fs-6">{{ $stock->product?->name }}</span>
                                                    <span class="text-muted fs-8 d-block">{{ $stock->product?->sku }} &bull; Satuan: {{ $stock->product?->baseUnit?->name }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-secondary text-gray-800 fw-semibold">{{ $stock->workLocation?->name }}</span>
                                            <div class="text-muted fs-8 mt-1">Total di Lokasi</div>
                                        </td>
                                        <td class="text-end fw-semibold text-gray-800">{{ qty($stock->quantity_on_hand) }}</td>
                                        <td class="text-end fw-bold text-danger">{{ qty($available) }}</td>
                                        <td class="text-end text-muted">{{ qty($stock->product?->minimum_stock) }}</td>
                                        <td class="text-end fw-bolder text-primary fs-6">+{{ qty($suggested) }}</td>
                                        <td class="pe-4">
                                            @if($lastSupplier)
                                                <span class="badge badge-light-primary fw-semibold">{{ $lastSupplier->name }}</span>
                                            @else
                                                <span class="text-muted fs-8">Belum tercatat</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8">
                                            <x-metronic.empty-state title="Semua Stok Aman" description="Tidak ada produk yang berada di bawah batas minimum saat ini. Seluruh persediaan dalam kondisi aman." />
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

    {{-- Modal Auto-Generate PO dari Stok Kritis --}}
    @if ($recommendations->isNotEmpty())
        <div class="modal fade" id="autoGeneratePoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('purchasing.purchase-orders.auto-generate') }}">
                        @csrf
                        <div class="modal-header">
                            <h3 class="modal-title fw-bold">
                                <i class="ki-outline ki-flash fs-3 text-primary me-2"></i>
                                Auto-Generate Pesanan ke Supplier (PO)
                            </h3>
                            <button type="button" class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                                <i class="ki-outline ki-cross fs-2"></i>
                            </button>
                        </div>
                        <div class="modal-body py-4">
                            <div class="alert alert-light-primary d-flex align-items-center mb-4">
                                <i class="ki-outline ki-information-5 fs-2 text-primary me-3"></i>
                                <div class="fs-7">
                                    Pilih produk yang ingin dipesan. Sistem akan <strong>mengelompokkan pesanan secara otomatis per Supplier rekanan</strong> dan langsung menerbitkan draft PO siap kirim.
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle mb-0">
                                    <thead>
                                        <tr class="text-muted fw-bold text-uppercase fs-8 bg-light">
                                            <th class="w-40px ps-3">
                                                <input class="form-check-input" type="checkbox" id="check-all-auto-po" checked>
                                            </th>
                                            <th class="min-w-200px">Produk</th>
                                            <th class="min-w-120px">Lokasi Tujuan</th>
                                            <th class="min-w-200px">Pilih Supplier Rekanan</th>
                                            <th class="min-w-90px text-end">Sisa Stok</th>
                                            <th class="min-w-110px">Jumlah Order</th>
                                            <th class="min-w-130px pe-3">Harga Satuan (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recommendations as $i => $stock)
                                            @php
                                                $available = isset($stock->available_quantity) ? (float) $stock->available_quantity : ((float) $stock->quantity_on_hand - (float) $stock->quantity_reserved - (float) $stock->quantity_damaged);
                                                $target = (float) ($stock->product?->safety_stock > 0 ? $stock->product->safety_stock : (($stock->product?->minimum_stock ?: 0) * 2));
                                                $suggested = max(1, ceil($target - $available));
                                                $lastSupProd = $stock->product?->supplierProducts?->sortByDesc('last_supplied_at')->first();
                                                $defaultSupplierId = $lastSupProd?->supplier_id ?? ($suppliers->first()?->id ?? '');
                                                $lastPrice = $lastSupProd?->last_price ?? ($stock->product?->cost_price ?: 0);
                                            @endphp
                                            <tr>
                                                <td class="ps-3">
                                                    <input type="checkbox" name="items[{{ $i }}][selected]" value="1" class="form-check-input auto-po-checkbox" checked>
                                                    <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $stock->product_id }}">
                                                    <input type="hidden" name="items[{{ $i }}][destination_work_location_id]" value="{{ $stock->work_location_id }}">
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-gray-800">{{ $stock->product?->name }}</div>
                                                    <div class="text-muted fs-8">{{ $stock->product?->sku }} ({{ $stock->product?->baseUnit?->name }})</div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light-secondary">{{ $stock->workLocation?->name }}</span>
                                                </td>
                                                <td>
                                                    <select name="items[{{ $i }}][supplier_id]" class="form-select form-select-sm form-select-solid" required>
                                                        @foreach($suppliers as $supplier)
                                                            <option value="{{ $supplier->id }}" @selected($defaultSupplierId == $supplier->id)>{{ $supplier->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="text-end fw-bold text-danger">{{ qty($available) }}</td>
                                                <td>
                                                    <input type="number" step="any" min="0.0001" name="items[{{ $i }}][quantity]" value="{{ $suggested }}" class="form-control form-control-sm form-control-solid" required>
                                                </td>
                                                <td class="pe-3">
                                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" value="{{ $lastPrice }}" class="form-control form-control-sm form-control-solid">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-outline ki-flash fs-4 me-1"></i> Buat Draft Pesanan (Pisahkan per Supplier)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Katalog Visual Produk --}}
    <x-metronic.visual-catalog-modal 
        id="visualCatalogModal" 
        sourceSelector="#pr-items-body tr:first-child select.pr-product-select option"
        on-select="handleCatalogSelect" 
    />
@endsection

@push('scripts')
<script>
    let activePrRowIndex = 0;

    // Helper navigasi tab
    window.switchToPrCreateTab = function() {
        const tabEl = document.querySelector('a[href="#tab_pr_create"]');
        if (tabEl) {
            bootstrap.Tab.getOrCreateInstance(tabEl).show();
            window.scrollTo({ top: 300, behavior: 'smooth' });
        }
    };

    window.switchToPrHistoryTab = function() {
        const tabEl = document.querySelector('a[href="#tab_pr_history"]');
        if (tabEl) {
            bootstrap.Tab.getOrCreateInstance(tabEl).show();
        }
    };

    // Track active row for visual catalog
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-pr-catalog');
        if (btn) {
            activePrRowIndex = btn.dataset.rowIndex;
            const modal = document.getElementById('visualCatalogModal');
            if (modal && modal.__catalogInstance) {
                modal.__catalogInstance.updateSourceSelector(`select[name="items[${activePrRowIndex}][product_id]"] option`);
            }
        }
    });

    window.handleCatalogSelect = function(product) {
        const select = document.querySelector(`select[name="items[${activePrRowIndex}][product_id]"]`);
        if (select) {
            select.value = product.id;
            select.dispatchEvent(new Event('change'));
            toastr && toastr.success(product.name + ' dipilih.');
        }
    };

    // Add item row
    function addPrItemRow() {
        const tbody = document.getElementById('pr-items-body');
        const rows = tbody.querySelectorAll('.pr-item-row');
        const newIndex = rows.length;

        const firstRow = rows[0];
        if (!firstRow) return;

        const newRow = firstRow.cloneNode(true);
        newRow.setAttribute('data-index', newIndex);

        // Update product select
        const prodSelect = newRow.querySelector('.pr-product-select');
        prodSelect.setAttribute('name', `items[${newIndex}][product_id]`);
        prodSelect.value = '';

        // Update catalog button
        const catBtn = newRow.querySelector('.btn-pr-catalog');
        if (catBtn) catBtn.setAttribute('data-row-index', newIndex);

        // Update unit label
        const unitLabel = newRow.querySelector('.pr-unit-label');
        if (unitLabel) unitLabel.innerText = '';
        const unitAddon = newRow.querySelector('.pr-unit-addon');
        if (unitAddon) unitAddon.innerText = 'Pcs';

        // Update quantity
        const qtyInput = newRow.querySelector('input[type="number"]');
        qtyInput.setAttribute('name', `items[${newIndex}][quantity]`);
        qtyInput.value = 1;

        // Update reason
        const reasonInput = newRow.querySelector('input[placeholder*="Contoh"]');
        if (reasonInput) {
            reasonInput.setAttribute('name', `items[${newIndex}][reason]`);
            reasonInput.value = '';
        }

        tbody.appendChild(newRow);
        reindexPrRows();
    }

    document.getElementById('btn-add-pr-item')?.addEventListener('click', addPrItemRow);
    document.getElementById('btn-add-pr-item-bottom')?.addEventListener('click', addPrItemRow);

    // Remove item row
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-remove-pr-item');
        if (btn) {
            const tbody = document.getElementById('pr-items-body');
            const rows = tbody.querySelectorAll('.pr-item-row');
            if (rows.length <= 1) {
                toastr && toastr.warning('Minimal harus ada 1 barang dalam pengajuan.');
                return;
            }
            btn.closest('.pr-item-row').remove();
            reindexPrRows();
        }
    });

    function reindexPrRows() {
        const rows = document.querySelectorAll('#pr-items-body .pr-item-row');
        rows.forEach(function(row, idx) {
            row.setAttribute('data-index', idx);
            const numEl = row.querySelector('.pr-row-num');
            if (numEl) numEl.innerText = idx + 1;
            const select = row.querySelector('.pr-product-select');
            if (select) select.setAttribute('name', `items[idx][product_id]`.replace('idx', idx));
            const catBtn = row.querySelector('.btn-pr-catalog');
            if (catBtn) catBtn.setAttribute('data-row-index', idx);
            const qty = row.querySelector('input[type="number"]');
            if (qty) qty.setAttribute('name', `items[idx][quantity]`.replace('idx', idx));
            const reason = row.querySelector('input[placeholder*="Contoh"]');
            if (reason) reason.setAttribute('name', `items[idx][reason]`.replace('idx', idx));
        });

        const counter = document.getElementById('pr-items-counter');
        if (counter) counter.innerText = `${rows.length} item`;
    }

    // Auto update unit label when product changes
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('pr-product-select')) {
            const selectedOpt = e.target.options[e.target.selectedIndex];
            const unit = selectedOpt ? selectedOpt.getAttribute('data-unit') : 'Pcs';
            const row = e.target.closest('.pr-item-row');
            const label = row?.querySelector('.pr-unit-label');
            const addon = row?.querySelector('.pr-unit-addon');
            if (label) {
                label.innerText = unit ? `Satuan: ${unit}` : '';
            }
            if (addon) {
                addon.innerText = unit || 'Pcs';
            }
        }
    });

    // Check all checkbox for Auto PO modal
    document.getElementById('check-all-auto-po')?.addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('.auto-po-checkbox').forEach(cb => cb.checked = checked);
    });

    // Inisialisasi tampilan awal
    document.addEventListener('DOMContentLoaded', function() {
        reindexPrRows();
    });
</script>
@endpush
