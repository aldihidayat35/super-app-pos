@extends('layouts.metronic.app')
@section('title', 'Edit Supplier')
@section('page_title', 'Edit Supplier')

@section('page_guide')
    <x-metronic.page-guide id="admin-supplier-edit" title="Panduan Edit Supplier">
        <x-slot:function><p>Form ini mengubah data supplier. Kode tidak dapat diubah jika ada transaksi.</p></x-slot:function>
        <x-slot:parts><ul><li><strong>Kode:</strong> readonly jika ada transaksi.</li><li><strong>Lainnya:</strong> dapat diedit.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Perubahan kontak dan termin berlaku pada PO baru.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa data.</li><li>Ubah field.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Kode tidak dapat diubah jika ada transaksi.</li></ul></div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection
@section('content') @include('admin.suppliers._form') @endsection
