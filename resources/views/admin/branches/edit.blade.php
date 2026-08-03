@extends('layouts.metronic.app')

@section('title', 'Edit Cabang - ' . config('app.name'))
@section('page_title', 'Edit Cabang')
@section('page_guide')
    <x-metronic.page-guide id="admin-branch-edit" title="Panduan Edit Cabang">
        <x-slot:function><p>Form ini mengubah data cabang. Kode tidak bisa diubah jika sudah ada transaksi.</p></x-slot:function>
        <x-slot:parts><ul><li><strong>Kode:</strong> readonly jika ada transaksi.</li><li><strong>Field lain:</strong> dapat diedit.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Perubahan nama, kepala toko, dan konfigurasi berlaku pada transaksi baru.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa data.</li><li>Ubah field.</li><li>Simpan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0"><ul><li>Kode tidak dapat diubah jika ada transaksi.</li></ul></div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Edit Cabang/Toko" description="Perbarui cabang/toko. Kode dikunci setelah dipakai transaksi." />
    @include('admin.branches._form')
@endsection
