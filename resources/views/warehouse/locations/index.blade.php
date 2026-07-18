@extends('layouts.metronic.app')

@section('title', 'Zona, Rak, dan Bin - ' . config('app.name'))
@section('page_title', 'Zona, Rak, dan Bin')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-locations" title="Panduan Halaman Zona, Rak, dan Bin">
        <x-slot:function>
            <p>Halaman ini mengelola struktur lokasi fisik di dalam gudang: zona, rak, dan bin. Kepala Gudang dan Staff Gudang menggunakannya agar setiap saldo dan mutasi stok memiliki lokasi penyimpanan yang jelas.</p>
            <p>Lokasi terikat ke gudang dan dapat memiliki parent. Kode penuh dibentuk otomatis dari kode gudang, parent, dan kode lokasi.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pilih gudang atau status untuk menyaring daftar.</li><li>Klik <strong>Tambah Lokasi</strong> untuk membuat zona, rak, atau bin.</li><li>Pilih gudang, tipe, parent yang sesuai, lalu isi kode dan informasi kapasitas.</li><li>Sistem memvalidasi akses lokasi kerja dan membentuk kode penuh.</li><li>Lokasi aktif dapat dipakai oleh transaksi inventory; lokasi yang tidak lagi dipakai dapat dinonaktifkan.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Semua gudang:</strong> membatasi daftar ke gudang tertentu.</li><li><strong>Semua status:</strong> menampilkan lokasi aktif atau nonaktif.</li><li><strong>Kode Penuh:</strong> identitas hierarkis lokasi fisik.</li><li><strong>Parent:</strong> lokasi induk, misalnya rak berada di dalam zona.</li><li><strong>Tipe:</strong> menunjukkan apakah data berupa zona, rak, atau bin.</li><li><strong>Kapasitas/Jenis Barang:</strong> pedoman penempatan agar lokasi tidak salah atau penuh.</li><li><strong>Edit:</strong> memperbarui data lokasi sesuai izin.</li><li><strong>Nonaktifkan:</strong> mencegah lokasi dipilih untuk proses baru tanpa menghapus histori.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Lokasi aktif tersedia pada form penerimaan, transfer, saldo, dan kartu stok. Perubahan kode akan mengubah label yang terlihat pada halaman tersebut. Menonaktifkan lokasi tidak menghapus saldo atau histori mutasi yang sudah tercatat.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Tentukan struktur fisik gudang sebelum membuat data.</li><li>Buat zona terlebih dahulu, dilanjutkan rak, lalu bin.</li><li>Gunakan kode singkat dan unik yang sama dengan label fisik gudang.</li><li>Isi kapasitas dan jenis barang bila dipakai sebagai kontrol operasional.</li><li>Simpan dan pastikan Kode Penuh sesuai susunan lokasi.</li><li>Gunakan Edit untuk koreksi; nonaktifkan hanya jika lokasi tidak lagi dipakai.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Jangan memilih parent dari struktur atau gudang yang tidak sesuai.</li><li>Jangan menggunakan satu kode fisik untuk dua tempat berbeda.</li><li>Periksa stok dan transaksi aktif sebelum menonaktifkan lokasi.</li><li>Lokasi yang sudah memiliki histori tidak dihapus agar audit tetap utuh.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Gudang Pusat memiliki Zona A, Rak 01, dan Bin 03. Setelah dibuat berurutan, kode penuh seperti <strong>GDG-A-01-03</strong> dapat dipilih saat barang diterima dan muncul pada kartu stok.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <x-metronic.permission-button permission="stock.create" :href="route('warehouse.locations.create')" icon="ki-outline ki-plus">Tambah Lokasi</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-4"><select name="warehouse_id" class="form-select form-select-solid"><option value="">Semua gudang</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option><option value="active" @selected($status === 'active')>Aktif</option><option value="inactive" @selected($status === 'inactive')>Nonaktif</option></select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode Penuh</th><th>Gudang</th><th>Parent</th><th>Tipe</th><th>Kapasitas</th><th>Jenis Barang</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse ($locations as $location)
                    <tr>
                        <td class="fw-bold">{{ $location->full_code }}<div class="text-muted">{{ $location->name }}</div></td>
                        <td>{{ $location->warehouse?->name }}</td>
                        <td>{{ $location->parent?->full_code ?: '-' }}</td>
                        <td>{{ $location->type->label() }}</td>
                        <td>{{ $location->capacity ? qty($location->capacity) : '-' }}</td>
                        <td>{{ $location->item_type ?: '-' }}</td>
                        <td><x-metronic.status-badge :status="$location->is_active ? 'active' : 'inactive'" :label="$location->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-end">
                            @can('update', $location)
                                <a href="{{ route('warehouse.locations.edit', $location) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                @if ($location->is_active)<form method="POST" action="{{ route('warehouse.locations.deactivate', $location) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-light-danger">Nonaktifkan</button></form>@endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada lokasi gudang" description="Buat zona, rak, atau bin untuk mulai memetakan stok." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $locations->links() }}
    </x-metronic.card>
@endsection
