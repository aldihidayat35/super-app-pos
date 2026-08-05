@extends('layouts.metronic.app')
@section('title', $product->name)
@section('page_title', 'Detail Produk')
@section('toolbar_actions')
    @can('printBarcode', App\Models\Product::class)
        <a href="{{ route('admin.products.barcodes.index', ['product_id' => $product->id]) }}" class="btn btn-light">
            <i class="ki-outline ki-printer fs-5 me-2"></i>Cetak Barcode
        </a>
    @endcan
    @can('update', $product)
        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-primary">
            <i class="ki-outline ki-pencil fs-5 me-2"></i>Edit Produk
        </a>
    @endcan
@endsection

@section('page_guide')
    <x-metronic.page-guide id="admin-product-show" title="Panduan Detail Produk">
        <x-slot:function><p>Halaman menampilkan rincian produk dengan foto, barcode, stok, harga, supplier, dan histori mutasi.</p></x-slot:function>
        <x-slot:parts><ul><li><strong>Ringkasan:</strong> nama, SKU, kategori, merek, satuan, lokasi default, stok, harga.</li><li><strong>Tab Foto & Barcode:</strong> preview gambar dan barcode produk.</li><li><strong>Tab Stok:</strong> stok per lokasi dan histori mutasi.</li><li><strong>Tab Harga:</strong> harga jual dan harga pokok.</li><li><strong>Tab Supplier:</strong> supplier yang menyediakan produk ini.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Halaman ini read-only. Edit produk dari tombol toolbar.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa ringkasan produk.</li><li>Buka tab sesuai kebutuhan.</li></ol></x-slot:operation>
    </x-metronic.page-guide>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#audit' || new URLSearchParams(window.location.search).has('audit_page')) {
        const trigger = document.getElementById('audit-tab');
        if (trigger && window.bootstrap?.Tab) window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
});
</script>
@endpush

@section('content')
    <div class="row g-6">
        {{-- Left Column: Summary & Image --}}
        <div class="col-lg-4">
            <x-metronic.card title="Info Produk">
                @if($product->main_image_path || $product->images->isNotEmpty())
                    <div class="mb-4 text-center">
                        @php
                            $primaryImage = $product->images->firstWhere('is_primary');
                            $displayImage = $primaryImage ? $primaryImage->path : $product->main_image_path;
                        @endphp
                        @if($displayImage)
                            <img src="{{ asset('storage/' . $displayImage) }}" alt="{{ $product->name }}" class="img-fluid rounded max-h-200px object-fit-cover">
                        @else
                            <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="height: 150px;">
                                <i class="ki-outline ki-scheme fs-1 text-muted"></i>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="mb-4 text-center">
                        <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="height: 150px;">
                            <i class="ki-outline ki-scheme fs-1 text-muted"></i>
                        </div>
                    </div>
                @endif
                <div class="fs-3 fw-bold text-primary mb-1">{{ $product->name }}</div>
                <div class="text-muted mb-4">SKU: {{ $product->sku }}</div>

                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="text-muted fs-7 mb-1">Kategori</div>
                        <div class="fw-semibold">{{ $product->category?->name ?: '-' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted fs-7 mb-1">Subkategori</div>
                        <div class="fw-semibold">{{ $product->subcategory?->name ?: '-' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted fs-7 mb-1">Merek</div>
                        <div class="fw-semibold">{{ $product->brand?->name ?: '-' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted fs-7 mb-1">Satuan Dasar</div>
                        <div class="fw-semibold">{{ $product->baseUnit?->name ?? '-' }} {{ $product->baseUnit?->symbol ?? '' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted fs-7 mb-1">Gudang Default</div>
                        <div class="fw-semibold">{{ $product->defaultWarehouse?->name ?: '-' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted fs-7 mb-1">Berat</div>
                        <div class="fw-semibold">{{ $product->weight ? number_format($product->weight, 2) . ' kg' : '-' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted fs-7 mb-1">Volume</div>
                        <div class="fw-semibold">{{ $product->volume ? number_format($product->volume, 2) . ' m³' : '-' }}</div>
                    </div>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <x-metronic.status-badge :status="$product->status->value" :label="$product->status->label()" />
                    @if($product->minimum_stock > 0 || $product->safety_stock > 0)
                        <span class="badge bg-light-warning text-warning">
                            Min: {{ number_format($product->minimum_stock, 0) }}
                            @if($product->safety_stock > 0) · Aman: {{ number_format($product->safety_stock, 0) }}@endif
                        </span>
                    @endif
                </div>
            </x-metronic.card>

            {{-- Stok Ringkasan --}}
            @if($stockSummary && $stockSummary->total_on_hand > 0)
            <x-metronic.card title="Stok Ringkasan" class="mt-6">
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="text-muted fs-7">On Hand</div>
                        <div class="fw-bold fs-4 text-success">{{ number_format($stockSummary->total_on_hand, 0) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted fs-7">Reserved</div>
                        <div class="fw-bold fs-4 text-warning">{{ number_format($stockSummary->total_reserved, 0) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted fs-7">Available</div>
                        <div class="fw-bold fs-4">{{ number_format((float)$stockSummary->total_on_hand - (float)$stockSummary->total_reserved, 0) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted fs-7">Lokasi</div>
                        <div class="fw-bold fs-4">{{ $stockSummary->total_locations }}</div>
                    </div>
                </div>
                @if($stockSummary->total_value > 0)
                <div class="mt-3 pt-3 border-top">
                    <div class="text-muted fs-7">Nilai Stok (HPP)</div>
                    <div class="fw-bold fs-5">Rp {{ number_format((float)$stockSummary->total_value, 0, ',', '.') }}</div>
                </div>
                @endif
            </x-metronic.card>
            @endif

            {{-- Harga Ringkasan --}}
            @if($product->prices->isNotEmpty() || $product->cost_price > 0 || $product->minimum_price > 0)
            <x-metronic.card title="Harga" class="mt-6">
                <table class="table table-row-dashed align-middle fs-7">
                    <tbody>
                        @if($product->cost_price > 0)
                        <tr>
                            <td class="text-muted">HPP (Cost)</td>
                            <td class="fw-semibold text-end">Rp {{ number_format((float)$product->cost_price, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($product->minimum_price > 0)
                        <tr>
                            <td class="text-muted">Min. Harga</td>
                            <td class="fw-semibold text-end text-danger">Rp {{ number_format((float)$product->minimum_price, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @foreach($product->prices->take(5) as $price)
                        <tr>
                            <td class="text-muted">{{ ucfirst($price->price_ring) }} <span class="text-muted fw-normal">({{ $price->channel }})</span></td>
                            <td class="fw-semibold text-end text-success">Rp {{ number_format((float)$price->recommended_price, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-metronic.card>
            @endif
        </div>

        {{-- Right Column: Tabs --}}
        <div class="col-lg-8">
            <x-metronic.card title="Detail Produk" class="h-100">
                <ul class="nav nav-tabs nav-line-tabs mb-5" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">Informasi</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="media-tab" data-bs-toggle="tab" data-bs-target="#media" type="button" role="tab">Foto & Barcode</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab">Stok & Mutasi</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pricing-tab" data-bs-toggle="tab" data-bs-target="#pricing" type="button" role="tab">Harga</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="supplier-tab" data-bs-toggle="tab" data-bs-target="#supplier" type="button" role="tab">Supplier</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit" type="button" role="tab">Audit</button>
                    </li>
                </ul>

                <div class="tab-content" id="productTabContent">
                    {{-- Tab: Informasi --}}
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="text-muted fs-7 mb-1">Model</div>
                                <div class="fw-semibold">{{ $product->model ?: '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted fs-7 mb-1">Ukuran</div>
                                <div class="fw-semibold">{{ $product->size ?: '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted fs-7 mb-1">Warna</div>
                                <div class="fw-semibold">{{ $product->color ?: '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted fs-7 mb-1">Material</div>
                                <div class="fw-semibold">{{ $product->material ?: '-' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted fs-7 mb-1">Deskripsi</div>
                                <div class="fw-semibold">{{ $product->description ?: '-' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted fs-7 mb-1">Atribut Tambahan</div>
                                @if($product->attributes)
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($product->attributes as $key => $value)
                                            <span class="badge bg-light">{{ $key }}: {{ is_array($value) ? implode(', ', $value) : $value }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-muted">-</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Tab: Foto & Barcode --}}
                    <div class="tab-pane fade" id="media" role="tabpanel">
                        {{-- Gallery Foto --}}
                        <div class="mb-5">
                            <h6 class="text-muted fs-7 text-uppercase mb-3">Foto Produk</h6>
                            @if($product->images->isNotEmpty() || $product->main_image_path)
                                <div class="row g-3">
                                    @if($product->main_image_path && $product->images->where('path', $product->main_image_path)->isEmpty())
                                        <div class="col-md-4">
                                            <div class="border rounded overflow-hidden bg-white">
                                                <img src="{{ asset('storage/' . $product->main_image_path) }}" alt="{{ $product->name }}" class="w-100" style="height: 180px; object-fit: cover;">
                                                <div class="p-2 bg-light">
                                                    <span class="badge bg-primary">Foto Utama</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    @foreach($product->images as $image)
                                        <div class="col-md-4">
                                            <div class="border rounded overflow-hidden bg-white {{ $image->is_primary ? 'border-primary border-2' : '' }}">
                                                <img src="{{ asset('storage/' . $image->path) }}" alt="{{ $image->alt_text ?? $product->name }}" class="w-100" style="height: 180px; object-fit: cover;">
                                                <div class="p-2 bg-light d-flex justify-content-between align-items-center">
                                                    <small class="text-muted text-truncate">{{ $image->alt_text ?: 'Foto ' . $image->id }}</small>
                                                    @if($image->is_primary)
                                                        <span class="badge bg-primary">Utama</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <x-metronic.empty-state title="Belum ada foto" description="Foto utama dapat diunggah dari form produk." />
                            @endif
                        </div>

                        {{-- Barcode --}}
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted fs-7 text-uppercase mb-0">Barcode / QR Code</h6>
                                @can('printBarcode', App\Models\Product::class)
                                    <a href="{{ route('admin.products.barcodes.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-light">
                                        <i class="ki-outline ki-printer me-1"></i>Cetak
                                    </a>
                                @endcan
                            </div>
                            @if($product->barcodes->isNotEmpty())
                                <div class="row g-4">
                                    @foreach($product->barcodes as $barcode)
                                        <div class="col-md-4">
                                            <div class="card border-0 shadow-sm">
                                                <div class="card-body text-center p-4">
                                                    <div class="mb-3 p-3 bg-white border rounded d-inline-block">
                                                        @if($barcode->type === 'qr')
                                                            {!! (new \Milon\Barcode\DNS2D())->getBarcodeHTML($barcode->code, 'QRCODE', 4, 4) !!}
                                                        @else
                                                            {!! (new \Milon\Barcode\DNS1D())->getBarcodeHTML($barcode->code, 'C128', 2, 60) !!}
                                                        @endif
                                                    </div>
                                                    <div class="fw-bold fs-6 font-monospace text-dark">{{ $barcode->code }}</div>
                                                    <div class="text-muted small mt-1">
                                                        <span class="badge bg-light">{{ ucfirst($barcode->type) }}</span>
                                                        @if($barcode->productUnit?->unit?->name)
                                                            <span class="text-muted ms-1">· {{ $barcode->productUnit->unit->name }}</span>
                                                        @endif
                                                    </div>
                                                    @if($barcode->is_primary)
                                                        <div class="mt-2"><span class="badge bg-primary">Barcode Utama</span></div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body text-center p-4">
                                        <div class="mb-3 p-3 bg-white border rounded d-inline-block">
                                            {!! (new \Milon\Barcode\DNS1D())->getBarcodeHTML($product->sku, 'C128', 2, 60) !!}
                                        </div>
                                        <div class="fw-bold fs-6 font-monospace text-dark">{{ $product->sku }}</div>
                                        <div class="text-muted small mt-1">
                                            <span class="badge bg-light">SKU</span>
                                            <span class="text-muted ms-1">· Default Barcode</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tab: Stok & Mutasi --}}
                    <div class="tab-pane fade" id="stock" role="tabpanel">
                        <div class="border border-gray-300 border-dashed rounded p-5 mb-6">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-5">
                                <div>
                                    <h6 class="text-gray-900 mb-1">Pengaturan Stok Master</h6>
                                    <div class="text-muted fs-7">Batas operasional yang disimpan dari form produk.</div>
                                </div>
                                @can('update', $product)
                                    <a href="{{ route('admin.products.edit', $product) }}#tab_stock" class="btn btn-sm btn-light-primary">
                                        <i class="ki-outline ki-pencil fs-5 me-1"></i>Ubah Pengaturan
                                    </a>
                                @endcan
                            </div>
                            <div class="row g-4">
                                <div class="col-6 col-lg-4">
                                    <div class="text-muted fs-7 mb-1">Minimum Order</div>
                                    <div class="fw-bold fs-5">{{ qty($product->minimum_order) }}</div>
                                </div>
                                <div class="col-6 col-lg-4">
                                    <div class="text-muted fs-7 mb-1">Minimum Stock</div>
                                    <div class="fw-bold fs-5 text-warning">{{ qty($product->minimum_stock) }}</div>
                                </div>
                                <div class="col-6 col-lg-4">
                                    <div class="text-muted fs-7 mb-1">Safety Stock</div>
                                    <div class="fw-bold fs-5">{{ qty($product->safety_stock) }}</div>
                                </div>
                                <div class="col-6 col-lg-4">
                                    <div class="text-muted fs-7 mb-1">Gudang Default</div>
                                    <div class="fw-semibold">{{ $product->defaultWarehouse?->name ?: 'Belum ditentukan' }}</div>
                                </div>
                                <div class="col-6 col-lg-4">
                                    <div class="text-muted fs-7 mb-1">Berat</div>
                                    <div class="fw-semibold">{{ $product->weight ? qty($product->weight).' kg' : '-' }}</div>
                                </div>
                                <div class="col-6 col-lg-4">
                                    <div class="text-muted fs-7 mb-1">Volume</div>
                                    <div class="fw-semibold">{{ $product->volume ? qty($product->volume).' m³' : '-' }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Stok Per Lokasi --}}
                        <div class="mb-5">
                            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                <h6 class="text-muted fs-7 text-uppercase mb-0">Saldo Stok Per Lokasi</h6>
                                @can('stock.view')
                                    <a href="{{ route('warehouse.stocks.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-light">Buka Saldo Stok</a>
                                @endcan
                            </div>
                            @if($product->stocks->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle fs-7">
                                        <thead>
                                            <tr class="text-muted fw-bold">
                                                <th>Gudang</th>
                                                <th>Lokasi</th>
                                                <th class="text-end">On Hand</th>
                                                <th class="text-end">Reserved</th>
                                                <th class="text-end">Damaged</th>
                                                <th class="text-end">Available</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($product->stocks as $stock)
                                                <tr>
                                                    <td class="fw-semibold">{{ $stock->warehouseLocation?->warehouse?->name ?: '-' }}</td>
                                                    <td>{{ $stock->warehouseLocation?->full_code ?: '-' }}</td>
                                                    <td class="text-end">{{ number_format((float)$stock->quantity_on_hand, 0) }}</td>
                                                    <td class="text-end text-warning">{{ number_format((float)$stock->quantity_reserved, 0) }}</td>
                                                    <td class="text-end text-danger">{{ number_format((float)$stock->quantity_damaged, 0) }}</td>
                                                    <td class="text-end fw-bold">{{ number_format((float)$stock->available_quantity, 0) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <x-metronic.empty-state title="Belum ada saldo stok" description="Menyimpan master produk tidak membuat saldo stok. Saldo akan terbentuk setelah penerimaan barang, transfer masuk, retur, atau stok opname diposting." icon="ki-outline ki-box">
                                    <div class="d-flex justify-content-center flex-wrap gap-2">
                                        @can('stock.view')
                                            <a href="{{ route('warehouse.stocks.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-light-primary">Lihat Modul Stok</a>
                                        @endcan
                                        @can('stock_adjustments.create')
                                            <a href="{{ route('warehouse.stock-opnames.index') }}" class="btn btn-sm btn-primary">Buat Stok Awal via Opname</a>
                                        @endcan
                                    </div>
                                </x-metronic.empty-state>
                            @endif
                        </div>

                        {{-- Histori Mutasi --}}
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted fs-7 text-uppercase mb-0">Histori Mutasi Terbaru</h6>
                                <a href="{{ route('warehouse.stock-card.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-light">Lihat Semua</a>
                            </div>
                            @if($product->stockMutations->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle fs-7">
                                        <thead>
                                            <tr class="text-muted fw-bold">
                                                <th>Waktu</th>
                                                <th>Jenis</th>
                                                <th>Lokasi</th>
                                                <th class="text-end">Perubahan</th>
                                                <th>Ref</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($product->stockMutations as $mutation)
                                                <tr>
                                                    <td>{{ $mutation->occurred_at?->format('d/m/Y H:i') }}</td>
                                                    <td>
                                                        <span class="badge {{ in_array($mutation->mutation_type?->value ?? '', ['receive', 'transfer_in', 'recover', 'return_in']) ? 'bg-success' : 'bg-danger' }}">
                                                            {{ $mutation->mutation_type?->label() }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $mutation->warehouseLocation?->full_code ?: $mutation->workLocation?->name ?: '-' }}</td>
                                                    <td class="text-end {{ $mutation->quantity_on_hand_change > 0 ? 'text-success' : 'text-danger' }}">
                                                        {{ $mutation->quantity_on_hand_change > 0 ? '+' : '' }}{{ number_format((float)$mutation->quantity_on_hand_change, 0) }}
                                                    </td>
                                                    <td class="text-muted">{{ $mutation->reference_no ?: '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <x-metronic.empty-state title="Belum ada mutasi" description="Mutasi tidak dibuat saat master produk disimpan. Riwayat ini hanya ditulis oleh transaksi stok resmi agar audit tetap dapat direkonsiliasi." icon="ki-outline ki-arrow-left-right">
                                    @can('stock.view')
                                        <a href="{{ route('warehouse.stock-card.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-light-primary">Buka Kartu Stok</a>
                                    @endcan
                                </x-metronic.empty-state>
                            @endif
                        </div>
                    </div>

                    {{-- Tab: Harga --}}
                    <div class="tab-pane fade" id="pricing" role="tabpanel">
                        <div class="mb-5">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-3">
                                <div>
                                    <h6 class="text-muted fs-7 text-uppercase mb-1">Harga Jual dan Price Ring</h6>
                                    <div class="text-muted fs-7">Harga per channel, cabang, segmen, dan minimum kuantitas.</div>
                                </div>
                                @can('prices.view')
                                    <a href="{{ route('pricing.product-prices.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-light-primary">
                                        <i class="ki-outline ki-price-tag fs-5 me-1"></i>Kelola Harga
                                    </a>
                                @endcan
                            </div>
                            @if($product->prices->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle fs-7">
                                        <thead>
                                            <tr class="text-muted fw-bold">
                                                <th>Price Ring</th>
                                                <th>Channel</th>
                                                <th class="text-end">Min</th>
                                                <th class="text-end">Recommended</th>
                                                <th class="text-end">Max</th>
                                                <th class="text-end">Min Qty</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($product->prices as $price)
                                                <tr>
                                                    <td class="fw-semibold">{{ ucfirst($price->price_ring) }}</td>
                                                    <td><span class="badge bg-light">{{ ucfirst($price->channel) }}</span></td>
                                                    <td class="text-end text-muted">{{ $price->min_price > 0 ? 'Rp ' . number_format((float)$price->min_price, 0) : '-' }}</td>
                                                    <td class="text-end text-success fw-bold">Rp {{ number_format((float)$price->recommended_price, 0) }}</td>
                                                    <td class="text-end text-muted">{{ $price->max_price > 0 ? 'Rp ' . number_format((float)$price->max_price, 0) : '-' }}</td>
                                                    <td class="text-end">{{ number_format((float)$price->minimum_qty, 0) }}</td>
                                                    <td>
                                                        <x-metronic.status-badge :status="$price->status?->value ?? 'active'" :label="strtoupper($price->status?->value ?? 'active')" />
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <x-metronic.empty-state title="Harga jual belum dibuat" description="HPP dan harga minimum sudah tersimpan pada master. Tambahkan harga jual melalui modul Harga agar price ring, channel, histori, dan approval tercatat." icon="ki-outline ki-price-tag">
                                    @can('prices.view')
                                        <a href="{{ route('pricing.product-prices.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-light-primary">Tambah Harga Jual</a>
                                    @endcan
                                </x-metronic.empty-state>
                            @endif
                        </div>

                        <div>
                            <h6 class="text-muted fs-7 text-uppercase mb-3">Harga Pokok (HPP)</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <div class="text-muted fs-7 mb-1">HPP Saat Ini</div>
                                        <div class="fw-bold fs-4 text-warning">Rp {{ number_format((float)$product->cost_price, 0) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <div class="text-muted fs-7 mb-1">Min. Harga Jual</div>
                                        <div class="fw-bold fs-4 text-danger">Rp {{ number_format((float)$product->minimum_price, 0) }}</div>
                                    </div>
                                </div>
                                @if($product->cost_price > 0 && $product->minimum_price > 0)
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <div class="text-muted fs-7 mb-1">Margin Minimum</div>
                                        <div class="fw-bold fs-4 text-success">
                                            {{ number_format((($product->minimum_price - $product->cost_price) / $product->cost_price) * 100, 1) }}%
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Tab: Supplier --}}
                    <div class="tab-pane fade" id="supplier" role="tabpanel">
                        @if($product->supplierProducts->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle fs-7">
                                    <thead>
                                        <tr class="text-muted fw-bold">
                                            <th>Supplier</th>
                                            <th class="text-end">Last Price</th>
                                            <th>Last Supplied</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($product->supplierProducts as $sp)
                                            <tr>
                                                <td class="fw-semibold">{{ $sp->supplier?->name ?: '-' }}</td>
                                                <td class="text-end">Rp {{ number_format((float)$sp->last_price, 0) }}</td>
                                                <td>{{ $sp->last_supplied_at?->format('d/m/Y') ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <x-metronic.empty-state title="Belum ada supplier" description="Produk ini belum memiliki relasi supplier." />
                        @endif
                    </div>

                    {{-- Tab: Audit --}}
                    <div class="tab-pane fade" id="audit" role="tabpanel">
                        @include('admin.products.partials.audit-timeline', ['activities' => $activities, 'auditRelations' => $auditRelations])
                    </div>
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
