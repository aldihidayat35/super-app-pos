@extends('layouts.metronic.app')

@section('title', 'Lokasi Toko (Zona, Rak, Bin) - ' . config('app.name'))
@section('page_title', 'Lokasi Toko (Zona, Rak, Bin)')

@section('page_guide')
    <x-metronic.page-guide id="retail-locations" title="Panduan Halaman Lokasi Toko">
        <x-slot:function>
            <p>Halaman ini digunakan oleh Kepala Toko dan tim retail untuk mengelola struktur penempatan fisik barang di dalam toko: zona (misal: Area Display, Gudang Toko, Kasir), rak display, dan kotak/bin produk.</p>
            <p>Dengan memetakan lokasi fisik, kasir dan staf toko dapat dengan cepat menemukan barang, menghitung stok saat opname toko, serta mengontrol kapasitas rak.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pilih toko/cabang yang ingin dilihat atau dikelola.</li><li>Klik <strong>Tambah Lokasi Toko</strong> untuk mendaftarkan zona, rak, atau bin baru.</li><li>Tentukan toko, tipe lokasi (Zona / Rak / Bin), parent lokasi, kode fisik, dan nama.</li><li>Sistem otomatis membentuk kode hierarkis unik toko.</li><li>Lokasi aktif langsung dapat dipilih saat penerimaan restok, penyesuaian stok toko, etalase, dan penjualan POS.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Filter Toko:</strong> menyaring daftar lokasi berdasarkan cabang yang Anda kelola.</li><li><strong>Kode Penuh:</strong> identitas lengkap hierarki penempatan di toko.</li><li><strong>Parent:</strong> induk lokasi (misalnya rak berada di dalam zona display toko).</li><li><strong>Tipe:</strong> tingkat fisik (Zona, Rak, atau Bin).</li><li><strong>Kapasitas/Jenis Barang:</strong> acuan batas muatan rak dan klasifikasi kategori produk.</li><li><strong>Edit / Nonaktifkan:</strong> memperbarui data atau menonaktifkan rak/bin yang tidak lagi dipakai.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Lokasi toko yang aktif akan muncul pada opsi lokasi penyimpanan etalase toko, penerimaan transfer restok dari gudang, dan pencatatan fisik stok kasir.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Susun penomoran fisik toko yang mudah dibaca oleh staf dan kasir.</li><li>Buat Zona terlebih dahulu, lalu Rak, dan Bin.</li><li>Gunakan kode yang selaras dengan label stiker di rak toko fisik.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Jangan memilih parent dari cabang toko yang berbeda.</li><li>Periksa stok sebelum menonaktifkan lokasi rak/bin toko.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Toko Demo memiliki Zona Depan, Rak Snack A, dan Bin Tingkat 1. Kode penuh yang dihasilkan adalah <strong>TKO-DEMO-DPN-SNACKA-TK1</strong>.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    @can('opening_stocks.import')
        <a href="{{ route('warehouse.opening-stocks.import.index') }}" class="btn btn-light-primary me-2">
            <i class="ki-outline ki-file-up fs-2 me-1"></i> Import Stok Toko
        </a>
    @endcan
    <x-metronic.permission-button permission="stock.create" :href="route('retail.locations.create')" icon="ki-outline ki-plus">Tambah Lokasi Toko</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-5">
                <select name="branch_id" class="form-select form-select-solid" onchange="this.form.submit()">
                    <option value="">Semua Toko / Cabang</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select form-select-solid" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="active" @selected($status === 'active')>Aktif</option>
                    <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-light-primary w-100" type="submit">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th>Kode Penuh</th>
                        <th>Toko / Cabang</th>
                        <th>Parent</th>
                        <th>Tipe</th>
                        <th>Kapasitas</th>
                        <th>Jenis Barang</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($locations as $location)
                    <tr>
                        <td class="fw-bold">
                            {{ $location->full_code }}
                            <div class="text-muted fs-7">{{ $location->name }}</div>
                        </td>
                        <td>
                            <span class="badge badge-light-success fw-bold me-1"><i class="ki-outline ki-shop fs-8 me-1 text-success"></i>Toko</span>
                            <span class="fw-semibold text-gray-800">{{ $location->branch?->name ?: '-' }}</span>
                        </td>
                        <td>{{ $location->parent?->full_code ?: '-' }}</td>
                        <td><span class="badge badge-light fw-semibold">{{ $location->type->label() }}</span></td>
                        <td>{{ $location->capacity ? qty($location->capacity) : '-' }}</td>
                        <td>{{ $location->item_type ?: '-' }}</td>
                        <td><x-metronic.status-badge :status="$location->is_active ? 'active' : 'inactive'" :label="$location->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-end">
                            @can('update', $location)
                                <a href="{{ route('retail.locations.edit', $location) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                @if ($location->is_active)
                                    <form method="POST" action="{{ route('retail.locations.deactivate', $location) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm btn-light-danger" onclick="return confirm('Nonaktifkan lokasi toko ini?')">Nonaktifkan</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8"><x-metronic.empty-state title="Belum ada lokasi toko" description="Buat zona, rak, atau bin untuk memetakan penempatan barang di toko ini." /></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $locations->links() }}
    </x-metronic.card>
@endsection
