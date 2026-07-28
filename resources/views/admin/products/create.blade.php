@extends('layouts.metronic.app')
@section('title', 'Tambah Produk')
@section('page_title', 'Tambah Produk')

@section('page_guide')
    <x-metronic.page-guide id="admin-product-create" title="Panduan Tambah Produk Baru">
        <x-slot:function><p>Form untuk menambah produk baru sebagai master data transaksi gudang dan retail.</p></x-slot:function>
        <x-slot:workflow><ol><li>Isi SKU, nama, dan kategori.</li><li>Pilih merek dan satuan.</li><li>Set minimum stock dan status.</li><li>Simpan produk.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>SKU:</strong> kode unik produk (wajib).</li><li><strong>Nama:</strong> nama produk (wajib).</li><li><strong>Kategori:</strong> grup produk (wajib).</li><li><strong>Merek:</strong> brand produk.</li><li><strong>Satuan:</strong> satuan ukuran.</li><li><strong>Min Stok:</strong> batas minimum stok.</li><li><strong>Status:</strong> aktif/nonaktif.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Produk baru dapat dipilih pada form penerimaan, penjualan, dan transfer.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Isi SKU, nama, kategori.</li><li>Set merek dan satuan.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>SKU harus unik.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>SKU KOF-ARA, "Kopi Arabika", kategori Kopi, merek Premium, satuan pcs, min stok 10.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content') @include('admin.products._form') @endsection
