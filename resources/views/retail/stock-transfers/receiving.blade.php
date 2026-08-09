@extends('layouts.metronic.app')

@section('title', 'Terima Transfer - '.config('app.name'))
@section('page_title', 'Terima Transfer')

@section('content')
    <div class="alert alert-primary d-flex align-items-center gap-3"><i class="ki-outline ki-delivery-2 fs-2"></i><div>Barang yang sedang dikirim menuju lokasi Anda dan siap diproses penerimaannya.</div></div>
    <x-metronic.card>
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Nomor Transfer</th><th>Lokasi Asal</th><th>Lokasi Tujuan</th><th>Tanggal Kirim</th><th>Jumlah Item</th><th>Qty Dikirim</th><th>Sudah Diterima</th><th>Sisa</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse($transfers as $transfer)
            @php($shipped = $transfer->items->reduce(fn($carry, $item) => \App\Support\Decimal::add($carry, (string) $item->quantity_shipped), '0.0000'))
            @php($received = $transfer->items->reduce(fn($carry, $item) => \App\Support\Decimal::add($carry, \App\Support\Decimal::add((string) $item->quantity_received, \App\Support\Decimal::add((string) $item->quantity_damaged, (string) $item->quantity_discrepancy))), '0.0000'))
            <tr><td><strong>{{ $transfer->number }}</strong><div class="text-muted fs-8">Pengirim: {{ $transfer->shipper?->name ?: '-' }}</div></td><td>{{ $transfer->sourceWorkLocation?->name }}</td><td>{{ $transfer->destinationWorkLocation?->name }}</td><td>{{ $transfer->shipped_at?->format('d/m/Y H:i') ?: '-' }}</td><td>{{ $transfer->items->count() }}</td><td>{{ qty($shipped) }}</td><td>{{ qty($received) }}</td><td class="fw-bold text-primary">{{ qty(\App\Support\Decimal::sub($shipped, $received)) }}</td><td><x-metronic.status-badge :status="$transfer->status" /></td><td><a href="{{ route('retail.stock-transfers.receive-form', $transfer) }}" class="btn btn-sm btn-primary">Terima Barang</a></td></tr>
        @empty<tr><td colspan="10"><x-metronic.empty-state title="Tidak ada transfer siap diterima" description="Transfer akan muncul setelah gudang mengirim barang ke lokasi kerja Anda." icon="ki-outline ki-delivery-2" /></td></tr>@endforelse
        </tbody></table></div>{{ $transfers->links() }}
    </x-metronic.card>
@endsection
