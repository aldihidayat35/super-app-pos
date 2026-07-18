@extends('layouts.metronic.app')

@section('title', 'Transfer Lokasi - ' . config('app.name'))
@section('page_title', 'Transfer Antar Lokasi Internal')

@section('content')
    <div class="row g-5">
        <div class="col-lg-4">
            <x-metronic.card title="Form Transfer">
                <form method="POST" action="{{ route('warehouse.location-transfers.store') }}">
                    @csrf

                    @error('transfer')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <x-metronic.form-group name="product_id" label="Produk">
                        <select name="product_id" class="form-select form-select-solid" required>
                            <option value="">Pilih produk</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="source_work_location_id" label="Lokasi Kerja Sumber">
                        <select id="source_work_location_id" name="source_work_location_id" class="form-select form-select-solid js-work-location" data-bin-target="source_warehouse_location_id" required>
                            <option value="">Pilih lokasi kerja</option>
                            @foreach ($workLocations as $location)
                                <option value="{{ $location->id }}" @selected(old('source_work_location_id') == $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="source_warehouse_location_id" label="Zona/Rak/Bin Sumber">
                        <select id="source_warehouse_location_id" name="source_warehouse_location_id" class="form-select form-select-solid js-bin-select">
                            <option value="">Tanpa bin</option>
                            @foreach ($warehouseLocations as $location)
                                <option value="{{ $location->id }}" data-work-location-id="{{ $location->warehouse?->work_location_id }}" @selected(old('source_warehouse_location_id') == $location->id)>{{ $location->full_code }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="destination_work_location_id" label="Lokasi Kerja Tujuan">
                        <select id="destination_work_location_id" name="destination_work_location_id" class="form-select form-select-solid js-work-location" data-bin-target="destination_warehouse_location_id" required>
                            <option value="">Pilih lokasi kerja</option>
                            @foreach ($workLocations as $location)
                                <option value="{{ $location->id }}" @selected(old('destination_work_location_id') == $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="destination_warehouse_location_id" label="Zona/Rak/Bin Tujuan">
                        <select id="destination_warehouse_location_id" name="destination_warehouse_location_id" class="form-select form-select-solid js-bin-select">
                            <option value="">Tanpa bin</option>
                            @foreach ($warehouseLocations as $location)
                                <option value="{{ $location->id }}" data-work-location-id="{{ $location->warehouse?->work_location_id }}" @selected(old('destination_warehouse_location_id') == $location->id)>{{ $location->full_code }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="quantity" label="Qty">
                        <input name="quantity" type="number" step="1" min="1" value="{{ old('quantity') }}" class="form-control form-control-solid" required>
                    </x-metronic.form-group>

                    <x-metronic.form-group name="reason" label="Alasan">
                        <textarea name="reason" class="form-control form-control-solid" rows="3" required>{{ old('reason') }}</textarea>
                    </x-metronic.form-group>

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
                                <td>{{ qty($mutation->quantity_on_hand_change) }}</td>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filterBinOptions = (workLocationSelect) => {
                const binSelect = document.getElementById(workLocationSelect.dataset.binTarget);

                if (!binSelect) {
                    return;
                }

                const selectedWorkLocationId = workLocationSelect.value;
                const selectedOption = binSelect.selectedOptions[0];

                Array.from(binSelect.options).forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }

                    const isAllowed = selectedWorkLocationId && option.dataset.workLocationId === selectedWorkLocationId;
                    option.hidden = !isAllowed;
                    option.disabled = !isAllowed;
                });

                if (selectedOption && selectedOption.value && selectedOption.disabled) {
                    binSelect.value = '';
                }
            };

            document.querySelectorAll('.js-work-location').forEach((select) => {
                filterBinOptions(select);
                select.addEventListener('change', () => filterBinOptions(select));
            });
        });
    </script>
@endpush
