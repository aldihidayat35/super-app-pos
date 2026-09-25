@extends('layouts.metronic.app')

@section('title', 'Koreksi Stok - ' . config('app.name'))
@section('page_title', 'Koreksi Stok Langsung')

@section('page_guide')
    <x-metronic.page-guide id="stock-direct-adjustment" title="Panduan Koreksi Stok Langsung">
        <x-slot:function><p>Mencatat stok fisik akhir per produk secara resmi tanpa mengubah HPP.</p></x-slot:function>
        <x-slot:workflow><p>Kepala lokasi membuat draft, mengajukan, lalu kepala lokasi lain atau Super Admin menyetujui.</p></x-slot:workflow>
        <x-slot:warnings><div class="alert alert-warning mb-0">Pembuat tidak dapat menyetujui sendiri. Stok baru berubah setelah persetujuan.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    @error('adjustment')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <x-metronic.card title="Status Akses Lokasi" class="mb-6">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5"><label class="form-label">Lokasi</label><select name="work_location_id" class="form-select" onchange="this.form.submit()">@foreach($locations as $location)<option value="{{ $location->id }}" @selected($selectedLocationId === $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Status dokumen</label><select name="status" class="form-select"><option value="">Semua</option>@foreach(['draft'=>'Draft','pending_approval'=>'Menunggu Persetujuan','completed'=>'Selesai','rejected'=>'Ditolak','cancelled'=>'Dibatalkan'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-light-primary">Terapkan</button> <a href="{{ route('warehouse.stock-adjustments.export', request()->query()) }}" class="btn btn-light-success">CSV</a></div>
        </form>
        <div class="alert {{ $access?->isCurrentlyActive() ? 'alert-success' : 'alert-warning' }} mt-4 mb-0">
            @if($access?->isCurrentlyActive()) Akses aktif sampai {{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB. @else Fitur terkunci. Super Admin harus mengaktifkan lokasi ini terlebih dahulu. @endif
        </div>
    </x-metronic.card>

    @if(auth()->user()->hasRole('kepala_gudang') || auth()->user()->hasRole('kepala_toko'))
        @if($access?->isCurrentlyActive() && $stocks->isNotEmpty())
            <x-metronic.card title="Buat Draft Koreksi" class="mb-6">
                <form method="POST" action="{{ route('warehouse.stock-adjustments.store') }}" id="adjustment-form">@csrf
                    <input type="hidden" name="work_location_id" value="{{ $selectedLocationId }}">
                    <div id="item-rows">
                        <div class="row g-3 mb-3 adjustment-row">
                            <div class="col-md-4"><label class="form-label">Produk / stok</label><select name="items[0][stock_id]" class="form-select" required>@foreach($stocks as $stock)<option value="{{ $stock->id }}">{{ $stock->product?->sku }} — {{ $stock->product?->name }} ({{ qty($stock->quantity_on_hand) }}) {{ $stock->warehouseLocation?->full_code }}</option>@endforeach</select></div>
                            <div class="col-md-3"><label class="form-label">Stok fisik final</label><input type="number" step="0.0001" min="0" name="items[0][physical_qty]" class="form-control" required></div>
                            <div class="col-md-4"><label class="form-label">Catatan</label><input name="items[0][note]" class="form-control" required></div>
                            <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-light-danger remove-row">×</button></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-light-primary" id="add-row">Tambah Produk</button>
                    <div class="mt-4"><label class="form-label">Catatan dokumen</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                    <button class="btn btn-primary mt-4">Simpan Draft</button>
                </form>
            </x-metronic.card>
        @endif
    @endif

    <x-metronic.card title="Riwayat Koreksi">
        <div class="table-responsive"><table class="table table-row-bordered align-middle"><thead><tr><th>Nomor</th><th>Lokasi</th><th>Status</th><th>Pembuat</th><th>Item</th><th>Dibuat</th><th></th></tr></thead><tbody>
            @forelse($adjustments as $row)<tr><td class="fw-semibold">{{ $row->number }}</td><td>{{ $row->workLocation?->name }}</td><td><span class="badge badge-light-primary">{{ str_replace('_',' ',ucfirst($row->status)) }}</span></td><td>{{ $row->creator?->name }}</td><td>{{ $row->items->count() }}</td><td>{{ $row->created_at?->format('d/m/Y H:i') }}</td><td><a href="{{ route('warehouse.stock-adjustments.show', $row) }}" class="btn btn-sm btn-light-primary">Detail</a></td></tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-10">Belum ada dokumen koreksi.</td></tr>@endforelse
        </tbody></table></div>{{ $adjustments->links() }}
    </x-metronic.card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('item-rows'); const add = document.getElementById('add-row'); if (!container || !add) return;
    add.addEventListener('click', function () { const row = container.querySelector('.adjustment-row').cloneNode(true); const index = container.querySelectorAll('.adjustment-row').length; row.querySelectorAll('[name]').forEach(function(el){ el.name = el.name.replace(/items\[\d+\]/, 'items['+index+']'); if (el.tagName !== 'SELECT') el.value=''; }); container.appendChild(row); });
    container.addEventListener('click', function(e){ if(e.target.classList.contains('remove-row') && container.querySelectorAll('.adjustment-row').length > 1) e.target.closest('.adjustment-row').remove(); });
});
</script>
@endpush
