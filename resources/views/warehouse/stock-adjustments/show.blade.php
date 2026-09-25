@extends('layouts.metronic.app')

@section('title', $adjustment->number . ' - Koreksi Stok')
@section('page_title', 'Detail Koreksi ' . $adjustment->number)

@section('content')
    @error('adjustment')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div class="row g-6 mb-6">
        <div class="col-lg-8"><x-metronic.card title="Informasi Dokumen">
            <div class="row g-4"><div class="col-md-4 text-muted">Lokasi<br><strong class="text-gray-800">{{ $adjustment->workLocation?->name }}</strong></div><div class="col-md-4 text-muted">Pembuat<br><strong class="text-gray-800">{{ $adjustment->creator?->name }}</strong></div><div class="col-md-4 text-muted">Status<br><span class="badge badge-light-primary">{{ str_replace('_',' ',ucfirst($adjustment->status)) }}</span></div></div>
            @if($adjustment->notes)<div class="mt-4"><span class="text-muted">Catatan dokumen:</span> {{ $adjustment->notes }}</div>@endif
            @if($adjustment->decision_note)<div class="alert alert-light-warning mt-4 mb-0">{{ $adjustment->decision_note }}</div>@endif
        </x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Akses Lokasi">
            @if($access?->isCurrentlyActive())<span class="badge badge-light-success">Aktif</span><p class="mt-3 mb-0">Sampai {{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>@else<span class="badge badge-light-danger">Terkunci</span><p class="text-muted mt-3 mb-0">Pengajuan dan persetujuan tidak tersedia.</p>@endif
        </x-metronic.card></div>
    </div>

    <x-metronic.card title="Rincian Produk" class="mb-6">
        <div class="table-responsive"><table class="table table-row-bordered align-middle"><thead><tr><th>Produk</th><th>Lokasi</th><th class="text-end">Snapshot</th><th class="text-end">Fisik Final</th><th class="text-end">Selisih</th><th class="text-end">Nilai</th><th>Catatan</th></tr></thead><tbody>
            @foreach($adjustment->items as $item)<tr><td><strong>{{ $item->product_sku_snapshot }}</strong><br>{{ $item->product_name_snapshot }}</td><td>{{ $item->warehouseLocation?->full_code ?? 'Lokasi utama' }}</td><td class="text-end">{{ qty($item->system_qty_snapshot) }}</td><td class="text-end fw-bold">{{ qty($item->physical_qty) }}</td><td class="text-end {{ $item->difference_qty < 0 ? 'text-danger' : 'text-success' }}">{{ qty($item->difference_qty) }}</td><td class="text-end">Rp {{ number_format((float)$item->difference_value, 0, ',', '.') }}</td><td>{{ $item->note }}</td></tr>@endforeach
        </tbody></table></div>
    </x-metronic.card>

    @can('update', $adjustment)
        @if($access?->isCurrentlyActive())
            <x-metronic.card title="Perbarui Draft" class="mb-6">
                <form method="POST" action="{{ route('warehouse.stock-adjustments.update', $adjustment) }}">@csrf @method('PUT')
                    <input type="hidden" name="work_location_id" value="{{ $adjustment->work_location_id }}">
                    @foreach($adjustment->items as $index => $item)
                        <div class="row g-3 mb-3"><div class="col-md-4"><label class="form-label">Produk / stok</label><select name="items[{{ $index }}][stock_id]" class="form-select" required>@foreach($stocks as $stock)<option value="{{ $stock->id }}" @selected($item->stock_id === $stock->id)>{{ $stock->product?->sku }} — {{ $stock->product?->name }} ({{ qty($stock->quantity_on_hand) }})</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Stok fisik final</label><input type="number" step="0.0001" min="0" name="items[{{ $index }}][physical_qty]" value="{{ $item->physical_qty }}" class="form-control" required></div><div class="col-md-5"><label class="form-label">Catatan</label><input name="items[{{ $index }}][note]" value="{{ $item->note }}" class="form-control" required></div></div>
                    @endforeach
                    <textarea name="notes" class="form-control" rows="2">{{ $adjustment->notes }}</textarea><button class="btn btn-light-primary mt-3">Simpan Perubahan</button>
                </form>
            </x-metronic.card>
            <form method="POST" action="{{ route('warehouse.stock-adjustments.submit', $adjustment) }}" class="d-inline">@csrf<button class="btn btn-primary">Ajukan Persetujuan</button></form>
        @endif
    @endcan

    @can('decide', $adjustment)
        @if($access?->isCurrentlyActive())
            <x-metronic.card title="Keputusan Petugas Kedua" class="mt-6">
                <div class="alert alert-warning">Persetujuan langsung memposting koreksi seluruh item dalam satu transaksi.</div>
                <div class="row g-4"><div class="col-md-6"><form method="POST" action="{{ route('warehouse.stock-adjustments.approve', $adjustment) }}">@csrf<textarea name="notes" class="form-control" rows="3" placeholder="Alasan persetujuan" required></textarea><button class="btn btn-success mt-3">Setujui dan Perbarui Stok</button></form></div><div class="col-md-6"><form method="POST" action="{{ route('warehouse.stock-adjustments.reject', $adjustment) }}">@csrf<textarea name="notes" class="form-control" rows="3" placeholder="Alasan penolakan" required></textarea><button class="btn btn-danger mt-3">Tolak Koreksi</button></form></div></div>
            </x-metronic.card>
        @endif
    @endcan

    @if(in_array($adjustment->status, ['draft','pending_approval'], true) && ((int)$adjustment->created_by === (int)auth()->id() || auth()->user()->hasRole('super_admin')))
        <form method="POST" action="{{ route('warehouse.stock-adjustments.cancel', $adjustment) }}" class="mt-6">@csrf<input name="notes" class="form-control d-inline-block w-50" minlength="5" placeholder="Alasan pembatalan" required><button class="btn btn-light-danger">Batalkan Dokumen</button></form>
    @endif

    @if($adjustment->approvals->isNotEmpty())
        <x-metronic.card title="Riwayat Keputusan" class="mt-6"><ul class="mb-0">@foreach($adjustment->approvals as $approval)<li>{{ $approval->decided_at?->format('d/m/Y H:i') }} — {{ $approval->approver?->name }} — {{ ucfirst($approval->decision) }}: {{ $approval->notes }}</li>@endforeach</ul></x-metronic.card>
    @endif
@endsection
