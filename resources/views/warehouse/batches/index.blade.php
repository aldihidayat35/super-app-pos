@extends('layouts.metronic.app')

@section('title', 'Batch/Lot Stok - ' . config('app.name'))
@section('page_title', 'Batch/Lot Stok')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-batches" title="Panduan Halaman Batch/Lot Stok">
        <x-slot:function>
            <p>Halaman ini menampilkan daftar batch atau lot stok yang tercatat. Batch mengidentifikasi kelompok produk dengan nomor produksi, tanggal masuk, dan tanggal kadaluarsa yang sama. Kepala Gudang dan Owner menggunakannya untuk melacak kedaluwarsa dan biaya per batch.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Gunakan kolom pencarian atau filter status untuk menemukan batch.</li><li>Sistem menampilkan detail batch meliputi produk, supplier, tanggal, HPP, qty, lokasi, dan status.</li><li>Status menentukan apakah batch masih aktif, expired, atau ditutup.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Cari batch/lot:</strong> mencari berdasarkan nomor batch.</li><li><strong>Filter Status:</strong> menyaring berdasarkan Aktif, Expired, atau Ditutup.</li><li><strong>Batch:</strong> nomor identifikasi batch.</li><li><strong>Produk:</strong> SKU dan nama produk.</li><li><strong>Supplier:</strong> pemasok batch.</li><li><strong>Tanggal Masuk:</strong> hari barang diterima.</li><li><strong>Expired:</strong> tanggal kadaluarsa.</li><li><strong>HPP Batch:</strong> harga per unit batch.</li><li><strong>Qty:</strong> on hand dan reserved per batch.</li><li><strong>Lokasi:</strong> penyimpanan batch.</li><li><strong>Status:</strong> Aktif, Expired, atau Ditutup.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Halaman ini hanya untuk monitoring. Filter dan pencarian tidak mengubah data batch. Batch diisi oleh proses penerimaan barang.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Masukkan nomor batch atau gunakan filter status.</li><li>Klik <strong>Filter</strong>.</li><li>Periksa tanggal expired untuk batch yang mendekati kadaluarsa.</li><li>Perhatikan qty yang masih ada dan lokasi penyimpanannya.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Batch Expired tidak boleh dikeluarkan untuk penjualan.</li><li>Qty per batch dapat berbeda jika produk tersebar di banyak lokasi.</li><li>Jangan menganggap HPP batch sebagai satu-satunya acuan; sistem menggunakan moving weighted average.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Batch BTH-001 memiliki 200 unit pada 15/06/2025 dan expired 15/06/2026. HPP per unit Rp 25.000. Tersisa 50 unit pada Rak A-01 dengan status Aktif.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-4"><input name="q" value="{{ $search }}" class="form-control form-control-solid" placeholder="Cari batch/lot"></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option><option value="active" @selected($status === 'active')>Aktif</option><option value="expired" @selected($status === 'expired')>Expired</option><option value="closed" @selected($status === 'closed')>Ditutup</option></select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Batch</th><th>Produk</th><th>Supplier</th><th>Tanggal Masuk</th><th>Expired</th><th>HPP Batch</th><th>Qty</th><th>Lokasi</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="fw-bold">{{ $batch->batch_no }}</td>
                        <td>{{ $batch->product?->sku }}<div class="text-muted">{{ $batch->product?->name }}</div></td>
                        <td>{{ $batch->supplier?->name ?: '-' }}</td>
                        <td>{{ $batch->received_at?->format('d/m/Y') ?: '-' }}</td>
                        <td>{{ $batch->expires_at?->format('d/m/Y') ?: '-' }}</td>
                        <td>Rp {{ number_format((float) $batch->cost_price, 0, ',', '.') }}</td>
                        <td>{{ qty($batch->quantity_on_hand) }}<div class="text-muted">Reserved: {{ qty($batch->quantity_reserved) }}</div></td>
                        <td>{{ $batch->stock?->warehouseLocation?->full_code ?: $batch->stock?->workLocation?->name ?: '-' }}</td>
                        <td><x-metronic.status-badge :status="$batch->status" :label="ucfirst($batch->status)" /></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-metronic.empty-state title="Belum ada batch/lot" description="Batch akan diisi oleh penerimaan barang pada fase berikutnya." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $batches->links() }}
    </x-metronic.card>
@endsection
