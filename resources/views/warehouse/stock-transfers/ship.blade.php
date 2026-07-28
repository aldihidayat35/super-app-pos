@extends('layouts.metronic.app')

@section('title', 'Kirim Transfer - ' . config('app.name'))
@section('page_title', 'Pengiriman Transfer')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-transfer-ship" title="Panduan Halaman Pengiriman Transfer">
        <x-slot:function>
            <p>Halaman ini mencatat detail pengiriman transfer stok ke tujuan. Setelah dikirim, stok sumber resmi keluar dan jumlah in-transit tercatat pada dokumen transfer hingga tujuan menerima barang.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pengguna melengkapi info kurir, kendaraan, resi, biaya kirim, dan bukti.</li><li>Setiap item daftar barang yang akan ditampilkan otomatis.</li><li>Klik Kirim Transfer untuk mengonfirmasi pengiriman.</li><li>Setelah itu, transfer berstatus Shipped menunggu penerimaan.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Kurir/Ekspedisi:</strong> nama ekspedisi pengiriman.</li><li><strong>Kendaraan:</strong> nomor plat atau jenis kendaraan.</li><li><strong>Resi:</strong> nomor resi/tracking.</li><li><strong>Biaya Kirim:</strong> ongkos kirim aktual.</li><li><strong>Bukti/Surat Jalan:</strong> file bukti pengiriman.</li><li><strong>Produk:</strong> daftar item yang akan dikirim.</li><li><strong>Approved/Picked/Akan Dikirim:</strong> ringkasan qty per item.</li><li><strong>Kirim Transfer:</strong> konfirmasi pengiriman transfer.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Klik Kirim akan resmi mengeluarkan stok sumber dan mencatat in-transit pada dokumen. Penerimaan di tujuan mengubah in-transit menjadi stok tujuan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Isi nama ekspedisi, nomor kendaraan, dan resi.</li><li>Masukkan biaya kirim jika ada.</li><li>Unggah bukti/surat jalan jika ada.</li><li>Periksa daftar item yang akan dikirim.</li><li>Klik <strong>Kirim Transfer</strong> untuk konfirmasi.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Pastikan item sudah dipilih dari proses packing dengan benar.</li><li>Biaya kirim mempengaruhi penghitungan biaya per item.</li><li>Pastikan bukti sudah diunggah untuk audit.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>TRF-001 dikirim dengan ekspedi SiCepat, kendaraan B 1234 ABC, resi SI00012345, biaya Rp 50.000. Bukti surat jalan diunggah. Klik Kirim Transfer.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.card title="{{ $transfer->number }}">
        <div class="alert alert-info">Saat dikirim, stok sumber resmi keluar dan quantity in-transit dicatat pada dokumen transfer.</div>
        <form method="POST" action="{{ route('warehouse.stock-transfers.ship', $transfer) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-4">
                <div class="col-md-4"><label class="form-label">Kurir/Ekspedisi</label><input name="carrier" class="form-control form-control-solid"></div>
                <div class="col-md-4"><label class="form-label">Kendaraan</label><input name="vehicle_number" class="form-control form-control-solid"></div>
                <div class="col-md-4"><label class="form-label">Resi</label><input name="tracking_number" class="form-control form-control-solid"></div>
                <div class="col-md-4"><label class="form-label">Biaya Kirim</label><input name="shipping_cost" type="number" step="0.01" min="0" class="form-control form-control-solid"></div>
                <div class="col-md-8"><label class="form-label">Bukti/Surat Jalan</label><input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
            </div>
            <div class="table-responsive mt-6">
                <table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Approved</th><th>Picked</th><th>Akan Dikirim</th></tr></thead><tbody>@foreach($transfer->items as $item)<tr><td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td><td>{{ qty($item->quantity_approved) }}</td><td>{{ qty($item->quantity_picked) }}</td><td class="fw-bold">{{ (float) $item->quantity_picked > 0 ? $item->quantity_picked : $item->quantity_approved }}</td></tr>@endforeach</tbody></table>
            </div>
            <div class="d-flex justify-content-end"><button class="btn btn-primary" data-confirm="Kirim transfer dan keluarkan stok sumber?">Kirim Transfer</button></div>
        </form>
    </x-metronic.card>
@endsection
