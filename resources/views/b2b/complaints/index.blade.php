@extends('layouts.metronic.app')

@section('title', 'Komplain B2B')
@section('page_title', 'Komplain B2B')

@section('content')
    <x-metronic.page-title title="Komplain dan Retur B2B" description="Laporkan barang kurang, pecah, atau salah kirim." />
    <x-metronic.card title="Ajukan Komplain" class="mb-5">
        <form method="POST" action="{{ route('langganan.complaints.store') }}" enctype="multipart/form-data" class="row g-4">
            @csrf
            <div class="col-md-4"><label class="form-label">Order</label><select name="b2b_order_id" class="form-select"><option value="">Pilih order</option>@foreach($orders as $order)<option value="{{ $order->id }}">{{ $order->number }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Shipment</label><select name="shipment_id" class="form-select"><option value="">Pilih shipment</option>@foreach($shipments as $shipment)<option value="{{ $shipment->id }}">{{ $shipment->number }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Tipe</label><select name="type" class="form-select"><option value="kurang">Kurang</option><option value="pecah">Pecah</option><option value="salah_barang">Salah Barang</option><option value="lainnya">Lainnya</option></select></div>
            <div class="col-md-2"><label class="form-label">Qty</label><input name="quantity" type="number" step="0.0001" min="0" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Solusi</label><select name="requested_solution" class="form-select"><option value="diskusi">Diskusi</option><option value="kirim_pengganti">Kirim Pengganti</option><option value="refund">Refund</option><option value="credit_note">Credit Note</option></select></div>
            <div class="col-md-4"><label class="form-label">Bukti</label><input name="evidence" type="file" class="form-control"></div>
            <div class="col-12"><label class="form-label">Pesan</label><textarea name="message" class="form-control" required></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Kirim Komplain</button></div>
        </form>
    </x-metronic.card>
    <x-metronic.card title="Riwayat Komplain"><div class="table-responsive"><table class="table"><thead><tr><th>Nomor</th><th>Tipe</th><th>Status</th><th>Pesan</th><th>Tanggal</th></tr></thead><tbody>@forelse($complaints as $complaint)<tr><td class="fw-bold">{{ $complaint->number }}</td><td>{{ $complaint->type }}</td><td>{{ $complaint->status?->label() }}</td><td>{{ $complaint->message }}</td><td>{{ $complaint->created_at->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="5"><x-metronic.empty-state title="Belum ada komplain" description="Riwayat komplain akan tampil di sini." /></td></tr>@endforelse</tbody></table></div>{{ $complaints->links() }}</x-metronic.card>
@endsection
