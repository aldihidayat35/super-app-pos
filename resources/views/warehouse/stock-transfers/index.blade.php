@extends('layouts.metronic.app')

@section('title', 'Transfer Stok - ' . config('app.name'))
@section('page_title', 'Daftar Transfer Stok')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-transfers" title="Panduan Halaman Daftar Transfer Stok">
        <x-slot:function>
            <p>Halaman ini memonitor semua transfer stok antar lokasi yang dibuat pengguna. Transfer stok memiliki alur kerja lengkap: pembuatan, approval, packing, shipping, dan penerimaan. Kepala Gudang dan Staff Gudang menggunakannya untuk memantau progress dan melanjutkan proses berikutnya.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Klik <strong>Buat Transfer</strong> untuk membuat transfer baru.</li><li>Isi sumber, tujuan, item, dan tanggal pada form.</li><li>Simpan draft atau submit untuk approval.</li><li>Setelah approved, lanjutkan ke Packing lalu Shipping.</li><li>Destinasi menerima transfer melalui halaman terima di cabang.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Sumber/Tujuan:</strong> menyaring berdasarkan gudang asal atau tujuan.</li><li><strong>Status:</strong> menyaring Draft, Approved, Packing, Shipped, Received, Cancelled.</li><li><strong>No:</strong> nomor dokumen transfer.</li><li><strong>Tanggal:</strong> tanggal transfer dibuat.</li><li><strong>Item:</strong> jumlah baris produk dalam transfer.</li><li><strong>Pengirim/Penerima:</strong> user yang menangani transfer.</li><li><strong>Detail:</strong> melihat rincian item, status, dan timeline.</li><li><strong>Print:</strong> mencetak surat jalan transfer.</li><li><strong>Buat Transfer:</strong> membuka form pembuatan transfer baru.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Transfer yang sudah shipping secara resmi mengeluarkan stok sumber dan memasukkan in-transit ke tujuan. Penerimaan di cabang mengubah status menjadi Received dan menambah stok tujuan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Gunakan filter sumber, tujuan, atau status.</li><li>Klik <strong>Filter</strong> untuk menerapkan filter.</li><li>Klik <strong>Detail</strong> untuk melihat rincian transfer.</li><li>Gunakan <strong>Print</strong> untuk mencetak surat jalan.</li><li>Klik <strong>Buat Transfer</strong> untuk memulai transfer baru.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Pastikan sumber dan tujuan berbeda.</li><li>Jangan kirim transfer sebelum proses packing selesai.</li><li>Status transfer yang sudah Received tidak dapat diubah.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Transfer TRF-001 dari Gudang Pusat ke Cabang Bogor dengan status Approved. Klik Detail untuk melihat item, kemudian lanjutkan ke Packing untuk mengisi qty yang diambil.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <x-metronic.permission-button permission="stock_transfers.create" :href="route('warehouse.stock-transfers.create')" icon="ki-outline ki-plus">Buat Transfer</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-6">
            <div class="col-md-3"><select name="source_work_location_id" class="form-select form-select-solid"><option value="">Semua sumber</option>@foreach($workLocations as $location)<option value="{{ $location->id }}" @selected(($filters['source_work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="destination_work_location_id" class="form-select form-select-solid"><option value="">Semua tujuan</option>@foreach($workLocations as $location)<option value="{{ $location->id }}" @selected(($filters['destination_work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>No</th><th>Sumber</th><th>Tujuan</th><th>Tanggal</th><th>Item</th><th>Status</th><th>Pengirim/Penerima</th><th></th></tr></thead>
                <tbody>
                @forelse($transfers as $transfer)
                    <tr>
                        <td class="fw-bold">{{ $transfer->number }}</td>
                        <td>{{ $transfer->sourceWorkLocation?->name }}</td>
                        <td>{{ $transfer->destinationWorkLocation?->name }}</td>
                        <td>{{ $transfer->transfer_date?->format('d/m/Y') }}</td>
                        <td>{{ $transfer->items->count() }} item</td>
                        <td><x-metronic.status-badge :status="$transfer->status" /></td>
                        <td>{{ $transfer->shipper?->name ?: '-' }} / {{ $transfer->receiver?->name ?: '-' }}</td>
                        <td class="text-end"><a href="{{ route('warehouse.stock-transfers.show', $transfer) }}" class="btn btn-sm btn-light-primary">Detail</a><a href="{{ route('warehouse.stock-transfers.print', $transfer) }}" class="btn btn-sm btn-light-success">Print</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada transfer stok" description="Transfer dari gudang ke toko/antar lokasi akan tampil di sini." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $transfers->links() }}
    </x-metronic.card>
@endsection
