@extends('layouts.metronic.app')

@section('title', 'Bukti Pengiriman')
@section('page_title', 'Bukti Pengiriman')

@section('content')
    <x-metronic.page-title :title="$shipment->number" description="Upload foto/surat jalan/resi dan proof of delivery.">
        <a href="{{ route('shipments.show', $shipment) }}" class="btn btn-light">Kembali</a>
    </x-metronic.page-title>
    <x-metronic.card title="Form Proof">
        <form method="POST" action="{{ route('shipments.proof.store', $shipment) }}" enctype="multipart/form-data" class="row g-4">
            @csrf
            <div class="col-md-3"><label class="form-label">Tipe Bukti</label><select name="type" class="form-select"><option value="dispatch">Bukti Kirim</option><option value="delivery">Bukti Terima</option><option value="failed_delivery">Gagal Kirim</option></select></div>
            <div class="col-md-3"><label class="form-label">File</label><input name="proof" type="file" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Nama Penerima</label><input name="receiver_name" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Tanda Tangan Data</label><input name="signature_data" class="form-control" placeholder="Base64/signature ref opsional"></div>
            <div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" class="form-control"></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Simpan Proof</button></div>
        </form>
    </x-metronic.card>
@endsection
