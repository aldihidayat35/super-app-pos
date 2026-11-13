@extends('layouts.metronic.app')

@section('title', 'Zona, Rak, dan Bin - ' . config('app.name'))
@section('page_title', 'Zona, Rak, dan Bin')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-locations" title="Panduan Halaman Zona, Rak, dan Bin">
        <x-slot:function>
            <p>Halaman ini mengelola struktur lokasi fisik di dalam gudang maupun toko: zona, rak, dan bin. Tim gudang dan toko menggunakannya agar setiap saldo dan mutasi stok memiliki lokasi penyimpanan fisik yang jelas dan tertata.</p>
            <p>Lokasi terikat ke gudang atau toko dan dapat memiliki struktur hierarki parent. Kode penuh dibentuk otomatis dari kode tempat, parent, dan kode lokasi.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Gunakan filter tipe tempat, gudang, toko, atau status untuk menyaring daftar lokasi.</li><li>Klik <strong>Tambah Lokasi</strong> untuk membuat zona, rak, atau bin baru.</li><li>Pilih tipe tempat (Gudang atau Toko), unit kerja, tipe lokasi, parent yang sesuai, lalu isi kode dan kapasitas.</li><li>Sistem memvalidasi akses lokasi kerja dan membentuk kode unik hierarkis secara otomatis.</li><li>Lokasi aktif siap digunakan dalam penerimaan barang, mutasi, transfer, opname, serta penjualan POS toko.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Tipe Tempat:</strong> memfilter lokasi berdasarkan kepemilikan Gudang atau Toko/Cabang.</li><li><strong>Tempat / Lokasi Kerja:</strong> unit kerja pemilik lokasi fisik dengan penanda badge jenis tempat.</li><li><strong>Kode Penuh:</strong> identitas hierarkis lengkap lokasi fisik.</li><li><strong>Parent:</strong> lokasi induk, misalnya rak berada di dalam zona tertentu.</li><li><strong>Tipe:</strong> tingkat fisik lokasi (Zona, Rak, atau Bin).</li><li><strong>Kapasitas/Jenis Barang:</strong> pedoman batas muatan dan peruntukan kategori barang.</li><li><strong>Edit:</strong> memperbarui informasi data lokasi.</li><li><strong>Nonaktifkan:</strong> menonaktifkan lokasi dari transaksi baru tanpa menghapus riwayat audit stok terdahulu.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Lokasi fisik aktif langsung tersedia pada formulir transfer, penerimaan barang, kartu stok, stock opname gudang/toko, dan etalase toko. Menonaktifkan lokasi tidak akan merusak saldo atau audit mutasi yang telah tercatat sebelumnya.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Rencanakan struktur tata letak fisik sebelum mendaftarkan lokasi ke dalam sistem.</li><li>Buat Zona terlebih dahulu sebagai area utama, lalu buat Rak di dalam zona, dan Bin di dalam rak.</li><li>Gunakan kode ringkas yang sama dengan label fisik pada rak/bin di lapangan.</li><li>Pastikan kode penuh yang terbentuk sudah sesuai urutan hierarki yang diinginkan.</li><li>Gunakan fitur Edit untuk perbaikan data label; nonaktifkan lokasi jika sudah tidak digunakan secara fisik.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Jangan memilih parent dari gudang atau toko yang berbeda.</li><li>Jangan menduplikasi kode lokasi fisik untuk tempat yang berbeda.</li><li>Pastikan memeriksa saldo barang aktif sebelum menonaktifkan suatu lokasi.</li><li>Lokasi yang pernah memiliki riwayat mutasi dilindungi dan tidak dapat dihapus permanen demi keutuhan audit.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Toko Demo memiliki Zona Display, Rak Depan, dan Bin A1. Setelah dibuat berurutan, kode penuh seperti <strong>TKO-DEMO-DISPLAY-DEPAN-A1</strong> dapat dipilih saat kasir memproses barang atau saat opname toko.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <x-metronic.permission-button permission="stock.create" :href="route('warehouse.locations.create')" icon="ki-outline ki-plus">Tambah Lokasi</x-metronic.permission-button>
@endsection

@section('content')
    <x-warehouse.active-location-card />

    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-3">
                <select name="owner_type" class="form-select form-select-solid" onchange="this.form.submit()">
                    <option value="">Semua Tipe Tempat</option>
                    <option value="warehouse" @selected($ownerType === 'warehouse')>Hanya Gudang</option>
                    <option value="branch" @selected($ownerType === 'branch')>Hanya Toko / Cabang</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="warehouse_id" class="form-select form-select-solid">
                    <option value="">Semua gudang</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="branch_id" class="form-select form-select-solid">
                    <option value="">Semua toko / cabang</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-solid">
                    <option value="">Semua status</option>
                    <option value="active" @selected($status === 'active')>Aktif</option>
                    <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-light-primary w-100" type="submit">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-7">
                        <th>Kode Penuh</th>
                        <th>Tempat / Lokasi Kerja</th>
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
                            @if ($location->branch)
                                <span class="badge badge-light-success fw-bold me-1"><i class="ki-outline ki-shop fs-8 me-1 text-success"></i>Toko</span>
                                <span class="fw-semibold text-gray-800">{{ $location->branch->name }}</span>
                            @elseif ($location->warehouse)
                                <span class="badge badge-light-primary fw-bold me-1"><i class="ki-outline ki-home-2 fs-8 me-1 text-primary"></i>Gudang</span>
                                <span class="fw-semibold text-gray-800">{{ $location->warehouse->name }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $location->parent?->full_code ?: '-' }}</td>
                        <td><span class="badge badge-light fw-semibold">{{ $location->type->label() }}</span></td>
                        <td>{{ $location->capacity ? qty($location->capacity) : '-' }}</td>
                        <td>{{ $location->item_type ?: '-' }}</td>
                        <td><x-metronic.status-badge :status="$location->is_active ? 'active' : 'inactive'" :label="$location->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-end">
                            @can('update', $location)
                                <a href="{{ route('warehouse.locations.edit', $location) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                @if ($location->is_active)
                                    <form method="POST" action="{{ route('warehouse.locations.deactivate', $location) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm btn-light-danger" onclick="return confirm('Nonaktifkan lokasi ini?')">Nonaktifkan</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8"><x-metronic.empty-state title="Belum ada lokasi" description="Buat zona, rak, atau bin untuk mulai memetakan stok gudang atau toko." /></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $locations->links() }}
    </x-metronic.card>
@endsection
