@extends('layouts.metronic.app')

@section('title', 'Pembelian Darurat ' . $purchase->number)
@section('content')
    <x-metronic.page-title title="Pembelian Darurat {{ $purchase->number }}" description="{{ $purchase->branch?->name }} · {{ str_replace('_', ' ', ucfirst($purchase->status)) }}" />
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="d-flex flex-wrap gap-2 mb-5"><a class="btn btn-light" href="{{ route('retail.emergency.index', ['branch_id' => $purchase->branch_id]) }}">Daftar permintaan</a>
        @if($purchase->status === 'purchased' && auth()->user()->can('pos.create'))<a class="btn btn-primary" href="{{ route('retail.pos.index', ['emergency_purchase_id' => $purchase->id]) }}">Lanjutkan di POS</a>@endif
        @foreach($linkedSales as $linkedSale)<a class="btn btn-light" href="{{ route('retail.sales.show', $linkedSale) }}">POS {{ $linkedSale->number }}</a>@endforeach
    </div>
    <x-metronic.card title="Rincian dan alokasi">
        <p>Jenis: <strong>{{ $purchase->purpose === 'proactive_restock' ? 'Restok darurat toko' : 'Permintaan pelanggan' }}</strong> · Pemohon: {{ $purchase->requester?->name }} · Total biaya: <strong>{{ \App\Support\CurrencyFormatter::rupiah($purchase->total_cost) }}</strong></p>
        <div class="table-responsive"><table class="table table-row-bordered"><thead><tr><th>Produk</th><th>Diminta</th><th>Stok reguler awal</th><th>Kekurangan</th><th>Dibeli</th><th>Terpakai</th><th>Saldo darurat</th><th>Biaya/satuan dasar</th></tr></thead><tbody>
        @foreach($purchase->items as $item)<tr><td>{{ $item->product?->name }}</td><td>{{ qty($item->base_quantity) }}</td><td>{{ qty($item->available_at_request) }}</td><td>{{ $purchase->purpose === 'proactive_restock' ? '—' : qty($item->shortage_at_request) }}</td><td>{{ qty($item->purchased_quantity) }}</td><td>{{ qty($item->allocated_quantity) }}</td><td>{{ qty($itemBalances[$item->id] ?? 0) }}</td><td>{{ \App\Support\CurrencyFormatter::rupiah($item->unit_cost) }}</td></tr>@endforeach
        </tbody></table></div>
        @if($purchase->receipt_path)
            <div class="mt-5">
                <div class="fw-semibold mb-2">Nota pembelian</div>
                @if($receiptIsImage)
                    <a href="{{ route('retail.emergency.receipt-preview', $purchase) }}" target="_blank" rel="noopener noreferrer" aria-label="Buka nota pembelian di tab baru">
                        <img src="{{ route('retail.emergency.receipt-preview', $purchase) }}" class="emergency-receipt-preview img-fluid rounded border bg-light" alt="Nota pembelian {{ $purchase->number }}">
                    </a>
                    <div class="text-muted fs-8 mt-2">Klik gambar untuk membuka ukuran penuh di tab baru.</div>
                @endif
                <a class="btn btn-sm btn-light mt-3" href="{{ route('retail.emergency.receipt', $purchase) }}">Unduh {{ basename($purchase->receipt_path) }}</a>
            </div>
        @endif
        @if($purchase->purchaseOrder)<p>PO: {{ $purchase->purchaseOrder->number }} · Penerimaan: {{ $purchase->goodsReceipt?->number ?? 'menunggu posting gudang' }}</p>@endif
        @if($purchase->shiftExpense)<p>Kas toko: pengeluaran shift #{{ $purchase->shiftExpense->cash_shift_id }} tercatat sekali.</p>@endif
        @if($purchase->fund_source === 'personal')<p>Reimbursement: {{ $purchase->reimbursement_status }} · {{ \App\Support\CurrencyFormatter::rupiah($purchase->reimbursement_amount) }} @if($purchase->reimbursement_proof_path && auth()->user()->can('emergency_purchases.manage')) · <a href="{{ route('retail.emergency.reimbursement-proof', $purchase) }}">Bukti pembayaran</a>@endif</p>@endif
    </x-metronic.card>
    @if($purchase->status === 'pending_approval' && auth()->user()->can('emergency_purchases.approve'))
        <x-metronic.card title="Keputusan manajer"><div class="d-flex flex-wrap gap-3"><form method="POST" action="{{ route('retail.emergency.approve', $purchase) }}">@csrf<button class="btn btn-success">Setujui</button></form><form method="POST" action="{{ route('retail.emergency.reject', $purchase) }}">@csrf<input name="notes" required maxlength="1000" class="form-control mb-2" placeholder="Alasan penolakan"><button class="btn btn-danger">Tolak</button></form></div></x-metronic.card>
    @endif
    @if($purchase->status === 'approved' && auth()->user()->can('emergency_purchases.purchase'))
        <x-metronic.card title="Catat pembelian dan nota"><form method="POST" enctype="multipart/form-data" action="{{ route('retail.emergency.purchased', $purchase) }}" class="row g-3">@csrf
            <div class="col-md-6"><label class="form-label">Pemasok</label><input name="supplier_name" required class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Sumber dana</label><select name="fund_source" class="form-select"><option value="store_cash">Kas toko (shift aktif)</option><option value="personal">Uang pribadi, menunggu reimbursement</option></select></div>
            @foreach($purchase->items as $index => $item)@php($maximumPurchase = $purchase->purpose === 'proactive_restock' ? $item->base_quantity : $item->shortage_at_request)<div class="col-md-4"><label class="form-label">{{ $item->product?->name }} · Qty dibeli (maks. {{ qty($maximumPurchase) }})</label><input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}"><input name="items[{{ $index }}][purchased_quantity]" type="number" step="0.0001" min="0.0001" max="{{ $maximumPurchase }}" value="{{ $maximumPurchase }}" class="form-control" required></div><div class="col-md-4"><label class="form-label">Biaya per satuan dasar</label><input name="items[{{ $index }}][unit_cost]" type="number" step="0.01" min="0.01" value="{{ $item->unit_cost }}" class="form-control" required></div>@endforeach
            <div class="col-md-6"><label class="form-label">Nota (PDF/foto, maks. 5 MB)</label><input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp" required class="form-control"></div>
            <div class="col-12"><button class="btn btn-primary">Simpan pembelian</button></div>
        </form></x-metronic.card>
    @endif
    @if(in_array($purchase->status, ['approved','pending_approval','purchased']) && ((int)$purchase->requested_by === (int)auth()->id() || auth()->user()->can('emergency_purchases.manage')))
        <x-metronic.card title="Batalkan permintaan"><form method="POST" action="{{ route('retail.emergency.cancel', $purchase) }}" class="d-flex flex-wrap gap-2">@csrf<input name="reason" required maxlength="1000" class="form-control" placeholder="Alasan pembatalan"><button class="btn btn-warning">Catat pembatalan</button></form></x-metronic.card>
    @endif
    @can('emergency_purchases.manage')
        @if($purchase->status === 'unallocated')<x-metronic.card title="Pool stok darurat toko"><p>Saldo barang otomatis tersedia untuk POS toko ini setelah stok reguler habis. Biaya tetap mengikuti lot pembelian asal.</p>
            @can('emergency_purchases.manage')<form method="POST" action="{{ route('retail.emergency.regularize', $purchase) }}" class="mb-5">@csrf<button class="btn btn-success w-100" data-confirm="Saldo darurat yang tidak sedang dicadangkan akan dimasukkan ke stok reguler toko dan ikut memperbarui HPP. Lanjutkan?">Masukkan Saldo Bebas ke Stok Reguler Toko</button></form>@endcan
            @if($reallocationTargets->isNotEmpty())<form method="POST" action="{{ route('retail.emergency.reassign', $purchase) }}" class="d-flex flex-wrap gap-2 mb-4">@csrf<select name="target_id" required class="form-select"><option value="">Pilih permintaan tujuan</option>@foreach($reallocationTargets as $target)<option value="{{ $target->id }}">{{ $target->number }}</option>@endforeach</select><button class="btn btn-primary">Alokasikan ulang</button></form>@endif
            <form method="POST" action="{{ route('retail.emergency.supplier-return', $purchase) }}" class="row g-3 mb-4">@csrf
                <div class="col-md-6"><label class="form-label">Referensi retur/refund</label><input name="reference" required class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Nominal pengembalian</label><input name="refund_amount" type="number" step="0.01" min="0" required class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Penerima refund</label><select name="refund_recipient" class="form-select"><option value="store">Toko</option>@if($purchase->fund_source === 'personal' && $purchase->reimbursement_status === 'pending')<option value="purchaser">Pembeli dengan uang pribadi</option>@endif</select></div>
                <div class="col-md-4"><label class="form-label">Metode refund</label><select name="refund_method" class="form-select"><option value="bank_transfer">Transfer</option><option value="cash">Tunai</option></select></div>
                <div class="col-md-4"><label class="form-label">Shift penerima (jika tunai untuk toko)</label><select name="cash_shift_id" class="form-select"><option value="">Pilih shift</option>@foreach($openShifts as $shift)<option value="{{ $shift->id }}">{{ $shift->number }}</option>@endforeach</select></div>
                <div class="col-12"><button class="btn btn-warning">Retur ke pemasok</button></div>
            </form>
            <form method="POST" action="{{ route('retail.emergency.forward-warehouse', $purchase) }}" class="d-flex flex-wrap gap-2">@csrf<select name="purchase_order_id" required class="form-select"><option value="">Pilih PO yang disetujui</option>@foreach($availableOrders as $order)<option value="{{ $order->id }}">{{ $order->number }}</option>@endforeach</select><button class="btn btn-light-primary" @disabled($availableOrders->isEmpty())>Teruskan ke PO/gudang</button></form>@if($availableOrders->isEmpty())<p class="text-muted mt-2 mb-0">Belum ada PO yang dapat dipilih pada gudang utama toko ini.</p>@endif</x-metronic.card>@endif
        @if($purchase->status === 'warehouse_pending')<x-metronic.card title="Menunggu penerimaan gudang"><p>Pastikan PO {{ $purchase->purchaseOrder?->number }} diproses melalui penerimaan gudang yang ada. Barang belum masuk stok lewat permintaan ini.</p><form method="POST" action="{{ route('retail.emergency.complete-warehouse', $purchase) }}" class="d-flex flex-wrap gap-2">@csrf<select name="goods_receipt_id" required class="form-select"><option value="">Pilih penerimaan yang sudah posted</option>@foreach($postedReceipts as $receipt)<option value="{{ $receipt->id }}">{{ $receipt->number }}</option>@endforeach</select><button class="btn btn-primary" @disabled($postedReceipts->isEmpty())>Verifikasi penerimaan posted</button></form>@if($postedReceipts->isEmpty())<p class="text-muted mt-2 mb-0">Penerimaan untuk PO ini belum diposting.</p>@endif</x-metronic.card>@endif
        @if($purchase->reimbursement_status === 'pending')<x-metronic.card title="Pembayaran reimbursement"><form method="POST" enctype="multipart/form-data" action="{{ route('retail.emergency.reimburse', $purchase) }}" class="row g-3">@csrf<div class="col-md-6"><input name="reference" class="form-control" required placeholder="Referensi pembayaran"></div><div class="col-md-6"><input type="file" name="proof" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control"></div><div class="col-12"><button class="btn btn-primary">Tandai sudah dibayar</button></div></form></x-metronic.card>@endif
    @endcan
    <x-metronic.card title="Riwayat tindakan"><div class="table-responsive"><table class="table"><thead><tr><th>Waktu</th><th>Tindakan</th><th>Status</th><th>Catatan</th></tr></thead><tbody>@foreach($purchase->histories as $history)<tr><td>{{ $history->created_at?->format('d/m/Y H:i') }}</td><td>{{ str_replace('_', ' ', $history->action) }}</td><td>{{ $history->from_status }} → {{ $history->to_status }}</td><td>{{ $history->notes }}</td></tr>@endforeach</tbody></table></div></x-metronic.card>
@endsection

@push('styles')
    <style>
        .emergency-receipt-preview {
            display: block;
            width: 100%;
            max-width: 640px;
            max-height: 420px;
            object-fit: contain;
            cursor: zoom-in;
        }
    </style>
@endpush
