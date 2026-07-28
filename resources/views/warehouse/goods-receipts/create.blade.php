@extends('layouts.metronic.app')

@section('title', 'Buat Penerimaan - ' . config('app.name'))
@section('page_title', 'Form Penerimaan dan QC')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-goods-receipt-create" title="Panduan Form Penerimaan Barang">
        <x-slot:function>
            <p>Form ini digunakan untuk membuat penerimaan barang dari Purchase Order yang sudah disetujui atau dikirim ke supplier. Pengguna memilih PO, mengisi qty datang, QC result, batch, lokasi, dan biaya.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pilih PO siap diterima.</li><li>Isi header: tanggal datang, surat jalan, biaya, bukti.</li><li>Isi item: qty datang, accepted, rejected, damaged, retur, lokasi, batch, catatan QC.</li><li>Simpan Draft atau langsung Posting.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Pilih PO:</strong> dropdown PO yang siap diterima.</li><li><strong>PO/Supplier/Gudang:</strong> info readonly dari PO.</li><li><strong>Tanggal Datang:</strong> hari barang diterima (wajib).</li><li><strong>Nomor Surat Jalan:</strong> nomor dokumen pengiriman supplier.</li><li><strong>Ongkir/Biaya Tambahan:</strong> biaya aktual delivery.</li><li><strong>Foto/Bukti:</strong> upload bukti penerimaan.</li><li><strong>Catatan:</strong> info tambahan receipt.</li><li><strong>Qty Datang:</strong> total barang datang per item.</li><li><strong>Accepted:</strong> qty lolos QC.</li><li><strong>Rejected:</strong> qty gagal QC.</li><li><strong>Damaged:</strong> qty rusak saat terima.</li><li><strong>Retur Supplier:</strong> qty dikembalikan ke supplier.</li><li><strong>Lokasi:</strong> bin penyimpanan per item.</li><li><strong>Batch:</strong> nomor batch/lot per item.</li><li><strong>Alasan QC:</strong> catatan QC per item.</li><li><strong>Simpan Draft:</strong> simpan tanpa posting.</li><li><strong>Simpan & Posting:</strong> simpan langsung posting.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Draft tidak mengubah stok. Posting menambah stok accepted, mencatat damaged/rejected, memperbarui PO outstanding, dan menghitung HPP baru.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pilih PO siap diterima dan klik Tampilkan.</li><li>Periksa header PO dan isi tanggal datang.</li><li>Isi biaya dan unggah bukti jika ada.</li><li>Untuk setiap item, isi qty datang dan pecah ke accepted/rejected/damaged.</li><li>Pilih lokasi dan batch.</li><li>Simpan Draft untuk review atau Simpan & Posting.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Total QC harus sama dengan qty datang.</li><li>Qty accepted tidak boleh melebihi outstanding PO.</li><li>Lokasi harus satu gudang dengan PO.</li><li>Jangan posting sebelum barang diperiksa fisik.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>PO-001 pesan 100 unit. Datang 100 unit: 95 accepted, 2 rejected, 3 damaged. Simpan Draft. Cek detail, lalu Posting.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    @include('warehouse.goods-receipts._form', [
        'action' => route('warehouse.goods-receipts.store'),
        'method' => 'POST',
    ])
@endsection
