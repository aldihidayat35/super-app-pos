@extends('layouts.metronic.app')

@section('title', 'Relasi Bisnis - ' . config('app.name'))
@section('page_title', 'Relasi Bisnis')

@section('page_guide')
    <x-metronic.page-guide id="admin-business-relations" title="Panduan Relasi Bisnis Terpadu">
        <x-slot:function>
            <p>Halaman ini menyatukan pengelolaan seluruh mitra eksternal bisnis: Pemasok / Supplier (kolom kiri) serta Pelanggan Retail dan Mitra B2B (kolom kanan) dalam satu antarmuka yang terstruktur dan responsif.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li>Pantau ringkasan metrik mitra pemasok dan pelanggan pada header masing-masing panel.</li>
                <li>Cari dan filter data mitra berdasarkan nama, kode, atau status.</li>
                <li>Gunakan tombol aksi cepat untuk pendaftaran supplier baru atau pelanggan baru.</li>
                <li>Buka detail transaksi, histori pasokan, atau limit piutang melalui tabel data terkait.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Kolom Kiri (Supplier):</strong> Pemasok barang untuk pengadaan dan penerimaan stok.</li>
                <li><strong>Kolom Kanan (Pelanggan &amp; B2B):</strong> Konsumen umum, langganan toko, dan outlet B2B kredit.</li>
                <li><strong>Tautan Detail Penuh:</strong> Tombol 'Lihat Semua' untuk membuka tabel pencarian komprehensif.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Data supplier terintegrasi langsung pada penerimaan barang dan histori biaya produk. Data pelanggan menentukan aturan harga POS, piutang, dan akses portal B2B.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Gunakan kolom pencarian pada panel terkait.</li>
                <li>Klik Tambah untuk input mitra baru atau klik baris untuk melihat detail lengkap.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul>
                    <li>Mitra yang telah memiliki histori transaksi tidak dapat dihapus, hanya dinonaktifkan.</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Supplier PT Sumber Pangan memasok beras; Pelanggan Toko Berkah memesan via B2B dengan limit Rp 10.000.000.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    @can('suppliers.create')
        <a href="{{ route('admin.suppliers.create') }}" class="btn btn-light-primary">
            <i class="ki-outline ki-delivery-2 fs-4 me-1"></i> Tambah Supplier
        </a>
    @endcan
    @can('customers.create')
        <a href="{{ route('admin.customers.create') }}" class="btn btn-primary">
            <i class="ki-outline ki-people fs-4 me-1"></i> Tambah Pelanggan
        </a>
    @endcan
@endsection

@section('content')
    <div class="row g-6">
        {{-- KOLOM KIRI: SUPPLIER --}}
        <div class="col-12 col-xl-6">
            <div class="card card-flush h-100 shadow-xs border border-gray-200">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <div class="symbol symbol-40px symbol-circle bg-light-primary me-3">
                            <span class="symbol-label">
                                <i class="ki-outline ki-delivery-2 fs-2 text-primary"></i>
                            </span>
                        </div>
                        <div class="d-flex flex-column">
                            <h3 class="fs-4 fw-bold text-gray-900 mb-0">Manajemen Supplier</h3>
                            <span class="text-muted fs-7">Mitra pengadaan & pemasok barang</span>
                        </div>
                    </div>
                    <div class="card-toolbar d-flex gap-2">
                        <span class="badge badge-light-primary fw-bold fs-8">Total: {{ $supplierKpis['total'] }}</span>
                        <span class="badge badge-light-success fw-bold fs-8">Aktif: {{ $supplierKpis['active'] }}</span>
                    </div>
                </div>

                <div class="card-body pt-4">
                    {{-- Quick Action Panel Supplier --}}
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        @can('suppliers.create')
                            <a href="{{ route('admin.suppliers.create') }}" class="btn btn-sm btn-light-primary">
                                <i class="ki-outline ki-plus fs-6 me-1"></i> Tambah Supplier
                            </a>
                        @endcan
                        <a href="{{ route('admin.parties.import.index', 'suppliers') }}" class="btn btn-sm btn-light">
                            <i class="ki-outline ki-file-up fs-6 me-1"></i> Import
                        </a>
                        <a href="{{ route('admin.suppliers.index') }}" class="btn btn-sm btn-light-info ms-auto">
                            Lihat Semua <i class="ki-outline ki-arrow-right fs-7 ms-1"></i>
                        </a>
                    </div>

                    {{-- Filter Supplier --}}
                    <form method="GET" action="{{ route('admin.business-relations.index') }}" class="d-flex flex-wrap gap-2 mb-4">
                        <input type="hidden" name="customer_q" value="{{ $customerQ }}">
                        <input type="hidden" name="customer_type" value="{{ $customerType }}">
                        <div class="flex-grow-1 min-w-150px">
                            <input name="supplier_q" value="{{ $supplierQ }}" class="form-control form-control-sm form-control-solid" placeholder="Cari kode/nama supplier...">
                        </div>
                        <div class="w-130px">
                            <select name="supplier_status" class="form-select form-select-sm form-select-solid">
                                <option value="">Semua Status</option>
                                <option value="active" @selected($supplierStatus === 'active')>Aktif</option>
                                <option value="inactive" @selected($supplierStatus === 'inactive')>Nonaktif</option>
                            </select>
                        </div>
                        <button class="btn btn-sm btn-light-primary" type="submit">
                            <i class="ki-outline ki-magnifier"></i>
                        </button>
                    </form>

                    {{-- Tabel Supplier --}}
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-hover align-middle gs-2 gy-3 mb-0">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase fs-8 border-bottom border-gray-200">
                                    <th>Supplier</th>
                                    <th>Kontak / Telepon</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($suppliers as $supplier)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.suppliers.show', $supplier) }}" class="fw-bold text-gray-900 text-hover-primary fs-7 d-block">
                                                {{ $supplier->name }}
                                            </a>
                                            <span class="text-muted fs-8 font-monospace">{{ $supplier->code }}</span>
                                        </td>
                                        <td class="fs-8 text-gray-700">
                                            <div>{{ $supplier->contact_name ?: '-' }}</div>
                                            <span class="text-muted fs-9">{{ $supplier->phone ?: '-' }}</span>
                                        </td>
                                        <td>
                                            @if ($supplier->is_active)
                                                <span class="badge badge-light-success fs-8">Aktif</span>
                                            @else
                                                <span class="badge badge-light-danger fs-8">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                @can('suppliers.view')
                                                    <a href="{{ route('admin.suppliers.show', $supplier) }}" class="btn btn-icon btn-light btn-sm" title="Detail">
                                                        <i class="ki-outline ki-eye fs-7"></i>
                                                    </a>
                                                @endcan
                                                @can('suppliers.update')
                                                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-icon btn-light-primary btn-sm" title="Edit">
                                                        <i class="ki-outline ki-pencil fs-7"></i>
                                                    </a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-6 fs-7">
                                            Tidak ada data supplier yang sesuai filter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($suppliers->hasPages())
                        <div class="mt-4">
                            {{ $suppliers->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN: PELANGGAN & B2B --}}
        <div class="col-12 col-xl-6">
            <div class="card card-flush h-100 shadow-xs border border-gray-200">
                <div class="card-header pt-6">
                    <div class="card-title">
                        <div class="symbol symbol-40px symbol-circle bg-light-info me-3">
                            <span class="symbol-label">
                                <i class="ki-outline ki-people fs-2 text-info"></i>
                            </span>
                        </div>
                        <div class="d-flex flex-column">
                            <h3 class="fs-4 fw-bold text-gray-900 mb-0">Pelanggan &amp; Mitra B2B</h3>
                            <span class="text-muted fs-7">Konsumen retail, langganan & kredit</span>
                        </div>
                    </div>
                    <div class="card-toolbar d-flex flex-wrap gap-2">
                        <span class="badge badge-light-info fw-bold fs-8">Total: {{ $customerKpis['total'] }}</span>
                        <span class="badge badge-light-primary fw-bold fs-8">B2B: {{ $customerKpis['b2b'] }}</span>
                        <span class="badge badge-light-success fw-bold fs-8">Retail: {{ $customerKpis['retail'] }}</span>
                    </div>
                </div>

                <div class="card-body pt-4">
                    {{-- Quick Action Panel Pelanggan --}}
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        @can('customers.create')
                            <a href="{{ route('admin.customers.create') }}" class="btn btn-sm btn-light-primary">
                                <i class="ki-outline ki-plus fs-6 me-1"></i> Tambah Pelanggan
                            </a>
                        @endcan
                        <a href="{{ route('admin.customers.registration-form') }}" class="btn btn-sm btn-light">
                            <i class="ki-outline ki-setting-2 fs-6 me-1"></i> Form Registrasi
                        </a>
                        <a href="{{ route('admin.parties.import.index', 'customers') }}" class="btn btn-sm btn-light">
                            <i class="ki-outline ki-file-up fs-6 me-1"></i> Import
                        </a>
                        <a href="{{ route('admin.customers.index') }}" class="btn btn-sm btn-light-info ms-auto">
                            Lihat Semua <i class="ki-outline ki-arrow-right fs-7 ms-1"></i>
                        </a>
                    </div>

                    {{-- Filter Pelanggan --}}
                    <form method="GET" action="{{ route('admin.business-relations.index') }}" class="d-flex flex-wrap gap-2 mb-4">
                        <input type="hidden" name="supplier_q" value="{{ $supplierQ }}">
                        <input type="hidden" name="supplier_status" value="{{ $supplierStatus }}">
                        <div class="flex-grow-1 min-w-150px">
                            <input name="customer_q" value="{{ $customerQ }}" class="form-control form-control-sm form-control-solid" placeholder="Cari kode/nama pelanggan...">
                        </div>
                        <div class="w-130px">
                            <select name="customer_type" class="form-select form-select-sm form-select-solid">
                                <option value="">Semua Tipe</option>
                                <option value="general" @selected($customerType === 'general')>Umum</option>
                                <option value="retail_credit" @selected($customerType === 'retail_credit')>Retail Kredit</option>
                                <option value="b2b" @selected($customerType === 'b2b')>B2B</option>
                            </select>
                        </div>
                        <button class="btn btn-sm btn-light-info" type="submit">
                            <i class="ki-outline ki-magnifier"></i>
                        </button>
                    </form>

                    {{-- Tabel Pelanggan --}}
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-hover align-middle gs-2 gy-3 mb-0">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase fs-8 border-bottom border-gray-200">
                                    <th>Pelanggan</th>
                                    <th>Tipe</th>
                                    <th>Limit / Piutang</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($customers as $customer)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.customers.show', $customer) }}" class="fw-bold text-gray-900 text-hover-primary fs-7 d-block">
                                                {{ $customer->business_name }}
                                            </a>
                                            <span class="text-muted fs-8 font-monospace">{{ $customer->code }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-secondary fs-9 fw-semibold">
                                                {{ ucfirst(str_replace('_', ' ', $customer->type->value ?? (string) $customer->type)) }}
                                            </span>
                                        </td>
                                        <td class="fs-8 text-gray-700">
                                            <div>Limit: {{ \App\Support\CurrencyFormatter::rupiah($customer->credit_limit ?? 0) }}</div>
                                            <span class="text-danger fs-9">Piutang: {{ \App\Support\CurrencyFormatter::rupiah($customer->receivable_balance ?? 0) }}</span>
                                        </td>
                                        <td>
                                            @if ($customer->is_active)
                                                <span class="badge badge-light-success fs-8">Aktif</span>
                                            @else
                                                <span class="badge badge-light-danger fs-8">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                @can('customers.view')
                                                    <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-icon btn-light btn-sm" title="Detail">
                                                        <i class="ki-outline ki-eye fs-7"></i>
                                                    </a>
                                                @endcan
                                                @can('customers.update')
                                                    <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-icon btn-light-primary btn-sm" title="Edit">
                                                        <i class="ki-outline ki-pencil fs-7"></i>
                                                    </a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-6 fs-7">
                                            Tidak ada data pelanggan yang sesuai filter.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($customers->hasPages())
                        <div class="mt-4">
                            {{ $customers->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
