@extends('layouts.metronic.app')

@section('title', 'Edit Penerimaan - ' . config('app.name'))
@section('page_title', 'Edit Penerimaan Draft')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-goods-receipt-edit" title="Panduan Edit Penerimaan Barang Draft">
        <x-slot:function>
            <p>Form ini digunakan untuk memperbaiki receipt berstatus draft. Sudah diposting tidak dapat diedit dari sini; gunakan dokumen koreksi/reversal resmi.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Header PO tetap readonly.</li><li>Ubah qty, QC, lokasi, batch, atau catatan sesuai kebutuhan.</li><li>Simpan Draft atau Posting.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Header Receipt:</strong> informasi awal yang dapat diedit.</li><li><strong>Item:</strong> qty, QC, lokasi, batch per baris.</li><li><strong>Simpan Draft:</strong> menyimpan perubahan tanpa posting.</li><li><strong>Simpan & Posting:</strong> menyimpan dan langsung posting.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Hanya draft yang dapat diedit. Posting mengunci receipt dan memperbarui stok, PO, dan HPP.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Periksa data receipt.</li><li>Ubah nilai yang perlu dikoreksi.</li><li>Klik <strong>Simpan Draft</strong> atau <strong>Simpan & Posting</strong>.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Receipt posted tidak dapat diedit dari halaman ini.</li><li>Hormati validation aturan QC dan outstanding PO.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Salah input batch pada Kopi Robusta. Buka Edit, perbaiki batch, Simpan Draft, verifikasi, lalu Posting.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    @include('warehouse.goods-receipts._form', [
        'action' => route('warehouse.goods-receipts.update', $receipt),
        'method' => 'PUT',
    ])
@endsection
