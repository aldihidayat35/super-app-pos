@extends('layouts.metronic.app')

@section('title', 'Transfer Stok - ' . config('app.name'))
@section('page_title', 'Daftar Transfer Stok')

@section('toolbar_actions')
    <x-metronic.permission-button permission="stock_transfers.create" :href="route('warehouse.stock-transfers.create')" icon="ki-outline ki-plus">Buat Transfer</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-6">
            <div class="col-md-3"><select name="source_work_location_id" class="form-select form-select-solid"><option value="">Semua sumber</option>@foreach($workLocations as $location)<option value="{{ $location->id }}" @selected(($filters['source_work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="destination_work_location_id" class="form-select form-select-solid"><option value="">Semua tujuan</option>@foreach($workLocations as $location)<option value="{{ $location->id }}" @selected(($filters['destination_work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>No</th><th>Sumber</th><th>Tujuan</th><th>Tanggal</th><th>Item</th><th>Status</th><th>Pengirim/Penerima</th><th></th></tr></thead>
                <tbody>
                @forelse($transfers as $transfer)
                    <tr>
                        <td class="fw-bold">{{ $transfer->number }}</td>
                        <td>{{ $transfer->sourceWorkLocation?->name }}</td>
                        <td>{{ $transfer->destinationWorkLocation?->name }}</td>
                        <td>{{ $transfer->transfer_date?->format('d/m/Y') }}</td>
                        <td>{{ $transfer->items->count() }} item</td>
                        <td><x-metronic.status-badge :status="$transfer->status" /></td>
                        <td>{{ $transfer->shipper?->name ?: '-' }} / {{ $transfer->receiver?->name ?: '-' }}</td>
                        <td class="text-end"><a href="{{ route('warehouse.stock-transfers.show', $transfer) }}" class="btn btn-sm btn-light-primary">Detail</a><a href="{{ route('warehouse.stock-transfers.print', $transfer) }}" class="btn btn-sm btn-light-success">Print</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada transfer stok" description="Transfer dari gudang ke toko/antar lokasi akan tampil di sini." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $transfers->links() }}
    </x-metronic.card>
@endsection
