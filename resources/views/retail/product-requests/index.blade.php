@extends('layouts.metronic.app')

@section('title', 'Pengajuan Produk Toko')

@section('content')
    <x-metronic.page-title title="Pengajuan Produk Baru" description="Usulkan produk yang belum ada di master data sebelum dibeli dan diterima toko." />

    <div class="d-flex justify-content-end mb-5">
        @can('create', App\Models\ProductRequest::class)
            <a href="{{ route('retail.product-requests.create') }}" class="btn btn-primary">Ajukan Produk</a>
        @endcan
    </div>

    <x-metronic.card title="Daftar Pengajuan">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold"><th>Nomor</th><th>Produk</th><th>Toko</th><th>Pengaju</th><th>Status</th><th>Hasil</th><th></th></tr></thead>
                <tbody>
                @forelse($requests as $proposal)
                    <tr>
                        <td>{{ $proposal->number }}</td>
                        <td><div class="fw-bold">{{ $proposal->name }}</div><div class="text-muted fs-8">SKU: {{ $proposal->proposed_sku ?: 'dibuat otomatis' }}</div></td>
                        <td>{{ $proposal->branch?->name }}</td>
                        <td>{{ $proposal->requester?->name }}</td>
                        <td><span class="badge badge-light-{{ $proposal->status === 'approved' ? 'success' : ($proposal->status === 'rejected' ? 'danger' : 'warning') }}">{{ ['pending_approval' => 'Menunggu pemeriksaan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$proposal->status] ?? $proposal->status }}</span></td>
                        <td>{{ $proposal->createdProduct ? $proposal->createdProduct->sku : '-' }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light-primary" href="{{ route('retail.product-requests.show', $proposal) }}">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada pengajuan" description="Gunakan tombol Ajukan Produk bila barang belum ada di master data." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    </x-metronic.card>
@endsection
