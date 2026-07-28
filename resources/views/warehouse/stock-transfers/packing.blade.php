@extends('layouts.metronic.app')

@section('title', 'Packing Transfer - ' . config('app.name'))
@section('page_title', 'Picking dan Packing')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-transfer-packing" title="Panduan Halaman Packing Transfer">
        <x-slot:function>
            <p>Halaman ini digunakan untuk proses picking dan packing transfer stok yang sudah di-approved. Staff Gudang mengisi qty picked per item, membuat nomor paket, dan mengunggah foto bukti packing sebelum transfer siap dikirim.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Sistem menampilkan daftar item transfer dengan qty approved.</li><li>Pengambil mengisi qty picked sesuai barang yang tersedia.</li><li>Pengemas membuat nomor paket dan mengunggah bukti.</li><li>Simpan packing untuk melanjutkan ke proses pengiriman.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Nomor Paket:</strong> identifikasi paket (otomatis).</li><li><strong>Foto/Bukti Packing:</strong> unggah foto barang saat dipacking.</li><li><strong>Catatan Paket:</strong> keterangan tambahan paket.</li><li><strong>Produk:</strong> daftar item transfer.</li><li><strong>Lokasi Ambil:</strong> bin tempat barang diambil.</li><li><strong>Approved:</strong> qty yang sudah disetujui.</li><li><strong>Qty Picked:</strong> qty yang berhasil diambil (wajib).</li><li><strong>Short:</strong> selisih jika qty picked kurang dari approved.</li><li><strong>Catatan Item:</strong> keterangan khusus per item.</li><li><strong>Simpan Packing:</strong> menyimpan hasil picking dan packing.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Simpan packing mengubah status transfer dan mengurangi qty available sumber. Transfer siap untuk proses pengiriman setelah packing disimpan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Periksa qty approved per item.</li><li>Masukkan qty picked yang berhasil diambil.</li><li>Buat nomor paket atau gunakan yang otomatis.</li><li>Unggah foto bukti packing jika tersedia.</li><li>Tulis catatan paket jika diperlukan.</li><li>Klik <strong>Simpan Packing</strong>.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Qty picked tidak boleh melebihi qty approved.</li><li>Short akan otomatis terdeteksi jika picked kurang dari approved.</li><li>Bukti foto membantu proses klaim pengiriman.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Transfer TRF-001 approved 10 unit. Picked 8 unit karena stok hanya tersedia 8. Short = 2. Nomor paket PKG-123045. Foto diunggah sebagai bukti. Simpan untuk lanjut ke pengiriman.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.card title="{{ $transfer->number }}">
        <form method="POST" action="{{ route('warehouse.stock-transfers.pack', $transfer) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-4 mb-5">
                <div class="col-md-4"><label class="form-label">Nomor Paket</label><input name="package_no" value="PKG-{{ now()->format('His') }}" class="form-control form-control-solid"></div>
                <div class="col-md-4"><label class="form-label">Foto/Bukti Packing</label><input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-4"><label class="form-label">Catatan Paket</label><input name="package_notes" class="form-control form-control-solid"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi Ambil</th><th>Approved</th><th>Qty Picked</th><th>Short</th><th>Catatan</th></tr></thead>
                    <tbody>
                    @foreach($transfer->items as $item)
                        <tr>
                            <td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td>
                            <td>{{ $item->sourceWarehouseLocation?->full_code ?: '-' }}</td>
                            <td>{{ qty($item->quantity_approved) }}</td>
                            <td><input type="number" step="1" min="0" max="{{ qty_input($item->quantity_approved) }}" name="items[{{ $item->id }}][quantity_picked]" value="{{ old("items.$item->id.quantity_picked", qty_input($item->quantity_picked ?: $item->quantity_approved)) }}" class="form-control form-control-sm"></td>
                            <td>{{ qty($item->quantity_short) }}</td>
                            <td><input name="items[{{ $item->id }}][notes]" value="{{ $item->notes }}" class="form-control form-control-sm"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end"><button class="btn btn-primary">Simpan Packing</button></div>
        </form>
    </x-metronic.card>
@endsection
