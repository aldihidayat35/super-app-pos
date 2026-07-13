@extends('layouts.metronic.app')

@section('title', 'Transfer Lokasi - ' . config('app.name'))
@section('page_title', 'Transfer Antar Lokasi Internal')

@section('content')
    <div class="row g-5">
        <div class="col-lg-4">
            <x-metronic.card title="Form Transfer">
                <form method="POST" action="{{ route('warehouse.location-transfers.store') }}">
                    @csrf
                    <x-metronic.form-group name="product_id" label="Produk"><select name="product_id" class="form-select form-select-solid" required><option value="">Pilih produk</option>@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></x-metronic.form-group>
                    <x-metronic.form-group name="source_work_location_id" label="Lokasi Kerja Sumber"><select name="source_work_location_id" class="form-select form-select-solid" required>@foreach ($workLocations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></x-metronic.form-group>
                    <x-metronic.form-group name="source_warehouse_location_id" label="Zona/Rak/Bin Sumber"><select name="source_warehouse_location_id" class="form-select form-select-solid"><option value="">Tanpa bin</option>@foreach ($warehouseLocations as $location)<option value="{{ $location->id }}">{{ $location->full_code }}</option>@endforeach</select></x-metronic.form-group>
                    <x-metronic.form-group name="destination_work_location_id" label="Lokasi Kerja Tujuan"><select name="destination_work_location_id" class="form-select form-select-solid" required>@foreach ($workLocations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></x-metronic.form-group>
                    <x-metronic.form-group name="destination_warehouse_location_id" label="Zona/Rak/Bin Tujuan"><select name="destination_warehouse_location_id" class="form-select form-select-solid"><option value="">Tanpa bin</option>@foreach ($warehouseLocations as $location)<option value="{{ $location->id }}">{{ $location->full_code }}</option>@endforeach</select></x-metronic.form-group>
                    <x-metronic.form-group name="quantity" label="Qty"><input name="quantity" type="number" step="0.0001" min="0.0001" class="form-control form-control-solid" required></x-metronic.form-group>
                    <x-metronic.form-group name="reason" label="Alasan"><textarea name="reason" class="form-control form-control-solid" rows="3" required></textarea></x-metronic.form-group>
                    <input type="hidden" name="idempotency_key" value="{{ (string) str()->uuid() }}">
                    <button class="btn btn-primary w-100" type="submit">Proses Transfer</button>
                </form>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Histori Transfer">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Jenis</th><th>Lokasi</th><th>Qty</th><th>User</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($transfers as $mutation)
                            <tr>
                                <td>{{ $mutation->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ $mutation->product?->sku }}<div class="text-muted">{{ $mutation->product?->name }}</div></td>
                                <td>{{ $mutation->mutation_type->label() }}</td>
                                <td>{{ $mutation->warehouseLocation?->full_code ?: $mutation->workLocation?->name }}</td>
                                <td>{{ $mutation->quantity_on_hand_change }}</td>
                                <td>{{ $mutation->actor?->name ?: '-' }}</td>
                                <td class="text-end"><a href="{{ route('warehouse.stock-mutations.show', $mutation) }}" class="btn btn-sm btn-light">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-metronic.empty-state title="Belum ada transfer lokasi" description="Transfer internal akan menghasilkan mutasi keluar dan masuk." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $transfers->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection
