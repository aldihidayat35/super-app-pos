@extends('layouts.metronic.app')

@section('title', 'Edit Lokasi Gudang - ' . config('app.name'))
@section('page_title', 'Edit Lokasi Gudang')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-location-edit" title="Panduan Edit Lokasi Gudang">
        <x-slot:function>
            <p>Form ini digunakan untuk mengedit data lokasi (zona, rak, atau bin) yang sudah ada. Edit tidak mengubah hierarki yang sudah terhubung dengan saldo atau mutasi.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Ubah kode, nama, tipe, capacity, jenis barang, atau status aktif.</li><li>Parent dan gudang tidak dapat diubah dari halaman ini.</li><li>Simpan perubahan.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Full Code:</strong> identitas hierarkis lokasi (readonly).</li><li><strong>Nama:</strong> nama lokasi (wajib).</li><li><strong>Kapasitas:</strong> batas muatan lokasi.</li><li><strong>Jenis Barang:</strong> jenis produk yang disimpan.</li><li><strong>Aktif:</strong> toggle lokasi aktif/nonaktif.</li><li><strong>Simpan:</strong> menyimpan perubahan.</li><li><strong>Batal:</strong> kembali ke daftar lokasi.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Perubahan nama dan kode akan terlihat pada halaman lain yang menampilkan lokasi. Nonaktifkan lokasi mencegah pemilihan untuk transaksi baru.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Periksa data lokasi saat ini.</li><li>Ubah field yang diperlukan.</li><li>Klik <strong>Simpan</strong>.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Perubahan full_code tidak dapat dilakukan langsung.</li><li>Nonaktifkan lokasi jika tidak lagi dipakai, bukan hapus.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Bin A-01 ingin diganti nama menjadi "Bin A-01 Kopi". Ubah nama, simpan.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.card title="{{ $location->full_code }}">
        <form method="POST" action="{{ route('warehouse.locations.update', $location) }}">
            @include('warehouse.locations._form', ['method' => 'PUT'])
        </form>
    </x-metronic.card>
@endsection
