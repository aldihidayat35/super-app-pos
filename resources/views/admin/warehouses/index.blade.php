@extends('layouts.metronic.app')

@section('title', 'Daftar Gudang - ' . config('app.name'))
@section('page_title', 'Daftar Gudang')

@section('page_guide')
    <x-metronic.page-guide id="admin-warehouses" title="Panduan Halaman Daftar Gudang">
        <x-slot:function>
            <p>Halaman ini mengelola daftar gudang utama dalam sistem. Owner dan Super Admin menggunakannya untuk menambah, mengedit, dan memantau status gudang yang berfungsi sebagai lokasi penyimpanan stok pusat.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pengguna dapat mencari gudang berdasarkan kota atau memfilter status.</li><li>Klik Tambah Gudang untuk membuat gudang baru.</li><li>Gudang yang aktif dapat diedit atau dinonaktifkan.</li><li>Gudang nonaktif tidak dapat dipilih untuk transaksi baru.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Kode:</strong> identitas unik gudang.</li><li><strong>Nama:</strong> nama lengkap gudang.</li><li><strong>Kota:</strong> lokasi geografis gudang.</li><li><strong>Kepala Gudang:</strong> user yang menjabat sebagai manajer gudang.</li><li><strong>Kapasitas:</strong> batas muatan maksimum gudang.</li><li><strong>Area Layanan:</strong> wilayah distribusi gudang.</li><li><strong>Status:</strong> Aktif atau Nonaktif.</li><li><strong>Detail:</strong> membuka halaman rincian gudang.</li><li><strong>Edit:</strong> mengubah data gudang.</li><li><strong>Nonaktifkan:</strong> menonaktifkan gudang tanpa menghapus.</li><li><strong>Tambah Gudang:</strong> membuka form pembuatan gudang baru.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Menonaktifkan gudang mencegah pemilihan untuk transaksi baru namun tidak menghapus histori. Kapasitas dan area layanan memengaruhi perencanaan logistik. Gudang yang dibuat menjadi pilihan pada form penerimaan barang dan transfer stok.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Gunakan kolom pencarian kota atau filter status.</li><li>Klik tombol Filter untuk menerapkan.</li><li>Klik Tambah Gudang untuk membuat gudang baru.</li><li>Klik Detail untuk melihat informasi lengkap.</li><li>Klik Edit untuk mengubah data gudang.</li><li>Klik Nonaktifkan untuk menonaktifkan gudang tanpa menghapus.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Jangan mengaktifkan kembali gudang yang memiliki saldo stok belum direkonsiliasi.</li><li>Gudang dengan histori transaksi tidak dapat dihapus.</li><li>Pastikan kepala gudang ditunjuk sebelum gudang aktif.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Gudang GDG-PUSAT di Jakarta aktif dengan kapasitas 10000 unit dan kepala gudang Budi. Area layanan Jabodetabek. Gudang dinonaktifkan karena pindah lokasi. Data histori tetap tercatat.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <x-metronic.permission-button permission="admin.warehouses.create" :href="route('admin.warehouses.create')" icon="ki-outline ki-plus">
        Tambah Gudang
    </x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" action="{{ route('admin.warehouses.index') }}" class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
            <div class="d-flex flex-wrap gap-3">
                <input name="city" value="{{ $city }}" class="form-control form-control-solid w-225px" placeholder="Filter kota">
                <select name="status" class="form-select form-select-solid w-175px">
                    <option value="">Semua Status</option>
                    <option value="active" @selected($status === 'active')>Aktif</option>
                    <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <button class="btn btn-light-primary" type="submit"><i class="ki-outline ki-magnifier"></i> Filter</button>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode</th><th>Nama</th><th>Kota</th><th>Kepala Gudang</th><th>Kapasitas</th><th>Area Layanan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($warehouses as $warehouse)
                        <tr>
                            <td class="fw-bold">{{ $warehouse->code }}</td>
                            <td><a href="{{ route('admin.warehouses.show', $warehouse) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $warehouse->name }}</a></td>
                            <td>{{ $warehouse->city ?: '-' }}</td>
                            <td>{{ $warehouse->manager?->name ?: '-' }}</td>
                            <td>{{ $warehouse->capacity ? qty($warehouse->capacity) : '-' }}</td>
                            <td>{{ $warehouse->service_area ?: '-' }}</td>
                            <td><x-metronic.status-badge :status="$warehouse->is_active ? 'active' : 'inactive'" :label="$warehouse->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                            <td class="text-end">
                                <a href="{{ route('admin.warehouses.show', $warehouse) }}" class="btn btn-sm btn-light">Detail</a>
                                @can('update', $warehouse)
                                    <a href="{{ route('admin.warehouses.edit', $warehouse) }}" class="btn btn-sm btn-light-primary">Edit</a>
                                    @if ($warehouse->is_active)
                                        <form method="POST" action="{{ route('admin.warehouses.deactivate', $warehouse) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-light-danger" type="submit">Nonaktifkan</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-metronic.empty-state title="Belum ada gudang" description="Gudang akan tampil setelah dibuat." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $warehouses->links() }}
    </x-metronic.card>
@endsection
