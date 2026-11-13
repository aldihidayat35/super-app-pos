@extends('layouts.metronic.app')

@section('title', 'Saldo Stok - ' . config('app.name'))
@section('page_title', 'Saldo Stok per Lokasi')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stocks" title="Panduan Halaman Saldo Stok per Lokasi">
        <x-slot:function>
            <p>Halaman ini menampilkan saldo terkini setiap produk pada gudang, cabang, zona, rak, atau bin yang boleh diakses akun Anda. Gudang, Owner, dan Kepala Toko menggunakannya untuk memeriksa ketersediaan dan nilai persediaan.</p>
            <p>Saldo dibentuk oleh InventoryService dari mutasi resmi. Halaman ini bersifat baca-saja dan bukan tempat mengubah stok langsung.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Sistem membatasi saldo berdasarkan scope lokasi akun.</li><li>Filter produk, lokasi kerja, bin, dan status diterapkan.</li><li>Sistem menampilkan on hand, reserved, rusak, available, batas minimum, dan nilai HPP.</li><li>Klik <strong>Kartu Stok</strong> untuk menelusuri asal perubahan saldo.</li><li>Gunakan <strong>Export CSV</strong> untuk mengunduh hasil sesuai filter aktif.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Semua produk:</strong> memilih produk tertentu.</li><li><strong>Semua gudang/cabang:</strong> memilih lokasi kerja sesuai hak akses.</li><li><strong>Semua zona/rak/bin:</strong> mempersempit lokasi fisik.</li><li><strong>Status Kritis/Kosong:</strong> menemukan stok yang perlu ditindaklanjuti.</li><li><strong>On Hand:</strong> jumlah fisik yang tercatat.</li><li><strong>Reserved:</strong> jumlah yang sudah dialokasikan.</li><li><strong>Rusak:</strong> jumlah yang tidak tersedia untuk penggunaan normal.</li><li><strong>Available:</strong> on hand dikurangi reserved dan rusak.</li><li><strong>Min/Safety:</strong> batas pengendalian stok pada master produk.</li><li><strong>Nilai HPP:</strong> nilai persediaan pada baris saldo.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Filter dan export tidak mengubah data. Saldo berubah hanya melalui proses resmi seperti receipt, issue, reservation, transfer, retur, kerusakan, atau penyesuaian opname. Link Kartu Stok membawa filter produk dan lokasi agar saldo dapat direkonsiliasi.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pilih produk atau lokasi yang akan diperiksa.</li><li>Pilih status Kritis atau Kosong bila sedang merencanakan restock.</li><li>Klik <strong>Filter</strong>.</li><li>Periksa Available, bukan hanya On Hand, sebelum menjanjikan atau memindahkan barang.</li><li>Klik <strong>Kartu Stok</strong> jika angka perlu ditelusuri.</li><li>Klik <strong>Export CSV</strong> setelah memastikan filter sudah benar.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Reserved dan rusak mengurangi stok yang benar-benar tersedia.</li><li>Jangan mengoreksi selisih dengan mengubah database atau master produk.</li><li>Saldo pada bin berbeda merupakan baris berbeda walaupun produknya sama.</li><li>Export mengikuti scope akun dan filter saat ini.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Produk Kopi memiliki on hand 50, reserved 8, dan rusak 2 pada Bin A-01. Available yang dapat dipakai adalah 40 unit. Klik Kartu Stok untuk melihat transaksi pembentuk angka tersebut.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    @can('opening_stocks.import')
        <a href="{{ route('warehouse.opening-stocks.import.index') }}" class="btn btn-primary"><i class="ki-outline ki-file-up me-1"></i> Import Stok</a>
    @endcan
    <a href="{{ route('warehouse.stock-card.index') }}" class="btn btn-light"><i class="ki-outline ki-document"></i> Kartu Stok</a>
    <a href="{{ route('warehouse.batches.index') }}" class="btn btn-light"><i class="ki-outline ki-barcode"></i> Batch & Exp</a>
    @can('goods_receipts.view')
        <a href="{{ route('pricing.hpp-history.index') }}" class="btn btn-light"><i class="ki-outline ki-chart-line-up"></i> Histori HPP</a>
    @endcan
    <a href="{{ route('warehouse.stocks.export', request()->query()) }}" class="btn btn-light-primary"><i class="ki-outline ki-file-down"></i> Export CSV</a>

    @if(auth()->user()?->hasRole('super_admin'))
        <form method="POST" action="{{ route('warehouse.stocks.reset-all') }}" id="form-reset-stocks" class="d-inline" data-no-loading="true">
            @csrf
            <button type="button" class="btn btn-light-danger" id="btn-reset-stocks" title="Khusus Super Admin: Reset seluruh saldo stok untuk uji coba">
                <i class="ki-outline ki-arrows-circle fs-4 me-1"></i> Reset Stok (Uji Coba)
            </button>
        </form>
    @endif
@endsection

@section('content')
    <x-warehouse.active-location-card />

    {{-- Grid 5 Menu Aksi Saldo Stok --}}
    <div class="row row-cols-2 row-cols-sm-3 row-cols-xl-5 g-4 mb-6">
        <div class="col">
            <a href="{{ route('warehouse.opening-stocks.import.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-primary me-3 flex-shrink-0">
                        <span class="symbol-label"><i class="ki-outline ki-file-up fs-2 text-primary"></i></span>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fs-7 fw-bold text-gray-900 text-truncate">Impor Produk</div>
                        <div class="text-muted fs-8 text-truncate">Saldo awal stok</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('warehouse.stock-transfers.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-info me-3 flex-shrink-0">
                        <span class="symbol-label"><i class="ki-outline ki-arrow-right-left fs-2 text-info"></i></span>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fs-7 fw-bold text-gray-900 text-truncate">Transfer Cabang</div>
                        <div class="text-muted fs-8 text-truncate">Mutasi antar lokasi</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('warehouse.location-transfers.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-success me-3 flex-shrink-0">
                        <span class="symbol-label"><i class="ki-outline ki-element-plus fs-2 text-success"></i></span>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fs-7 fw-bold text-gray-900 text-truncate">Pindah Rak Internal</div>
                        <div class="text-muted fs-8 text-truncate">Atur rak &amp; bin</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('warehouse.losses.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-danger me-3 flex-shrink-0">
                        <span class="symbol-label"><i class="ki-outline ki-cross-circle fs-2 text-danger"></i></span>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fs-7 fw-bold text-gray-900 text-truncate">Barang Loss/Rusak</div>
                        <div class="text-muted fs-8 text-truncate">Catat barang rusak</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('warehouse.stock-opnames.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 text-decoration-none bg-body shadow-xs">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-warning me-3 flex-shrink-0">
                        <span class="symbol-label"><i class="ki-outline ki-clipboard fs-2 text-warning"></i></span>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fs-7 fw-bold text-gray-900 text-truncate">Stok Opname</div>
                        <div class="text-muted fs-8 text-truncate">Penghitungan fisik</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-3"><select name="product_id" class="form-select form-select-solid"><option value="">Semua produk</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="work_location_id" class="form-select form-select-solid"><option value="">Semua gudang/cabang</option>@foreach ($workLocations as $location)<option value="{{ $location->id }}" @selected(($filters['work_location_id'] ?? '') == $location->id)>{{ $location->typeLabel() }} — {{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="warehouse_location_id" class="form-select form-select-solid"><option value="">Semua zona/rak/bin</option>@foreach ($warehouseLocations as $location)<option value="{{ $location->id }}" @selected(($filters['warehouse_location_id'] ?? '') == $location->id)>{{ $location->full_code }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option><option value="critical" @selected(($filters['status'] ?? '') === 'critical')>Kritis</option><option value="empty" @selected(($filters['status'] ?? '') === 'empty')>Kosong</option></select></div>
            <div class="col-md-1"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th>Produk</th>
                        <th>Lokasi</th>
                        <th>On Hand</th>
                        <th>Reserved</th>
                        <th>Rusak</th>
                        <th>Available</th>
                        <th>Min/Safety</th>
                        <th>
                            <span class="d-inline-flex align-items-center gap-1">
                                Nilai HPP
                                <button
                                    type="button"
                                    class="btn btn-icon btn-sm h-20px w-20px p-0 text-gray-500 text-hover-primary"
                                    data-bs-toggle="tooltip"
                                    data-bs-trigger="hover focus click"
                                    data-bs-placement="top"
                                    title="Nilai HPP adalah total nilai modal persediaan pada baris lokasi ini, bukan harga jual dan bukan HPP per unit. Secara umum nilainya berasal dari jumlah stok fisik dikali HPP per unit produk. Nilai Rp0 dapat berarti HPP produk masih nol atau nilai persediaan belum disinkronkan oleh proses stok."
                                    aria-label="Penjelasan Nilai HPP"
                                >
                                    <i class="ki-outline ki-information-5 fs-7"></i>
                                </button>
                            </span>
                        </th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($stocks as $stock)
                    <tr>
                        <td>
                            @if($stock->product)
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-35px me-3">
                                        <x-metronic.image-preview url="{{ $stock->product->main_image_url }}" title="{{ $stock->product->name }}" />
                                    </div>
                                    <div>
                                        <div class="fw-bold text-gray-800" data-mobile-primary>{{ $stock->product->name }}</div>
                                        <div class="text-muted fs-7" data-mobile-secondary>{{ $stock->product->sku }}</div>
                                    </div>
                                </div>
                            @else
                                -
                            @endif
                        </td>
                        <td><span data-mobile-subtitle>{{ $stock->workLocation?->name }}</span><div class="text-muted">{{ $stock->warehouseLocation?->full_code ?: 'Tanpa bin' }}</div></td>
                        <td>{{ qty($stock->quantity_on_hand) }}</td>
                        <td>{{ qty($stock->quantity_reserved) }}</td>
                        <td>{{ qty($stock->quantity_damaged) }}</td>
                        <td class="fw-bold"><span data-mobile-highlight data-mobile-highlight-label="Available">{{ qty($stock->available_quantity) }}</span></td>
                        <td>{{ qty($stock->product?->minimum_stock) }} / {{ qty($stock->product?->safety_stock) }}</td>
                        <td>{{ \App\Support\CurrencyFormatter::rupiah($stock->inventory_value) }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('warehouse.stock-card.index', ['product_id' => $stock->product_id, 'work_location_id' => $stock->work_location_id, 'warehouse_location_id' => $stock->warehouse_location_id]) }}">Kartu Stok</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada saldo stok" description="Saldo akan tercipta otomatis saat InventoryService menerima mutasi pertama." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $stocks->links() }}
    </x-metronic.card>

@if(auth()->user()?->hasRole('super_admin'))
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const btnResetStocks = document.getElementById('btn-reset-stocks');
        const formResetStocks = document.getElementById('form-reset-stocks');

        if (btnResetStocks && formResetStocks) {
            btnResetStocks.addEventListener('click', function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Reset Semua Saldo Stok?',
                        html: '<div class="text-start fs-7 text-gray-700">Tindakan ini akan <strong>mengosongkan seluruh saldo stok fisik (on-hand, reserved, damaged), batch, dan histori mutasi</strong> di semua gudang/cabang.<br><br>Master produk tetap ada dengan saldo 0.<br><br><span class="text-danger fw-bold"><i class="ki-outline ki-information-2 text-danger"></i> Fitur ini khusus mode uji coba untuk memudahkan pembersihan data tes saldo stok.</span></div>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<i class="ki-outline ki-arrows-circle fs-4 me-1"></i> Ya, Reset Semua Stok',
                        cancelButtonText: 'Batal',
                        customClass: {
                            confirmButton: 'btn btn-danger fw-bold',
                            cancelButton: 'btn btn-light fw-bold'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            btnResetStocks.disabled = true;
                            btnResetStocks.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Mereset Stok...';
                            formResetStocks.submit();
                        }
                    });
                } else {
                    if (confirm('PERINGATAN: Apakah Anda yakin ingin mereset seluruh saldo stok dan mutasi persediaan untuk keperluan uji coba?')) {
                        btnResetStocks.disabled = true;
                        formResetStocks.submit();
                    }
                }
            });
        }
    });
    </script>
    @endpush
@endif
@endsection
