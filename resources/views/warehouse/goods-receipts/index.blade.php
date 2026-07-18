@extends('layouts.metronic.app')

@section('title', 'Penerimaan Barang - ' . config('app.name'))
@section('page_title', 'Daftar Penerimaan Barang')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-goods-receipts" title="Panduan Halaman Daftar Penerimaan Barang">
        <x-slot:function>
            <p>Halaman ini mencatat dan memantau penerimaan barang berdasarkan Purchase Order yang sudah siap diterima. Kepala Gudang, Staff Gudang, Purchasing, dan Owner menggunakannya untuk melihat receipt, hasil quality control, serta status posting.</p>
            <p>Receipt draft dapat diperbaiki. Ketika diposting, sistem memproses stok diterima/rusak, outstanding PO, batch, histori biaya, dan HPP dalam transaksi database yang sama.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Klik <strong>Buat Receipt</strong> dan pilih PO berstatus approved, sent to supplier, atau partially received.</li><li>Isi tanggal, surat jalan, item outstanding, hasil QC, lokasi penyimpanan, biaya aktual, dan bukti.</li><li>Simpan sebagai draft untuk pemeriksaan atau posting jika data sudah final.</li><li>Saat posting, sistem mengunci dokumen terkait, mencegah penerimaan melebihi outstanding, dan menjalankan proses secara idempotent.</li><li>Accepted menambah stok layak pakai; damaged dicatat sebagai stok rusak; rejected/returned tidak menjadi stok tersedia.</li><li>Status dan qty diterima pada PO diperbarui menjadi parsial atau selesai sesuai hasil.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Supplier/Status/Tanggal:</strong> menyaring daftar penerimaan.</li><li><strong>No Receipt:</strong> nomor dokumen penerimaan yang dibuat sistem.</li><li><strong>PO:</strong> purchase order sumber dan batas outstanding.</li><li><strong>Gudang/Penerima:</strong> tempat dan pengguna penerima barang.</li><li><strong>QC:</strong> ringkasan accepted, rejected, dan damaged.</li><li><strong>Status:</strong> Draft masih dapat diedit; Posted sudah memengaruhi stok dan dikunci.</li><li><strong>Buat Receipt:</strong> membuka form penerimaan dari PO.</li><li><strong>Detail:</strong> melihat item, QC, mutasi, perhitungan HPP, dan audit.</li><li><strong>Edit:</strong> tersedia hanya untuk receipt yang masih dapat diubah.</li><li><strong>Export:</strong> mengunduh daftar sesuai filter aktif.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Menyimpan draft belum mengubah stok atau HPP. Posting menambah stok tepat sekali, mencatat mutasi dan QC, memperbarui penerimaan PO, batch/histori biaya, serta moving weighted average HPP. Receipt posted tidak dapat diedit atau dihapus; koreksi harus memakai proses koreksi/reversal yang disediakan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pastikan barang dan surat jalan cocok dengan PO.</li><li>Klik <strong>Buat Receipt</strong>, lalu pilih PO yang benar.</li><li>Masukkan qty datang dan pecah hasilnya ke accepted, rejected, damaged, atau returned sesuai kondisi nyata.</li><li>Pilih bin yang berada pada gudang PO dan isi batch bila diperlukan.</li><li>Unggah bukti yang valid serta isi biaya dan alasan QC.</li><li>Simpan draft, buka Detail, lalu periksa kembali seluruh ringkasan.</li><li>Posting hanya setelah qty, lokasi, QC, dan biaya telah disetujui.</li><li>Pastikan notifikasi berhasil dan cek mutasi/HPP pada Detail.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-danger mb-0"><ul><li>Jangan posting sebelum pemeriksaan fisik dan QC selesai.</li><li>Qty accepted tidak boleh melebihi outstanding PO.</li><li>Pastikan bin sesuai dengan gudang pada PO.</li><li>Jangan menekan posting berulang kali; sistem memiliki idempotensi, tetapi pengguna tetap harus menunggu hasil proses.</li><li>Kesalahan biaya aktual dapat memengaruhi landed cost dan HPP produk.</li><li>Receipt posted terkunci dan tidak boleh dihapus.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>PO memesan 100 unit dan belum pernah diterima. Gudang menerima 60 unit: 55 accepted, 3 rejected, dan 2 damaged. Setelah posting, PO tetap parsial; stok layak pakai bertambah 55, stok rusak tercatat 2, sementara sisa PO dihitung dari penerimaan yang diakui sistem.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('warehouse.goods-receipts.export', request()->query()) }}" class="btn btn-light-success"><i class="ki-outline ki-file-down"></i> Export</a>
    <x-metronic.permission-button permission="goods_receipts.create" :href="route('warehouse.goods-receipts.create')" icon="ki-outline ki-plus">Buat Receipt</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-6">
            <div class="col-md-3"><select name="supplier_id" class="form-select form-select-solid"><option value="">Semua supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>No Receipt</th><th>PO</th><th>Supplier</th><th>Gudang</th><th>Tanggal</th><th>Penerima</th><th>QC</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($receipts as $receipt)
                    <tr>
                        <td class="fw-bold">{{ $receipt->number }}</td>
                        <td>{{ $receipt->purchaseOrder?->number }}</td>
                        <td>{{ $receipt->supplier?->name }}</td>
                        <td>{{ $receipt->warehouse?->name }}</td>
                        <td>{{ $receipt->received_at?->format('d/m/Y') }}</td>
                        <td>{{ $receipt->receiver?->name }}</td>
                        <td><span class="text-success">{{ $receipt->acceptedQuantity() }}</span> / <span class="text-danger">{{ $receipt->rejectedQuantity() }}</span> / <span class="text-warning">{{ $receipt->damagedQuantity() }}</span></td>
                        <td><x-metronic.status-badge :status="$receipt->status" /></td>
                        <td class="text-end">
                            <a href="{{ route('warehouse.goods-receipts.show', $receipt) }}" class="btn btn-sm btn-light-primary">Detail</a>
                            @can('update', $receipt)<a href="{{ route('warehouse.goods-receipts.edit', $receipt) }}" class="btn btn-sm btn-light">Edit</a>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada penerimaan" description="Buat receipt dari PO yang sudah disetujui atau dikirim ke supplier." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $receipts->links() }}
    </x-metronic.card>
@endsection
