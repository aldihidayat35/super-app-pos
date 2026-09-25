@extends('layouts.metronic.app')

@section('title', $document->number . ' - Pendataan Stok')
@section('page_title', 'Detail Pendataan ' . $document->number)

@section('content')
    @error('opening_stock')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @php
        $zeroCount = $document->items->filter(fn($item) => (float)$item->physical_qty === 0.0)->count();
        $increaseCount = $document->items->filter(fn($item) => (float)$item->difference_qty > 0)->count();
        $decreaseCount = $document->items->filter(fn($item) => (float)$item->difference_qty < 0)->count();
        $inventoryValue = $document->items->sum(fn($item) => (float)$item->physical_qty * (float)$item->unit_cost_snapshot);
        $isCreator = (int)$document->created_by === (int)auth()->id();
        $isHead = auth()->user()->hasAnyRole(['kepala_gudang','kepala_toko','super_admin']);
    @endphp

    <div class="row g-5 mb-6">
        <div class="col-lg-8"><x-metronic.card title="Informasi Pendataan">
            <div class="row g-4"><div class="col-md-3 text-muted">Lokasi<br><strong class="text-gray-800">{{ $document->workLocation?->name }}</strong></div><div class="col-md-3 text-muted">Pembuat<br><strong class="text-gray-800">{{ $document->creator?->name }}</strong></div><div class="col-md-3 text-muted">Status<br><span class="badge badge-light-primary">{{ ['draft'=>'Draft','pending_approval'=>'Menunggu Persetujuan','completed'=>'Selesai','rejected'=>'Ditolak','cancelled'=>'Dibatalkan'][$document->status] ?? $document->status }}</span></div><div class="col-md-3 text-muted">Diposting oleh<br><strong class="text-gray-800">{{ $document->approver?->name ?? '-' }}</strong></div></div>
            @if($document->notes)<div class="mt-4"><span class="text-muted">Catatan:</span> {{ $document->notes }}</div>@endif
            @if($document->decision_note)<div class="alert alert-light-warning mt-4 mb-0">{{ $document->decision_note }}</div>@endif
        </x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Akses Lokasi">
            @if($access?->isCurrentlyActive())<span class="badge badge-light-success">Aktif</span><p class="mt-3 mb-0">Sampai {{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>@else<span class="badge badge-light-danger">Terkunci</span><p class="text-muted mt-3 mb-0">Pengajuan dan posting tidak tersedia.</p>@endif
        </x-metronic.card></div>
    </div>

    <div class="row g-5 mb-6">
        <div class="col-md-3"><x-metronic.card title="Produk"><div class="fs-2 fw-bold">{{ $document->items->count() }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Jumlah Nol"><div class="fs-2 fw-bold">{{ $zeroCount }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Naik / Turun"><div class="fs-2 fw-bold"><span class="text-success">{{ $increaseCount }}</span> / <span class="text-danger">{{ $decreaseCount }}</span></div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Nilai Fisik"><div class="fs-4 fw-bold">Rp {{ number_format($inventoryValue, 0, ',', '.') }}</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Rincian Produk" class="mb-6">
        <div class="alert alert-info">Kolom jumlah fisik adalah saldo akhir. Sistem hanya memutasi sebesar selisihnya.</div>
        <div class="table-responsive"><table class="table table-row-bordered align-middle"><thead><tr><th>Produk</th><th>Rak</th><th class="text-end">Jumlah di Sistem</th><th class="text-end">Jumlah Fisik Sekarang</th><th class="text-end">Selisih</th><th class="text-end">HPP</th><th>Catatan</th></tr></thead><tbody>
            @foreach($document->items as $item)<tr><td><strong>{{ $item->product_sku_snapshot }}</strong><br>{{ $item->product_name_snapshot }} <small class="text-muted">{{ $item->product?->baseUnit?->symbol }}</small></td><td>{{ $item->warehouseLocation?->full_code }}</td><td class="text-end">{{ qty($item->system_qty_snapshot) }}</td><td class="text-end fw-bold">{{ qty($item->physical_qty) }}</td><td class="text-end {{ (float)$item->difference_qty < 0 ? 'text-danger' : ((float)$item->difference_qty > 0 ? 'text-success' : '') }}">{{ qty($item->difference_qty) }}</td><td class="text-end">Rp {{ number_format((float)$item->unit_cost_snapshot, 0, ',', '.') }}</td><td>{{ $item->note }}</td></tr>@endforeach
        </tbody></table></div>
    </x-metronic.card>

    @if($document->status === 'draft' && $isCreator && $access?->isCurrentlyActive())
        <x-metronic.card title="Perbarui Draft" class="mb-6">
            <form method="POST" action="{{ route('warehouse.opening-stocks.update', $document) }}">@csrf @method('PUT')
                <input type="hidden" name="work_location_id" value="{{ $document->work_location_id }}">
                @foreach($document->items as $index => $item)
                    <div class="row g-3 mb-3"><div class="col-md-3"><label class="form-label">Produk</label><input class="form-control" value="{{ $item->product_sku_snapshot }} - {{ $item->product_name_snapshot }}" disabled><input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item->product_id }}"></div><div class="col-md-3"><label class="form-label">Rak</label><select name="items[{{ $index }}][warehouse_location_id]" class="form-select" required>@foreach($bins as $bin)<option value="{{ $bin->id }}" @selected($item->warehouse_location_id === $bin->id)>{{ $bin->full_code }} - {{ $bin->name }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Jumlah fisik</label><input type="number" step="0.0001" min="0" name="items[{{ $index }}][physical_qty]" value="{{ $item->physical_qty }}" class="form-control" required></div><div class="col-md-4"><label class="form-label">Catatan</label><input name="items[{{ $index }}][note]" value="{{ $item->note }}" class="form-control"></div></div>
                @endforeach
                <textarea name="notes" class="form-control" rows="2">{{ $document->notes }}</textarea><button class="btn btn-light-primary mt-3">Simpan Perubahan</button>
            </form>
        </x-metronic.card>
        <form method="POST" action="{{ route('warehouse.opening-stocks.submit', $document) }}" class="d-inline">@csrf<button class="btn btn-primary">Ajukan Pendataan</button></form>
    @endif

    @if($document->status === 'pending_approval' && auth()->user()->can('opening_stocks.approve') && $isHead && $access?->isCurrentlyActive())
        <x-metronic.card title="Posting Saldo Stok" class="mt-6">
            @if($isCreator)<div class="alert alert-warning">Anda membuat dokumen ini sebagai kepala lokasi. Sesuai pengaturan, Anda dapat mempostingnya sendiri dan tindakan dicatat sebagai audit berisiko tinggi.</div>@else<div class="alert alert-warning">Periksa jumlah fisik dan rak sebelum memposting. Stok berubah segera setelah proses berhasil.</div>@endif
            <div class="row g-4"><div class="col-md-6"><form method="POST" action="{{ route('warehouse.opening-stocks.approve', $document) }}">@csrf<textarea name="notes" class="form-control" rows="3" placeholder="Catatan pemeriksaan atau posting" required></textarea><button class="btn btn-success mt-3">Posting sebagai Saldo Akhir</button></form></div>@if(!$isCreator)<div class="col-md-6"><form method="POST" action="{{ route('warehouse.opening-stocks.reject', $document) }}">@csrf<textarea name="notes" class="form-control" rows="3" placeholder="Alasan penolakan" required></textarea><button class="btn btn-danger mt-3">Tolak Pendataan</button></form></div>@endif</div>
        </x-metronic.card>
    @endif

    @if(in_array($document->status, ['draft','pending_approval'], true) && ($isCreator || auth()->user()->hasRole('super_admin')))
        <form method="POST" action="{{ route('warehouse.opening-stocks.cancel', $document) }}" class="mt-6">@csrf<input name="notes" class="form-control d-inline-block w-50" minlength="5" placeholder="Alasan pembatalan" required><button class="btn btn-light-danger">Batalkan Dokumen</button></form>
    @endif

    @if($document->approvals->isNotEmpty())
        <x-metronic.card title="Riwayat Keputusan" class="mt-6"><ul class="mb-0">@foreach($document->approvals as $approval)<li>{{ $approval->decided_at?->format('d/m/Y H:i') }} — {{ $approval->approver?->name }} — {{ ucfirst($approval->decision) }}: {{ $approval->notes }}</li>@endforeach</ul></x-metronic.card>
    @endif
@endsection
