@extends('layouts.metronic.app')

@section('title', 'Edit Gudang - ' . config('app.name'))
@section('page_title', 'Edit Gudang')

@section('page_guide')
    <x-metronic.page-guide id="admin-warehouse-edit" title="Panduan Edit Gudang">
        <x-slot:function><p>Form ini mengubah data gudang. Kode tidak dapat diubah jika gudang sudah memiliki transaksi.</p></x-slot:function>
        <x-slot:workflow><ol><li>Ubah nama, kota, telepon, alamat, area layanan, kapasitas, dan kepala gudang.</li><li>Simpan perubahan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Kode Gudang:</strong> readonly jika ada transaksi.</li><li><strong>Field lainnya:</strong> dapat diedit.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Perubahan nama dan kepala gudang berlaku pada semua transaksi baru.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa data gudang.</li><li>Ubah field yang diperlukan.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Kode tidak dapat diubah jika ada transaksi.</li></ul></div></x-slot:warnings>
        <x-slot:example><p>Ubah nama gudang atau ganti kepala gudang.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Edit Gudang" description="Perbarui master gudang. Kode dikunci setelah dipakai transaksi." />
    @include('admin.warehouses._form')
@endsection
