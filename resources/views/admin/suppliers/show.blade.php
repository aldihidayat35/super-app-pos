@extends('layouts.metronic.app')
@section('title', $supplier->name . ' - Detail Supplier')
@section('page_title', 'Detail Supplier')

@section('toolbar_actions')
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-light">
        <i class="ki-outline ki-arrow-left fs-5 me-2"></i>Kembali
    </a>
    @can('update', $supplier)
        <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-primary">
            <i class="ki-outline ki-pencil fs-5 me-2"></i>Edit Supplier
        </a>
    @endcan
@endsection

@section('page_guide')
    <x-metronic.page-guide id="admin-supplier-show" title="Panduan Detail Supplier">
        <x-slot:function><p>Halaman berfungsi sebagai pusat informasi Supplier (Supplier Dashboard) yang menampilkan profil, kontak, produk yang disupply, histori PO, penerimaan barang, dan audit aktivitas dalam satu tampilan terpadu.</p></x-slot:function>
        <x-slot:parts>
            <ul>
                <li><strong>Header Card:</strong> nama, kode, skor performa, status, dan ringkasan statistik.</li>
                <li><strong>Info Grid:</strong> PIC, kontak, alamat, termin pembayaran, NPWP, dan info bank.</li>
                <li><strong>Tab Kontak:</strong> daftar kontak PIC supplier dengan posisi, nomor telepon/WA, dan email.</li>
                <li><strong>Tab Produk:</strong> daftar produk yang disupply dan harga terakhir pembelian.</li>
                <li><strong>Tab PO:</strong> histori Purchase Order ke supplier ini.</li>
                <li><strong>Tab Penerimaan:</strong> histori Goods Receipt dari supplier.</li>
                <li><strong>Tab Audit:</strong> aktivitas perubahan data supplier.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts><p>Setiap tab memiliki tombol aksi yang membawa Supplier ID sehingga pengguna tidak perlu memilih supplier lagi saat membuat dokumen baru.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa header dan ringkasan statistik supplier.</li><li>Periksa info kontak dan alamat di tab Info.</li><li>Pilih tab sesuai data yang ingin dilihat (Kontak, Produk, PO, dll).</li><li>Gunakan tombol aksi pada tiap tab untuk menambah atau melihat data lengkap.</li></ol></x-slot:operation>
    </x-metronic.page-guide>
@endsection

@section('content')
    @php
        $supplierProductsCount = $supplier->productsSupplied->count();
        $totalPo = $supplier->purchaseOrders->count();
        $totalReceipts = $supplier->goodsReceipts->count();
        $totalPOValue = $supplier->purchaseOrders->sum(fn($po) => (float) $po->grand_total);
        $performanceScore = (float) $supplier->performance_score ?? 0;
        $performanceClass = $performanceScore >= 80 ? 'success' : ($performanceScore >= 60 ? 'warning' : 'danger');
        $statusBadge = $supplier->is_active ? 'success' : 'danger';
    @endphp

    {{-- Header Card --}}
    <x-metronic.card class="mb-6">
        <div class="d-flex flex-wrap gap-4 align-items-center mb-5">
            <div class="symbol symbol-100px symbol-circle bg-gradient-primary text-white flex-shrink-0">
                <span class="symbol-label fs-2 fw-bold">{{ mb_substr($supplier->name, 0, 1) }}</span>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                    <h4 class="fw-bold text-gray-900 mb-0">{{ $supplier->name }}</h4>
                    <span class="badge badge-{{ $statusBadge }} fs-7 fw-normal">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    <span class="badge badge-light fs-7 font-monospace">{{ $supplier->code }}</span>
                </div>
                <div class="text-muted fs-7">
                    <i class="ki-outline ki-calendar fs-5 me-1"></i>
                    Didaftarkan {{ $supplier->created_at?->format('d M Y') }}
                    @if($supplier->updated_at && $supplier->updated_at != $supplier->created_at)
                        · Diperbarui {{ $supplier->updated_at->diffForHumans() }}
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('purchasing.purchase-orders.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-primary">
                    <i class="ki-outline ki-plus fs-5 me-2"></i>Buat PO Baru
                </a>
                @can('update', $supplier)
                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-light">
                        <i class="ki-outline ki-pencil fs-5 me-2"></i>Edit
                    </a>
                @endcan
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body text-center">
                        <div class="symbol symbol-50px symbol-circle bg-{{ $performanceClass }}-simple text-{{ $performanceClass }} mb-3 mx-auto">
                            <i class="ki-outline ki-star fs-2x"></i>
                        </div>
                        <div class="fs-2 fw-bold text-{{ $performanceClass }}">{{ number_format($performanceScore, 1) }}</div>
                        <div class="text-muted fs-7">Skor Performa</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body text-center">
                        <div class="symbol symbol-50px symbol-circle bg-primary-simple text-primary mb-3 mx-auto">
                            <i class="ki-outline ki-package fs-2x"></i>
                        </div>
                        <div class="fs-2 fw-bold text-primary">{{ $supplierProductsCount }}</div>
                        <div class="text-muted fs-7">Produk Disupply</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body text-center">
                        <div class="symbol symbol-50px symbol-circle bg-success-simple text-success mb-3 mx-auto">
                            <i class="ki-outline ki-document fs-2x"></i>
                        </div>
                        <div class="fs-2 fw-bold text-success">{{ $totalPo }}</div>
                        <div class="text-muted fs-7">Total PO</div>
                        <div class="text-muted fs-7">Rp {{ number_format($totalPOValue, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-light h-100">
                    <div class="card-body text-center">
                        <div class="symbol symbol-50px symbol-circle bg-warning-simple text-warning mb-3 mx-auto">
                            <i class="ki-outline ki-truck fs-2x"></i>
                        </div>
                        <div class="fs-2 fw-bold text-warning">{{ $totalReceipts }}</div>
                        <div class="text-muted fs-7">Penerimaan</div>
                    </div>
                </div>
            </div>
        </div>
    </x-metronic.card>

    <div class="row g-6">
        {{-- Kolom Kiri: Info Grid --}}
        <div class="col-lg-4">
            {{-- Kontak & Informasi --}}
            <x-metronic.card title="Informasi Kontak">
                <div class="d-flex flex-column gap-4">
                    @if($supplier->contact_name)
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-primary text-primary flex-shrink-0">
                            <i class="ki-outline ki-user fs-2x"></i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">PIC / Kontak Utama</div>
                            <div class="fw-semibold">{{ $supplier->contact_name }}</div>
                        </div>
                    </div>
                    @endif

                    @if($supplier->whatsapp_number)
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-success text-success flex-shrink-0">
                            <i class="ki-outline ki-whatsapp fs-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="text-muted fs-7 mb-1">WhatsApp</div>
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $supplier->whatsapp_number) }}" target="_blank" class="text-success fw-semibold text-hover-primary">
                                {{ $supplier->whatsapp_number }}
                            </a>
                        </div>
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $supplier->whatsapp_number) }}" target="_blank" class="btn btn-sm btn-success" title="Chat via WhatsApp">
                            <i class="ki-outline ki-message fs-5"></i>
                        </a>
                    </div>
                    @endif

                    @if($supplier->phone_number)
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-info text-info flex-shrink-0">
                            <i class="ki-outline ki-phone fs-2x"></i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Telepon</div>
                            <div class="fw-semibold">{{ $supplier->phone_number }}</div>
                        </div>
                    </div>
                    @endif

                    @if($supplier->email)
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-warning text-warning flex-shrink-0">
                            <i class="ki-outline ki-sms fs-2x"></i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Email</div>
                            <a href="mailto:{{ $supplier->email }}" class="text-primary fw-semibold text-hover-primary">{{ $supplier->email }}</a>
                        </div>
                    </div>
                    @endif
                </div>

                <div class="separator separator-dashed my-4"></div>

                <div class="d-flex flex-column gap-4">
                    @if($supplier->address)
                    <div class="d-flex align-items-start gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-primary text-primary flex-shrink-0">
                            <i class="ki-outline ki-location fs-2x"></i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Alamat</div>
                            <div class="fw-semibold">{{ $supplier->address }}</div>
                            @if($supplier->city)
                            <div class="text-muted fs-7">{{ $supplier->city }}</div>
                            @endif
                        </div>
                    </div>
                    @endif

                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-success text-success flex-shrink-0">
                            <i class="ki-outline ki-calendar fs-2x"></i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Termin Pembayaran</div>
                            <div class="fw-semibold">{{ $supplier->payment_term_days }} hari</div>
                        </div>
                    </div>

                    @if($supplier->tax_number)
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-info text-info flex-shrink-0">
                            <i class="ki-outline ki-12 fs-2x"></i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">NPWP</div>
                            <div class="fw-semibold font-monospace">{{ $supplier->tax_number }}</div>
                        </div>
                    </div>
                    @endif
                </div>
            </x-metronic.card>

            {{-- Informasi Bank --}}
            @if($supplier->bank_name || $supplier->bank_account_number)
            <x-metronic.card title="Informasi Bank" class="mt-6">
                <div class="d-flex flex-column gap-4">
                    @if($supplier->bank_name)
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-primary text-primary flex-shrink-0">
                            <i class="ki-outline ki-credit-card fs-2x"></i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Nama Bank</div>
                            <div class="fw-semibold">{{ $supplier->bank_name }}</div>
                        </div>
                    </div>
                    @endif
                    @if($supplier->bank_account_name)
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-success text-success flex-shrink-0">
                            <i class="ki-outline ki-user fs-2x"></i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Atas Nama</div>
                            <div class="fw-semibold">{{ $supplier->bank_account_name }}</div>
                        </div>
                    </div>
                    @endif
                    @if($supplier->bank_account_number)
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-40px symbol-circle bg-light-warning text-warning flex-shrink-0">
                            <i class="ki-outline ki-document fs-2x"></i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">No. Rekening</div>
                            <div class="fw-semibold font-monospace">{{ $supplier->bank_account_number }}</div>
                        </div>
                    </div>
                    @endif
                </div>
            </x-metronic.card>
            @endif

            {{-- Catatan --}}
            @if($supplier->notes)
            <x-metronic.card title="Catatan" class="mt-6">
                <div class="text-muted">{{ $supplier->notes }}</div>
            </x-metronic.card>
            @endif

            {{-- Aksi --}}
            <x-metronic.card title="Aksi Cepat" class="mt-6">
                <div class="d-grid gap-3">
                    <a href="{{ route('purchasing.purchase-orders.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-primary">
                        <i class="ki-outline ki-plus fs-5 me-2"></i>Buat Purchase Order
                    </a>
                    @can('goods_receipts.create')
                        <a href="{{ route('warehouse.goods-receipts.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-light">
                            <i class="ki-outline ki-truck fs-5 me-2"></i>Catat Penerimaan
                        </a>
                    @endcan
                    @can('update', $supplier)
                        <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-light">
                            <i class="ki-outline ki-pencil fs-5 me-2"></i>Edit Supplier
                        </a>
                    @endcan
                    @if($supplier->is_active)
                        @can('update', $supplier)
                            <form method="POST" action="{{ route('admin.suppliers.deactivate', $supplier) }}" class="d-inline w-100" onsubmit="return confirm('Yakin ingin menonaktifkan supplier ini?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-light-danger w-100">
                                    <i class="ki-outline ki-cross fs-5 me-2"></i>Nonaktifkan Supplier
                                </button>
                            </form>
                        @endcan
                    @endif
                </div>
            </x-metronic.card>
        </div>

        {{-- Kolom Kanan: Tab --}}
        <div class="col-lg-8">
            <x-metronic.card class="h-100">
                <ul class="nav nav-tabs nav-line-tabs mb-5" id="supplierTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                            <i class="ki-outline ki-user fs-5 me-2"></i>Kontak
                            <span class="badge badge-circle badge-secondary ms-2">{{ $supplier->contacts->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                            <i class="ki-outline ki-package fs-5 me-2"></i>Produk
                            <span class="badge badge-circle badge-secondary ms-2">{{ $supplierProductsCount }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="po-tab" data-bs-toggle="tab" data-bs-target="#po" type="button" role="tab">
                            <i class="ki-outline ki-document fs-5 me-2"></i>PO
                            <span class="badge badge-circle badge-secondary ms-2">{{ $totalPo }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="receipts-tab" data-bs-toggle="tab" data-bs-target="#receipts" type="button" role="tab">
                            <i class="ki-outline ki-truck fs-5 me-2"></i>Penerimaan
                            <span class="badge badge-circle badge-secondary ms-2">{{ $totalReceipts }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="audit-tab" data-bs-toggle="tab" data-bs-target="#audit" type="button" role="tab">
                            <i class="ki-outline ki-time fs-5 me-2"></i>Audit
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="supplierTabContent">
                    {{-- Tab: Kontak --}}
                    <div class="tab-pane fade show active" id="contact" role="tabpanel">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-5">
                            <div>
                                <h6 class="text-gray-900 mb-1">Daftar Kontak Supplier</h6>
                                <div class="text-muted fs-7">PIC, nomor telepon, dan email untuk menghubungi supplier.</div>
                            </div>
                            @can('update', $supplier)
                                <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-sm btn-primary">
                                    <i class="ki-outline ki-plus fs-5 me-1"></i>Tambah Kontak
                                </a>
                            @endcan
                        </div>
                        @if($supplier->contacts->isNotEmpty())
                            <div class="row g-4">
                                @foreach($supplier->contacts as $contact)
                                    <div class="col-md-6">
                                        <div class="card border {{ $contact->is_primary ? 'border-primary border-2' : 'border-gray-300' }} shadow-sm h-100">
                                            <div class="card-body p-5">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="symbol symbol-50px symbol-circle bg-light-primary text-primary me-3">
                                                            <span class="symbol-label fw-bold">{{ mb_substr($contact->name, 0, 1) }}</span>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold fs-6 text-gray-900">{{ $contact->name }}</div>
                                                            <div class="text-muted fs-7">{{ $contact->position ?: 'PIC' }}</div>
                                                        </div>
                                                    </div>
                                                    @if($contact->is_primary)
                                                        <span class="badge badge-light-primary">Kontak Utama</span>
                                                    @endif
                                                </div>
                                                <div class="separator separator-dashed my-3"></div>
                                                <div class="d-flex flex-column gap-2">
                                                    @if($contact->phone_number)
                                                        <div class="d-flex align-items-center fs-7">
                                                            <i class="ki-outline ki-phone fs-5 text-muted me-2"></i>
                                                            <span>{{ $contact->phone_number }}</span>
                                                        </div>
                                                    @endif
                                                    @if($contact->whatsapp_number)
                                                        <div class="d-flex align-items-center fs-7">
                                                            <i class="ki-outline ki-whatsapp fs-5 text-success me-2"></i>
                                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $contact->whatsapp_number) }}" target="_blank" class="text-success">{{ $contact->whatsapp_number }}</a>
                                                        </div>
                                                    @endif
                                                    @if($contact->email)
                                                        <div class="d-flex align-items-center fs-7">
                                                            <i class="ki-outline ki-sms fs-5 text-muted me-2"></i>
                                                            <a href="mailto:{{ $contact->email }}" class="text-primary">{{ $contact->email }}</a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <x-metronic.empty-state title="Belum ada kontak tambahan" description="Kontak utama supplier ada di ringkasan profil. Tambah kontak tambahan melalui menu edit supplier." icon="ki-outline ki-user">
                                @can('update', $supplier)
                                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-sm btn-light-primary">
                                        <i class="ki-outline ki-pencil fs-5 me-1"></i>Edit Supplier
                                    </a>
                                @endcan
                            </x-metronic.empty-state>
                        @endif
                    </div>

                    {{-- Tab: Produk --}}
                    <div class="tab-pane fade" id="products" role="tabpanel">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-5">
                            <div>
                                <h6 class="text-gray-900 mb-1">Daftar Produk yang Disupply</h6>
                                <div class="text-muted fs-7">Produk yang pernah dibeli dari supplier ini beserta harga terakhir.</div>
                            </div>
                            <a href="{{ route('admin.products.index', ['supplier_id' => $supplier->id]) }}" class="btn btn-sm btn-light-primary">
                                <i class="ki-outline ki-eye fs-5 me-1"></i>Lihat Semua Produk
                            </a>
                        </div>
                        @if($supplier->productsSupplied->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle fs-7">
                                    <thead>
                                        <tr class="text-muted fw-bold">
                                            <th>Produk</th>
                                            <th>Kategori</th>
                                            <th>SKU</th>
                                            <th class="text-end">Harga Terakhir</th>
                                            <th>Terakhir Supply</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($supplier->productsSupplied as $item)
                                            <tr>
                                                <td class="fw-semibold">
                                                    @if($item->product)
                                                        <a href="{{ route('admin.products.show', $item->product) }}" class="text-gray-900 text-hover-primary">{{ $item->product->name }}</a>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>{{ $item->product?->category?->name ?: '-' }}</td>
                                                <td class="font-monospace">{{ $item->product?->sku ?: '-' }}</td>
                                                <td class="text-end fw-semibold">{{ App\Support\CurrencyFormatter::rupiah($item->last_price) }}</td>
                                                <td>{{ $item->last_supplied_at?->format('d/m/Y') ?: '-' }}</td>
                                                <td class="text-end">
                                                    @if($item->product)
                                                        <a href="{{ route('admin.products.show', $item->product) }}" class="btn btn-sm btn-icon btn-light" title="Lihat Produk">
                                                            <i class="ki-outline ki-eye fs-5"></i>
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <x-metronic.empty-state title="Belum ada produk supplier" description="Relasi produk supplier akan terisi otomatis dari pembelian/penerimaan barang." icon="ki-outline ki-package" />
                        @endif
                    </div>

                    {{-- Tab: Purchase Order --}}
                    <div class="tab-pane fade" id="po" role="tabpanel">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-5">
                            <div>
                                <h6 class="text-gray-900 mb-1">Histori Purchase Order</h6>
                                <div class="text-muted fs-7">Daftar PO yang pernah diterbitkan ke supplier ini.</div>
                            </div>
                            @can('purchase_orders.create')
                                <a href="{{ route('purchasing.purchase-orders.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-sm btn-primary">
                                    <i class="ki-outline ki-plus fs-5 me-1"></i>Buat PO untuk Supplier Ini
                                </a>
                            @endcan
                        </div>
                        @if($supplier->purchaseOrders->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle fs-7">
                                    <thead>
                                        <tr class="text-muted fw-bold">
                                            <th>Nomor PO</th>
                                            <th>Tanggal</th>
                                            <th>Gudang</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($supplier->purchaseOrders as $po)
                                            <tr>
                                                <td class="fw-semibold font-monospace">{{ $po->number }}</td>
                                                <td>{{ $po->order_date?->format('d/m/Y') ?: '-' }}</td>
                                                <td>{{ $po->warehouse?->name ?: '-' }}</td>
                                                <td class="text-end fw-semibold">Rp {{ number_format((float) $po->grand_total, 0, ',', '.') }}</td>
                                                <td>
                                                    <x-metronic.status-badge :status="$po->status?->value ?? 'draft'" :label="$po->status?->label() ?? ucfirst((string)$po->status)" />
                                                </td>
                                                <td class="text-end">
                                                    @can('purchase_orders.view')
                                                        <a href="{{ route('purchasing.purchase-orders.show', $po) }}" class="btn btn-sm btn-icon btn-light" title="Lihat Detail">
                                                            <i class="ki-outline ki-eye fs-5"></i>
                                                        </a>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <x-metronic.empty-state title="Belum ada Purchase Order" description="PO akan tampil di sini setelah Anda membuat Purchase Order untuk supplier ini." icon="ki-outline ki-document">
                                @can('purchase_orders.create')
                                    <a href="{{ route('purchasing.purchase-orders.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-sm btn-light-primary">
                                        <i class="ki-outline ki-plus fs-5 me-1"></i>Buat PO untuk Supplier Ini
                                    </a>
                                @endcan
                            </x-metronic.empty-state>
                        @endif
                    </div>

                    {{-- Tab: Penerimaan --}}
                    <div class="tab-pane fade" id="receipts" role="tabpanel">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-5">
                            <div>
                                <h6 class="text-gray-900 mb-1">Histori Penerimaan Barang</h6>
                                <div class="text-muted fs-7">Daftar Goods Receipt (GR) dari supplier ini yang sudah diposting.</div>
                            </div>
                            @can('goods_receipts.create')
                                <a href="{{ route('warehouse.goods-receipts.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-sm btn-primary">
                                    <i class="ki-outline ki-plus fs-5 me-1"></i>Catat Penerimaan
                                </a>
                            @endcan
                        </div>
                        @if($supplier->goodsReceipts->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle fs-7">
                                    <thead>
                                        <tr class="text-muted fw-bold">
                                            <th>Nomor GR</th>
                                            <th>Tanggal</th>
                                            <th>Gudang</th>
                                            <th>No. Surat Jalan</th>
                                            <th>Status</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($supplier->goodsReceipts as $gr)
                                            <tr>
                                                <td class="fw-semibold font-monospace">{{ $gr->number }}</td>
                                                <td>{{ $gr->received_at?->format('d/m/Y') ?: '-' }}</td>
                                                <td>{{ $gr->warehouse?->name ?: '-' }}</td>
                                                <td>{{ $gr->delivery_note_number ?: '-' }}</td>
                                                <td>
                                                    <x-metronic.status-badge :status="$gr->status?->value ?? 'draft'" :label="$gr->status?->label() ?? ucfirst((string)$gr->status)" />
                                                </td>
                                                <td class="text-end">
                                                    @can('goods_receipts.view')
                                                        <a href="{{ route('warehouse.goods-receipts.show', $gr) }}" class="btn btn-sm btn-icon btn-light" title="Lihat Detail">
                                                            <i class="ki-outline ki-eye fs-5"></i>
                                                        </a>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        @if($supplier->goodsReceipts->count() === 0)
                            <x-metronic.empty-state title="Belum ada penerimaan barang" description="Penerimaan barang dari supplier akan tampil di sini setelah Goods Receipt diposting." icon="ki-outline ki-truck">
                                @can('goods_receipts.create')
                                    <a href="{{ route('warehouse.goods-receipts.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-sm btn-light-primary">
                                        <i class="ki-outline ki-plus fs-5 me-1"></i>Catat Penerimaan
                                    </a>
                                @endcan
                            </x-metronic.empty-state>
                        @endif
                    </div>

                    {{-- Tab: Audit --}}
                    <div class="tab-pane fade" id="audit" role="tabpanel">
                        @php
                            $activities = \Spatie\Activitylog\Models\Activity::where('subject_type', App\Models\Supplier::class)
                                ->where('subject_id', $supplier->id)
                                ->with('causer')
                                ->orderBy('created_at', 'desc')
                                ->limit(20)
                                ->get();
                        @endphp
                        <div class="mb-5">
                            <h6 class="text-muted fs-7 text-uppercase mb-3">Riwayat Aktivitas</h6>
                            @if($activities->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle fs-7">
                                        <thead>
                                            <tr class="text-muted fw-bold">
                                                <th>Waktu</th>
                                                <th>User</th>
                                                <th>Aksi</th>
                                                <th>Perubahan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($activities as $activity)
                                                <tr>
                                                    <td>{{ $activity->created_at?->format('d/m/Y H:i') }}</td>
                                                    <td>{{ $activity->causer?->name ?: 'System' }}</td>
                                                    <td>
                                                        <span class="badge bg-light text-dark">{{ $activity->description ?: $activity->event }}</span>
                                                    </td>
                                                    <td class="text-muted">
                                                        @if($activity->properties && isset($activity->properties['old']) && isset($activity->properties['attributes']))
                                                            @foreach($activity->properties['attributes'] as $key => $value)
                                                                @if(($activity->properties['old'][$key] ?? null) !== $value)
                                                                    <div class="text-truncate" style="max-width: 280px;">{{ $key }}: {{ $activity->properties['old'][$key] ?? '-' }} → {{ $value }}</div>
                                                                @endif
                                                            @endforeach
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <x-metronic.empty-state title="Belum ada aktivitas tercatat" description="Setiap perubahan data supplier akan tercatat di sini." icon="ki-outline ki-time" />
                            @endif
                        </div>
                    </div>
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
