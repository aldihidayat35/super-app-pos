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
    <a href="{{ route('warehouse.stocks.export', request()->query()) }}" class="btn btn-light-primary"><i class="ki-outline ki-file-down"></i> Export CSV</a>
@endsection

@section('content')
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
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi</th><th>On Hand</th><th>Reserved</th><th>Rusak</th><th>Available</th><th>Min/Safety</th><th>Nilai HPP</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse ($stocks as $stock)
                    <tr>
                        <td><span class="fw-bold">{{ $stock->product?->sku }}</span><div class="text-muted">{{ $stock->product?->name }}</div></td>
                        <td>{{ $stock->workLocation?->name }}<div class="text-muted">{{ $stock->warehouseLocation?->full_code ?: 'Tanpa bin' }}</div></td>
                        <td>{{ qty($stock->quantity_on_hand) }}</td>
                        <td>{{ qty($stock->quantity_reserved) }}</td>
                        <td>{{ qty($stock->quantity_damaged) }}</td>
                        <td class="fw-bold">{{ qty($stock->available_quantity) }}</td>
                        <td>{{ qty($stock->product?->minimum_stock) }} / {{ qty($stock->product?->safety_stock) }}</td>
                        <td>Rp {{ number_format((float) $stock->cost_value, 0, ',', '.') }}</td>
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
@endsection
