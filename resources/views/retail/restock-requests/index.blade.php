@extends('layouts.metronic.app')

@section('title', 'Permintaan Restok - '.config('app.name'))
@section('page_title', 'Permintaan Restok')

@section('content')
    <div class="alert alert-primary d-flex align-items-start gap-3">
        <i class="ki-outline ki-information-2 fs-2"></i>
        <div><strong>Permintaan restok tidak langsung memindahkan stok.</strong><div>Setelah disetujui, permintaan dapat diproses menjadi transfer stok.</div></div>
    </div>

    @can('create', \App\Models\RestockRequest::class)
        <x-metronic.card title="Ajukan Permintaan Restok" class="mb-6">
            <form method="POST" action="{{ route('retail.restock-requests.store') }}" id="restock-form">
                @csrf
                <div class="row g-4 mb-6">
                    <div class="col-md-3">
                        <x-metronic.form-group name="branch_id" label="Cabang Pemohon" required>
                            @if($branches->count() === 1)
                                <input type="hidden" name="branch_id" id="branch_id" value="{{ $branches->first()->id }}">
                                <select class="form-select form-select-solid" data-searchable="false" disabled aria-readonly="true"><option value="{{ $branches->first()->id }}" selected>{{ $branches->first()->name }}</option></select>
                            @else
                                <select name="branch_id" id="branch_id" class="form-select form-select-solid" required>
                                    <option value="">Pilih cabang</option>
                                    @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int) old('branch_id', $filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>@endforeach
                                </select>
                            @endif
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-3"><x-metronic.form-group name="requester" label="Requester"><input class="form-control form-control-solid" value="{{ auth()->user()->name }}" readonly></x-metronic.form-group></div>
                    <div class="col-md-2"><x-metronic.form-group name="request_date" label="Tanggal"><input class="form-control form-control-solid" value="{{ now()->format('d/m/Y H:i') }}" readonly></x-metronic.form-group></div>
                    <div class="col-md-2"><x-metronic.form-group name="priority" label="Prioritas"><select name="priority" class="form-select form-select-solid"><option value="normal">Normal</option><option value="high">Tinggi</option><option value="urgent">Mendesak</option><option value="low">Rendah</option></select></x-metronic.form-group></div>
                    <div class="col-md-2"><x-metronic.form-group name="needed_at" label="Dibutuhkan"><input type="date" name="needed_at" value="{{ old('needed_at', now()->addDay()->toDateString()) }}" class="form-control form-control-solid"></x-metronic.form-group></div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-3"><div><h3 class="fs-5 mb-1">Item Restok</h3><div class="text-muted fs-7">Rekomendasi menggunakan minimum stock + safety stock pada master produk.</div></div><button type="button" id="add-restock-item" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-plus fs-5"></i> Tambah Produk</button></div>
                <div class="table-responsive mb-5">
                    <table class="table table-row-bordered align-middle restock-item-table">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-8"><th>Produk</th><th>SKU / Unit</th><th>Stok Sekarang</th><th>Stok Minimum</th><th>Target Stok</th><th>Rekomendasi</th><th>Qty Diminta</th><th>Prioritas</th><th>Alasan</th><th></th></tr></thead>
                        <tbody id="restock-items"></tbody>
                    </table>
                </div>
                <template id="restock-item-template">
                    <tr class="restock-item-row">
                        <td><select name="items[__INDEX__][product_id]" class="form-select form-select-sm restock-product" data-placeholder="Pilih produk" required><option value=""></option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} - {{ $product->name }}</option>@endforeach</select></td>
                        <td><strong class="metric-sku">-</strong><div class="text-muted metric-unit">-</div></td>
                        <td class="metric-stock fw-bold">0</td><td class="metric-minimum">0</td><td class="metric-target">0</td><td class="metric-recommendation text-primary fw-bold">0</td>
                        <td><input name="items[__INDEX__][quantity_requested]" type="number" min="0.0001" step="0.0001" class="form-control form-control-sm restock-qty" required></td>
                        <td><select name="items[__INDEX__][priority]" class="form-select form-select-sm" data-searchable="false"><option value="normal">Normal</option><option value="high">Tinggi</option><option value="urgent">Mendesak</option><option value="low">Rendah</option></select></td>
                        <td><input name="items[__INDEX__][notes]" class="form-control form-control-sm" placeholder="Alasan kebutuhan"></td>
                        <td><button type="button" class="btn btn-sm btn-icon btn-light-danger remove-restock-item" aria-label="Hapus item"><i class="ki-outline ki-trash"></i></button></td>
                    </tr>
                </template>
                <x-metronic.form-group name="notes" label="Catatan Permintaan"><textarea name="notes" class="form-control form-control-solid" rows="2">{{ old('notes') }}</textarea></x-metronic.form-group>
                <div class="d-flex flex-wrap justify-content-end gap-2"><button name="action" value="draft" class="btn btn-light">Simpan Draft</button><button name="action" value="submit" class="btn btn-primary"><i class="ki-outline ki-send fs-5 me-1"></i>Ajukan Permintaan</button></div>
            </form>
        </x-metronic.card>
    @endcan

    <div class="d-flex flex-wrap align-items-center gap-2 mb-4 fs-8">
        @foreach(\App\Enums\RestockRequestStatus::cases() as $status)<span class="badge badge-light-primary">{{ $status->label() }}</span>@if(!$loop->last)<i class="ki-outline ki-arrow-right text-muted"></i>@endif @endforeach
    </div>
    <x-metronic.card title="Daftar Permintaan Restok">
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-4"><select name="branch_id" class="form-select form-select-solid"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold fs-8 text-uppercase"><th>Permintaan</th><th>Cabang / Requester</th><th>Gudang Sumber</th><th>Item / Qty</th><th>Prioritas</th><th>Status</th><th>Transfer Terkait</th><th>Aksi</th></tr></thead><tbody>
        @forelse($requests as $restock)
            <tr><td><a href="{{ route('retail.restock-requests.show', $restock) }}" class="fw-bold">{{ $restock->number }}</a><div class="text-muted fs-8">{{ $restock->created_at?->format('d/m/Y H:i') }}</div></td><td>{{ $restock->branch?->name }}<div class="text-muted fs-8">{{ $restock->requester?->name }}</div></td><td>{{ $restock->sourceWarehouse?->name ?: '-' }}</td><td>{{ $restock->items->count() }} item / {{ qty($restock->requestedQuantity()) }}</td><td>{{ ucfirst($restock->priority) }}</td><td><x-metronic.status-badge :status="$restock->status" /></td><td>@if($transfer = $restock->stockTransfers->first())<a href="{{ route('warehouse.stock-transfers.show', $transfer) }}" class="fw-bold">{{ $transfer->number }}</a>@else<span class="text-muted">-</span>@endif</td><td><div class="d-flex flex-wrap gap-2">
                @can('approve', $restock)
                    @if($restock->status === \App\Enums\RestockRequestStatus::PENDING_APPROVAL)
                        <form method="POST" action="{{ route('retail.restock-requests.approve', $restock) }}">@csrf @foreach($restock->items as $item)<input type="hidden" name="items[{{ $item->id }}][quantity_approved]" value="{{ qty_input($item->quantity_requested) }}">@endforeach<button class="btn btn-sm btn-light-success">Setujui</button></form>
                        <form method="POST" action="{{ route('retail.restock-requests.reject', $restock) }}">@csrf<input type="hidden" name="reason" value="Ditolak dari daftar"><button class="btn btn-sm btn-light-danger" data-confirm="Tolak permintaan restok ini?">Tolak</button></form>
                    @elseif($restock->status === \App\Enums\RestockRequestStatus::APPROVED)
                        <a href="{{ route('retail.restock-requests.convert-form', $restock) }}" class="btn btn-sm btn-primary">Buat Transfer Stok</a>
                    @endif
                @endcan
            </div></td></tr>
        @empty<tr><td colspan="8"><x-metronic.empty-state title="Belum ada permintaan restok" description="Permintaan kebutuhan stok cabang akan tampil di sini." /></td></tr>@endforelse
        </tbody></table></div>{{ $requests->links() }}
    </x-metronic.card>
@endsection

@push('styles')<style>.restock-item-table{min-width:1180px}.restock-item-table .restock-product{min-width:230px}.restock-item-table input,.restock-item-table select{min-width:90px}</style>@endpush
@push('scripts')
<script>
(() => {
    const form = document.getElementById('restock-form');
    if (!form) return;
    const metrics = @json($productMetrics);
    const body = document.getElementById('restock-items');
    const template = document.getElementById('restock-item-template').innerHTML;
    const branch = document.getElementById('branch_id');
    let index = 0;
    const number = value => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 4 });
    const update = row => {
        const data = metrics[row.querySelector('.restock-product').value] || {};
        const current = Number(data.stocks?.[branch?.value] || 0);
        const target = Number(data.target || 0);
        const recommendation = Math.max(target - current, 0);
        row.querySelector('.metric-sku').textContent = data.sku || '-'; row.querySelector('.metric-unit').textContent = data.unit || '-';
        row.querySelector('.metric-stock').textContent = number(current); row.querySelector('.metric-minimum').textContent = number(data.minimum); row.querySelector('.metric-target').textContent = number(target); row.querySelector('.metric-recommendation').textContent = number(recommendation);
        const qty = row.querySelector('.restock-qty'); if (!qty.value || qty.dataset.auto === '1') { qty.value = recommendation || ''; qty.dataset.auto = '1'; }
    };
    const add = values => {
        body.insertAdjacentHTML('beforeend', template.replaceAll('__INDEX__', index++));
        const row = body.lastElementChild;
        if (values?.product_id) row.querySelector('.restock-product').value = values.product_id;
        if (values?.quantity_requested) { row.querySelector('.restock-qty').value = values.quantity_requested; row.querySelector('.restock-qty').dataset.auto = '0'; }
        row.querySelector('.restock-product').addEventListener('change', () => update(row));
        row.querySelector('.restock-qty').addEventListener('input', event => event.target.dataset.auto = '0');
        row.querySelector('.remove-restock-item').addEventListener('click', () => { if (body.children.length > 1) row.remove(); });
        update(row);
    };
    document.getElementById('add-restock-item').addEventListener('click', () => add());
    branch?.addEventListener('change', () => body.querySelectorAll('tr').forEach(update));
    const oldItems = @json(old('items', [])); if (oldItems.length) oldItems.forEach(add); else add();
})();
</script>
@endpush
