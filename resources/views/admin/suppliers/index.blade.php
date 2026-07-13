@extends('layouts.metronic.app')
@section('title', 'Daftar Supplier')
@section('page_title', 'Daftar Supplier')
@section('toolbar_actions')
    <x-metronic.permission-button permission="suppliers.import" :href="route('admin.parties.import.index', 'suppliers')" variant="light" icon="ki-outline ki-file-up">Import</x-metronic.permission-button>
    <x-metronic.permission-button permission="suppliers.export" :href="route('admin.suppliers.export')" variant="light" icon="ki-outline ki-file-down">Export</x-metronic.permission-button>
    <x-metronic.permission-button permission="suppliers.create" :href="route('admin.suppliers.create')" icon="ki-outline ki-plus">Tambah Supplier</x-metronic.permission-button>
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
