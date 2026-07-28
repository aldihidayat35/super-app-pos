@extends('layouts.metronic.app')
@section('title', 'Daftar Supplier')
@section('page_title', 'Daftar Supplier')
@section('toolbar_actions')
    <x-metronic.permission-button permission="suppliers.import" :href="route('admin.parties.import.index', 'suppliers')" variant="light" icon="ki-outline ki-file-up">Import</x-metronic.permission-button>
    <x-metronic.permission-button permission="suppliers.export" :href="route('admin.suppliers.export')" variant="light" icon="ki-outline ki-file-down">Export</x-metronic.permission-button>
    <x-metronic.permission-button permission="suppliers.create" :href="route('admin.suppliers.create')" icon="ki-outline ki-plus">Tambah Supplier</x-metronic.permission-button>
@endsection
@section('page_guide')
    <x-metronic.page-guide id="admin-suppliers" title="Panduan Halaman Daftar Supplier">
        <x-slot:function>
            <p>Halaman ini mengelola daftar supplier yang memasok barang ke gudang. Super Admin, Purchasing, dan Owner menggunakannya untuk menambah, mengedit, dan memantau supplier serta performanya.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Cari supplier berdasarkan kode, nama, atau kontak.</li><li>Filter kota dan status aktif/nonaktif.</li><li>Tambah supplier baru atau import dari file.</li><li>Monitor skor performa supplier.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Cari kode/nama/kontak:</strong> kolom pencarian supplier.</li><li><strong>Kota:</strong> filter berdasarkan kota.</li><li><strong>Filter Status:</strong> aktif/nonaktif.</li><li><strong>Kode/Nama/Kontak:</strong> identitas supplier.</li><li><strong>Termin:</strong> termin pembayaran hari.</li><li><strong>Produk:</strong> jumlah produk yang disupply.</li><li><strong>Harga Terakhir:</strong> harga beli terakhir.</li><li><strong>Skor:</strong> skor performa supplier.</li><li><strong>Status:</strong> aktif/nonaktif supplier.</li><li><strong>Import/Export:</strong> upload/download data supplier.</li><li><strong>Tambah Supplier:</strong> membuat supplier baru.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Supplier yang nonaktif tidak dapat dipilih saat membuat PO. Skor performa dipakai modul evaluasi supplier. Termin memengaruhi jatuh tempo hutang.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Cari atau filter supplier.</li><li>Klik Tambah Supplier atau Import.</li><li>Lihat Detail untuk mengevaluasi performa.</li><li>Edit data supplier jika diperlukan.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Supplier dengan PO aktif tidak dapat dinonaktifkan.</li><li>Pastikan data kontak selalu update.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Supplier PT Sumber Rezeki, kota Bandung, termin 30 hari, produk 25, skor 90, aktif.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content')
<x-metronic.card>
    <form method="GET" class="d-flex flex-wrap justify-content-between gap-3 mb-5">
        <div class="d-flex flex-wrap gap-3">
            <input name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid w-225px" placeholder="Cari kode/nama/kontak">
            <input name="city" value="{{ $filters['city'] }}" class="form-control form-control-solid w-175px" placeholder="Kota">
            <select name="status" class="form-select form-select-solid w-175px"><option value="">Semua Status</option><option value="active" @selected($filters['status'] === 'active')>Aktif</option><option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option></select>
        </div>
        <button class="btn btn-light-primary">Filter</button>
    </form>
    <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode</th><th>Nama</th><th>Kontak</th><th>Kota</th><th>Termin</th><th>Produk</th><th>Harga Terakhir</th><th>Skor</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
    @forelse($suppliers as $supplier)
        <tr><td class="fw-bold">{{ $supplier->code }}</td><td><a href="{{ route('admin.suppliers.show', $supplier) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $supplier->name }}</a></td><td>{{ $supplier->contact_name ?: '-' }}<div class="text-muted">{{ $supplier->whatsapp_number ?: $supplier->email }}</div></td><td>{{ $supplier->city ?: '-' }}</td><td>{{ $supplier->payment_term_days }} hari</td><td>{{ $supplier->products_supplied_count }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($supplier->last_price) }}</td><td>{{ $supplier->performance_score }}</td><td><x-metronic.status-badge :status="$supplier->is_active ? 'active' : 'inactive'" :label="$supplier->is_active ? 'Aktif' : 'Nonaktif'" /></td><td class="text-end"><a href="{{ route('admin.suppliers.show', $supplier) }}" class="btn btn-sm btn-light">Detail</a> @can('update', $supplier)<a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-sm btn-light-primary">Edit</a>@endcan</td></tr>
    @empty
        <tr><td colspan="10"><x-metronic.empty-state title="Belum ada supplier" description="Supplier akan tampil setelah dibuat atau diimport." /></td></tr>
    @endforelse
    </tbody></table></div>{{ $suppliers->links() }}
</x-metronic.card>
@endsection
