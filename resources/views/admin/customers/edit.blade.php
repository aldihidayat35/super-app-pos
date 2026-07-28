@extends('layouts.metronic.app')
@section('title', 'Edit Pelanggan')
@section('page_title', 'Edit Pelanggan')

@section('page_guide')
    <x-metronic.page-guide id="admin-customer-edit" title="Panduan Edit Pelanggan">
        <x-slot:function><p>Form ini mengubah data pelanggan yang sudah ada.</p></x-slot:function>
        <x-slot:parts><ul><li>Semua field dapat diedit kecuali kode pelanggan.</li><li>Update limit dan termin memengaruhi order baru.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Pelanggan tetap dapat order selama akun tetap aktif dan verifikasi sudah lengkap.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa data.</li><li>Ubah field.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Pastikan limit kredit diperbarui sebelum order baru.</li></ul></div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection
@section('content') @include('admin.customers._form') @endsection
