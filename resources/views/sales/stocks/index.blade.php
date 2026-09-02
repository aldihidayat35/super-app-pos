@extends('layouts.metronic.app')

@section('title', 'Cek Stok')
@section('page_title', 'Cek Stok')
@section('page_guide')
    <x-metronic.page-guide id="sales-stock" title="Panduan Cek Stok Sales">
        <x-slot:function><p>Melihat ketersediaan stok pada gudang atau cabang yang diizinkan.</p></x-slot:function>
        <x-slot:workflow><p>Available Stock dihitung dari On Hand dikurangi Reserved dan Damaged.</p></x-slot:workflow>
        <x-slot:parts><p>Gunakan pencarian SKU/nama produk dan filter lokasi.</p></x-slot:parts>
        <x-slot:impacts><p>Halaman ini hanya baca dan tidak mengubah saldo stok.</p></x-slot:impacts>
        <x-slot:operation><p>Pilih lokasi lalu klik Filter.</p></x-slot:operation>
        <x-slot:warnings><p>Stok dapat berubah saat gudang memproses reservation atau transaksi lain.</p></x-slot:warnings>
        <x-slot:example><p>On Hand 20, Reserved 2, dan Damaged 1 menghasilkan Available 17.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Cek Stok" description="Informasi read-only; Anda tidak memiliki akses adjustment, transfer, opname, atau edit saldo." />
    <form method="GET" class="card card-body mb-5"><div class="row g-3"><div class="col-md-6"><input name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid" placeholder="Cari SKU atau nama produk"></div><div class="col-md-4"><select name="work_location_id" class="form-select form-select-solid"><option value="">Semua lokasi yang diizinkan</option>@foreach($workLocations as $location)<option value="{{ $location->id }}" @selected((int) $filters['work_location_id'] === $location->id)>{{ $location->name }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div></div></form>
    <x-metronic.card><div class="table-responsive"><table class="table align-middle"><thead><tr><th>SKU</th><th>Nama Produk</th><th>Gudang/Cabang</th><th>Lokasi</th><th>Available Stock</th></tr></thead><tbody>
        @forelse($stocks as $stock)<tr><td class="fw-bold">{{ $stock->product?->sku }}</td><td>{{ $stock->product?->name }}</td><td>{{ $stock->workLocation?->name }}</td><td>{{ $stock->warehouseLocation?->full_code ?: '-' }}</td><td class="fw-bold">{{ qty($stock->available_quantity) }}</td></tr>
        @empty<tr><td colspan="5"><x-metronic.empty-state title="Stok tidak ditemukan" description="Pastikan lokasi kerja telah ditugaskan kepada akun Anda." /></td></tr>@endforelse
    </tbody></table></div><div class="mt-4">{{ $stocks->links() }}</div></x-metronic.card>
@endsection
