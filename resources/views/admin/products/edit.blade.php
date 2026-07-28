@extends('layouts.metronic.app')
@section('title', 'Edit Produk')
@section('page_title', 'Edit Produk')

@section('page_guide')
    <x-metronic.page-guide id="admin-product-edit" title="Panduan Edit Produk">
        <x-slot:function><p>Form ini mengubah data produk. SKU tidak dapat diubah jika ada transaksi.</p></x-slot:function>
        <x-slot:parts><ul><li><strong>SKU:</strong> readonly jika ada transaksi.</li><li><strong>Lainnya:</strong> dapat diedit.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Perubahan nama dan kategori terlihat pada semua modul.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa data.</li><li>Ubah field.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>SKU tidak dapat diubah jika ada transaksi.</li></ul></div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection
@section('content') @include('admin.products._form') @endsection
