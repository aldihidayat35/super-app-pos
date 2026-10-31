@extends('layouts.metronic.app')

@section('title', 'Buat Pesanan ke Supplier (PO) - ' . config('app.name'))
@section('page_title', 'Form Purchase Order')

@section('page_guide')
    <x-metronic.page-guide id="purchasing-po-create" title="Panduan Pembuatan Purchase Order">
        <x-slot:function><p>Form Purchase Order digunakan untuk menerbitkan dokumen pemesanan resmi ke pihak supplier rekanan untuk pengadaan barang dagangan atau persediaan operasional.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih supplier rekanan dan lokasi tujuan pengiriman barang (Gudang atau Toko Cabang).</li><li>Tentukan tanggal pemesanan, estimasi tiba (ETA), dan termin pembayaran.</li><li>Tambahkan produk, kuantitas beli, harga satuan, serta diskon/pajak baris.</li><li>Simpan sebagai draft untuk ditinjau, atau langsung setujui jika Anda memiliki wewenang approval.</li></ol></x-slot:workflow>
        <x-slot:warnings><p>Pastikan harga satuan yang dimasukkan sudah sesuai dengan kesepakatan harga beli dari supplier. Harga ini akan menjadi acuan saat pemeriksaan penerimaan barang (Goods Receipt).</p></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <div class="card card-flush shadow-sm">
        <div class="card-header border-bottom border-gray-200 py-4">
            <div class="card-title">
                <div class="d-flex align-items-center">
                    <span class="symbol symbol-40px bg-light-primary me-3 d-flex align-items-center justify-content-center">
                        <i class="ki-outline ki-delivery-time fs-2 text-primary"></i>
                    </span>
                    <div>
                        <h2 class="fs-4 fw-bolder mb-0 text-gray-900">Form Purchase Order - Pesanan ke Supplier (PO)</h2>
                        <div class="text-muted fs-8">Buat dokumen pemesanan resmi kepada pemasok luar dengan kalkulasi otomatis.</div>
                    </div>
                </div>
            </div>
            <div class="card-toolbar">
                <span class="badge badge-light-primary fw-bold me-3">Draft Baru</span>
                <a href="{{ route('purchasing.purchase-orders.index') }}" class="btn btn-sm btn-light">
                    <i class="ki-outline ki-arrow-left fs-5 me-1"></i> Kembali ke Daftar PO
                </a>
            </div>
        </div>

        <div class="card-body p-6">
            <form method="POST" action="{{ route('purchasing.purchase-orders.store') }}" id="form-purchase-order">
                @include('purchasing.purchase-orders._form')
            </form>
        </div>
    </div>
@endsection
