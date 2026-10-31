@extends('layouts.metronic.app')

@section('title', 'Pengajuan Belanja (Internal) - ' . config('app.name'))
@section('page_title', 'Pengajuan Belanja (Internal)')

@section('page_guide')
    <x-metronic.page-guide id="purchasing-request-index" title="Panduan Pengajuan Belanja">
        <x-slot:function><p>Pengajuan Belanja (Purchase Request / Permintaan Pembelian) adalah formulir internal kebutuhan barang dari gudang/toko sebelum diputuskan untuk dibeli dari pemasok luar. Dokumen ini murni kebutuhan internal organisasi.</p></x-slot:function>
        <x-slot:workflow><ol><li>Staf gudang/toko mengajukan daftar barang yang dibutuhkan (bisa banyak barang sekaligus).</li><li>Kepala bagian/manajer meninjau dan menyetujui pengajuan.</li><li>Tim Purchasing memilih supplier rekanan dan mengonversi pengajuan menjadi Pesanan ke Supplier (PO).</li></ol></x-slot:workflow>
        <x-slot:warnings><p>Rekomendasi stok minimum hanya sebagai acuan kebutuhan. Anda dapat menggunakan tombol Auto-Generate untuk langsung membuat draft PO dari stok kritis.</p></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <div class="row g-5">
        @can('create', \App\Models\PurchaseRequest::class)
            <div class="col-xl-5">
                <x-metronic.card title="Buat Permintaan Internal - Pengajuan Belanja">
                    <div class="text-muted fs-7 mb-5">Ajukan daftar kebutuhan pembelian untuk gudang atau toko di wilayah kerja Anda.</div>
                    <form method="POST" action="{{ route('purchasing.requests.store') }}" id="form-purchase-request">
                        @csrf
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <x-metronic.form-group name="warehouse_id" label="Gudang / Lokasi Tujuan" required help="Gudang yang membutuhkan barang dan akan menjadi tujuan penerimaan barang.">
                                    <select name="warehouse_id" class="form-select form-select-solid" required>
                                        <option value="">Pilih gudang</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->code }} - {{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                </x-metronic.form-group>
                            </div>
                            <div class="col-md-6">
                                <x-metronic.form-group name="priority" label="Tingkat Prioritas" required help="Rendah: dapat dijadwalkan. Normal: kebutuhan rutin. Tinggi: perlu segera diproses. Mendesak: stok kritis/berisiko operasional.">
                                    <select name="priority" class="form-select form-select-solid" required>
                                        <option value="low" @selected(old('priority') === 'low')>Rendah</option>
                                        <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                                        <option value="high" @selected(old('priority') === 'high')>Tinggi</option>
                                        <option value="urgent" @selected(old('priority') === 'urgent')>Mendesak</option>
                                    </select>
                                </x-metronic.form-group>
                            </div>
                        </div>

                        <x-metronic.form-group name="reason" label="Alasan / Keperluan Pengajuan" required help="Jelaskan kebutuhan pengajuan barang, misalnya stok menipis, kebutuhan promosi, atau permintaan stok rutin toko.">
                            <textarea name="reason" rows="2" class="form-control form-control-solid" required placeholder="Contoh: Stok beras dan minyak goreng mendekati habis untuk kebutuhan akhir pekan.">{{ old('reason') }}</textarea>
                        </x-metronic.form-group>

                        <div class="border rounded p-4 mb-5 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-bold fs-6">Daftar Barang yang Diajukan</div>
                                    <div class="text-muted fs-8">Tambahkan satu atau beberapa barang sekaligus.</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-light-primary" id="btn-add-pr-item">
                                        <i class="ki-outline ki-plus fs-6 me-1"></i> Tambah Baris
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle mb-0" id="pr-items-table">
                                    <thead>
                                        <tr class="text-muted fw-bold text-uppercase fs-8">
                                            <th class="min-w-180px">Produk</th>
                                            <th class="min-w-90px">Jumlah</th>
                                            <th class="min-w-120px">Catatan</th>
                                            <th class="w-40px text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pr-items-body">
                                        @php
                                            $oldItems = old('items', [['product_id' => '', 'quantity' => 1, 'reason' => '']]);
                                        @endphp
                                        @foreach($oldItems as $idx => $item)
                                            <tr class="pr-item-row" data-index="{{ $idx }}">
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <select name="items[{{ $idx }}][product_id]" class="form-select form-select-sm form-select-solid pr-product-select" required>
                                                            <option value="">Pilih produk</option>
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
                                                        <button type="button" class="btn btn-icon btn-sm btn-light-primary btn-pr-catalog" data-bs-toggle="modal" data-bs-target="#visualCatalogModal" data-row-index="{{ $idx }}" title="Pilih dari Katalog Visual">
                                                            <i class="ki-outline ki-element-11 fs-6"></i>
                                                        </button>
                                                    </div>
                                                    <span class="text-muted fs-8 pr-unit-label d-block mt-1"></span>
                                                </td>
                                                <td>
                                                    <input name="items[{{ $idx }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" type="number" step="any" min="0.0001" class="form-control form-control-sm form-control-solid" required>
                                                </td>
                                                <td>
                                                    <input name="items[{{ $idx }}][reason]" value="{{ $item['reason'] ?? '' }}" class="form-control form-control-sm form-control-solid" placeholder="Opsional">
                                                </td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-icon btn-sm btn-light-danger btn-remove-pr-item" title="Hapus baris">
                                                        <i class="ki-outline ki-trash fs-6"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100">
                            <i class="ki-outline ki-send fs-5 me-2"></i>Kirim Pengajuan Belanja
                        </button>
                    </form>
                </x-metronic.card>
            </div>
        @endcan

        <div class="@can('create', \App\Models\PurchaseRequest::class) col-xl-7 @else col-12 @endcan">
            <x-metronic.card title="Rekomendasi dari Stok Menipis">
                <x-slot:toolbar>
                    @if ($recommendations->isNotEmpty())
                        @can('create', \App\Models\PurchaseOrder::class)
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#autoGeneratePoModal">
                                <i class="ki-outline ki-flash fs-5 me-1"></i> Auto-Generate Order ke Supplier
                            </button>
                        @endcan
                    @endif
                </x-slot:toolbar>

                <div class="alert alert-light-primary d-flex align-items-start mb-4">
                    <i class="ki-outline ki-information-5 fs-2 text-primary me-3"></i>
                    <div>
                        <div class="fw-semibold">Rekomendasi untuk kebutuhan internal gudang</div>
                        <div class="text-muted fs-7">Produk di bawah ini membutuhkan pengadaan karena sisa stok tersedia sudah berada di bawah batas minimum/safety stock. Klik tombol <strong>Auto-Generate Order</strong> untuk langsung membuat draft PO per supplier.</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase fs-7">
                                <th>Produk</th>
                                <th>Lokasi Stok</th>
                                <th class="text-end">Stok Fisik</th>
                                <th class="text-end">Sisa Tersedia</th>
                                <th class="text-end">Batas Min</th>
                                <th class="text-end">Saran Order</th>
                                <th>Supplier Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recommendations as $stock)
                                @php
                                    $available = (float) $stock->quantity_on_hand - (float) $stock->quantity_reserved - (float) $stock->quantity_damaged;
                                    $target = (float) ($stock->product?->safety_stock > 0 ? $stock->product->safety_stock : (($stock->product?->minimum_stock ?: 0) * 2));
                                    $suggested = max(1, ceil($target - $available));
                                    $lastSupplier = $stock->product?->supplierProducts?->sortByDesc('last_supplied_at')->first()?->supplier;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-35px me-3">
                                                <x-metronic.image-preview url="{{ $stock->product?->main_image_url }}" title="{{ $stock->product?->name }}" />
                                            </div>
                                            <div>
                                                <span class="fw-semibold d-block text-gray-800">{{ $stock->product?->name }}</span>
                                                <span class="text-muted fs-8 d-block">{{ $stock->product?->sku }} / {{ $stock->product?->baseUnit?->name }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        {{ $stock->workLocation?->name }}
                                        <div class="text-muted fs-8">{{ $stock->warehouseLocation?->full_code ?: 'Tanpa rak khusus' }}</div>
                                    </td>
                                    <td class="text-end">{{ qty($stock->quantity_on_hand) }}</td>
                                    <td class="text-end fw-bold text-danger">{{ qty($available) }}</td>
                                    <td class="text-end">{{ qty($stock->product?->minimum_stock) }}</td>
                                    <td class="text-end fw-bold text-primary">{{ qty($suggested) }}</td>
                                    <td>
                                        @if($lastSupplier)
                                            <span class="badge badge-light-primary">{{ $lastSupplier->name }}</span>
                                        @else
                                            <span class="text-muted fs-8">Belum ada</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-metronic.empty-state title="Semua Stok Aman" description="Tidak ada produk yang berada di bawah batas minimum saat ini." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Riwayat Pengajuan Belanja" class="mt-5">
                <form method="GET" class="row g-3 mb-5">
                    <div class="col-sm-8 col-md-5">
                        <label class="form-label fs-7">Status Dokumen</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="">Semua status</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-4 col-md-3 d-flex align-items-end"><button class="btn btn-light-primary w-100">Terapkan Filter</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase fs-7">
                                <th>No. Dokumen</th>
                                <th>Gudang Tujuan</th>
                                <th>Pemohon</th>
                                <th>Prioritas</th>
                                <th>Status</th>
                                <th>Jml Item</th>
                                <th class="text-end">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $purchaseRequest)
                                <tr>
                                    <td>
                                        <a href="{{ route('purchasing.requests.show', $purchaseRequest) }}" class="fw-bold text-primary">{{ $purchaseRequest->number }}</a>
                                        <div class="text-muted fs-8">{{ $purchaseRequest->submitted_at?->format('d/m/Y H:i') }}</div>
                                    </td>
                                    <td>{{ $purchaseRequest->warehouse?->name }}</td>
                                    <td>{{ $purchaseRequest->requester?->name }}</td>
                                    <td>
                                        @php
                                            $priorityClass = match($purchaseRequest->priority) {
                                                'urgent' => 'badge-light-danger',
                                                'high' => 'badge-light-warning',
                                                'low' => 'badge-light-secondary',
                                                default => 'badge-light-info',
                                            };
                                        @endphp
                                        <span class="badge {{ $priorityClass }}">{{ ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Mendesak'][$purchaseRequest->priority] ?? ucfirst($purchaseRequest->priority) }}</span>
                                    </td>
                                    <td><x-metronic.status-badge :status="$purchaseRequest->status" /></td>
                                    <td>{{ $purchaseRequest->items->count() }} item</td>
                                    <td class="text-end">
                                        <a href="{{ route('purchasing.requests.show', $purchaseRequest) }}" class="btn btn-sm btn-light-primary">Buka</a>
                                        @if ($purchaseRequest->convertedPurchaseOrder)
                                            @can('view', $purchaseRequest->convertedPurchaseOrder)
                                                <a href="{{ route('purchasing.purchase-orders.show', $purchaseRequest->convertedPurchaseOrder) }}" class="btn btn-sm btn-light-success">Lihat PO</a>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-metronic.empty-state title="Belum ada pengajuan" description="Pengajuan kebutuhan belanja internal akan tampil di sini." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $requests->links() }}
            </x-metronic.card>
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
                                <i class="ki-outline ki-flash fs-2 text-primary me-2"></i>Auto-Generate Pesanan ke Supplier (PO)
                            </h3>
                            <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                                <i class="ki-outline ki-cross fs-1"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light-primary d-flex align-items-center p-4 mb-4">
                                <i class="ki-outline ki-shield-tick fs-2x text-primary me-3"></i>
                                <div class="fs-7">
                                    Pilih produk yang ingin dipesan. Sistem akan <strong>mengelompokkan pesanan secara otomatis per Supplier</strong> dan menerbitkan draft PO siap kirim.
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle mb-0">
                                    <thead>
                                        <tr class="text-muted fw-bold text-uppercase fs-8">
                                            <th class="w-40px">
                                                <input class="form-check-input" type="checkbox" id="check-all-auto-po" checked>
                                            </th>
                                            <th class="min-w-200px">Produk</th>
                                            <th class="min-w-120px">Lokasi Tujuan</th>
                                            <th class="min-w-200px">Pilih Supplier Rekanan</th>
                                            <th class="min-w-100px text-end">Sisa Stok</th>
                                            <th class="min-w-110px">Jumlah Order</th>
                                            <th class="min-w-120px">Harga Satuan (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recommendations as $i => $stock)
                                            @php
                                                $available = (float) $stock->quantity_on_hand - (float) $stock->quantity_reserved - (float) $stock->quantity_damaged;
                                                $target = (float) ($stock->product?->safety_stock > 0 ? $stock->product->safety_stock : (($stock->product?->minimum_stock ?: 0) * 2));
                                                $suggested = max(1, ceil($target - $available));
                                                $lastSupProd = $stock->product?->supplierProducts?->sortByDesc('last_supplied_at')->first();
                                                $defaultSupplierId = $lastSupProd?->supplier_id ?? ($suppliers->first()?->id ?? '');
                                                $lastPrice = $lastSupProd?->last_price ?? ($stock->product?->cost_price ?: 0);
                                            @endphp
                                            <tr>
                                                <td>
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
                                                <td>
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

    <x-metronic.visual-catalog-modal 
        id="visualCatalogModal" 
        sourceSelector="#pr-items-body tr:first-child select.pr-product-select option"
        on-select="handleCatalogSelect" 
    />
@endsection

@push('scripts')
<script>
    let activePrRowIndex = 0;

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
    document.getElementById('btn-add-pr-item')?.addEventListener('click', function() {
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

        // Update quantity
        const qtyInput = newRow.querySelector('input[type="number"]');
        qtyInput.setAttribute('name', `items[${newIndex}][quantity]`);
        qtyInput.value = 1;

        // Update reason
        const reasonInput = newRow.querySelector('input[placeholder="Opsional"]');
        if (reasonInput) {
            reasonInput.setAttribute('name', `items[${newIndex}][reason]`);
            reasonInput.value = '';
        }

        tbody.appendChild(newRow);
    });

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
        document.querySelectorAll('#pr-items-body .pr-item-row').forEach(function(row, idx) {
            row.setAttribute('data-index', idx);
            const select = row.querySelector('.pr-product-select');
            if (select) select.setAttribute('name', `items[idx][product_id]`.replace('idx', idx));
            const catBtn = row.querySelector('.btn-pr-catalog');
            if (catBtn) catBtn.setAttribute('data-row-index', idx);
            const qty = row.querySelector('input[type="number"]');
            if (qty) qty.setAttribute('name', `items[idx][quantity]`.replace('idx', idx));
            const reason = row.querySelector('input[placeholder="Opsional"]');
            if (reason) reason.setAttribute('name', `items[idx][reason]`.replace('idx', idx));
        });
    }

    // Auto update unit label when product changes
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('pr-product-select')) {
            const selectedOpt = e.target.options[e.target.selectedIndex];
            const unit = selectedOpt ? selectedOpt.getAttribute('data-unit') : '';
            const row = e.target.closest('.pr-item-row');
            const label = row?.querySelector('.pr-unit-label');
            if (label) {
                label.innerText = unit ? `Satuan: ${unit}` : '';
            }
        }
    });

    // Check all checkbox for Auto PO modal
    document.getElementById('check-all-auto-po')?.addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('.auto-po-checkbox').forEach(cb => cb.checked = checked);
    });
</script>
@endpush
