@extends('layouts.metronic.app')

@section('title', 'Buat Transfer Stok - '.config('app.name'))
@section('page_title', 'Buat Transfer Stok')

@section('content')
    <div class="row g-5"><div class="col-xl-9">
        <x-metronic.card title="Konfirmasi Transfer {{ $restockRequest->number }}">
            <form method="POST" action="{{ route('retail.restock-requests.convert', $restockRequest) }}" id="convert-restock-form">@csrf
                <div class="row g-4 mb-6">
                    <div class="col-md-4"><div class="text-muted fs-8">PERMINTAAN</div><strong>{{ $restockRequest->number }}</strong></div>
                    <div class="col-md-4"><div class="text-muted fs-8">CABANG TUJUAN</div><strong>{{ $restockRequest->branch?->name }}</strong></div>
                    <div class="col-md-4"><div class="text-muted fs-8">STATUS</div><x-metronic.status-badge :status="$restockRequest->status" /></div>
                    <div class="col-md-6"><x-metronic.form-group name="source_warehouse_id" label="Gudang Sumber" required><select name="source_warehouse_id" id="convert-warehouse" class="form-select form-select-solid" required><option value="">Pilih gudang</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((int) old('source_warehouse_id', $restockRequest->source_warehouse_id) === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></x-metronic.form-group></div>
                    <div class="col-md-6"><x-metronic.form-group name="source_warehouse_location_id" label="Lokasi Ambil / Bin" required><select name="source_warehouse_location_id" id="convert-location" class="form-select form-select-solid" required><option value="">Pilih lokasi</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-warehouse="{{ $location->warehouse_id }}" @selected((int) old('source_warehouse_location_id') === $location->id)>{{ $location->full_code }} - {{ $location->name }}</option>@endforeach</select></x-metronic.form-group></div>
                </div>
                <div id="stock-warning" class="alert alert-warning d-none">Qty transfer melebihi stok tersedia pada lokasi yang dipilih.</div>
                <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Qty Diminta</th><th>Qty Disetujui</th><th>Stok Sumber</th><th>Qty Transfer</th></tr></thead><tbody>
                    @foreach($restockRequest->items as $item)<tr data-product="{{ $item->product_id }}"><td><strong>{{ $item->product?->sku }}</strong><div class="text-muted">{{ $item->product?->name }} / {{ $item->product?->baseUnit?->symbol }}</div></td><td>{{ qty($item->quantity_requested) }}</td><td>{{ qty($item->quantity_approved) }}</td><td class="source-balance fw-bold">0</td><td><input type="number" min="0" max="{{ qty_input($item->quantity_approved) }}" step="0.0001" name="items[{{ $item->id }}][quantity_transfer]" value="{{ old("items.{$item->id}.quantity_transfer", qty_input($item->quantity_approved)) }}" class="form-control transfer-qty" required></td></tr>@endforeach
                </tbody></table></div>
                <div class="d-flex justify-content-end gap-2 mt-5"><a href="{{ route('retail.restock-requests.index') }}" class="btn btn-light">Batal</a><button class="btn btn-primary" data-confirm="Buat transfer stok dari permintaan ini?"><i class="ki-outline ki-arrow-right-left fs-4 me-1"></i>Buat Transfer Stok</button></div>
            </form>
        </x-metronic.card>
    </div><div class="col-xl-3"><div class="alert alert-light-info"><strong>Periksa sebelum membuat</strong><div class="mt-2 fs-7">Transfer hanya dibuat sekali. Stok akan di-reserve saat transfer disetujui.</div></div></div></div>
@endsection

@push('scripts')<script>
(() => {
    const warehouse = document.getElementById('convert-warehouse'), location = document.getElementById('convert-location'); if (!warehouse || !location) return;
    const balances = @json($balances); const options = [...location.options].slice(1);
    const refreshLocations = () => { options.forEach(option => option.hidden = option.dataset.warehouse !== warehouse.value); if (location.selectedOptions[0]?.hidden) location.value = ''; refreshBalances(); };
    const refreshBalances = () => { let warning = false; document.querySelectorAll('tr[data-product]').forEach(row => { const available = Number(balances[`${location.value}-${row.dataset.product}`] || 0); row.querySelector('.source-balance').textContent = available.toLocaleString('id-ID', {maximumFractionDigits:4}); const qty = Number(row.querySelector('.transfer-qty').value || 0); row.classList.toggle('table-warning', qty > available); warning ||= qty > available; }); document.getElementById('stock-warning').classList.toggle('d-none', !warning); };
    warehouse.addEventListener('change', refreshLocations); location.addEventListener('change', refreshBalances); document.querySelectorAll('.transfer-qty').forEach(input => input.addEventListener('input', refreshBalances)); refreshLocations();
})();
</script>@endpush
