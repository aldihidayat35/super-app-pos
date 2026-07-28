@section('title', 'Laporan Stok Opname - ' . config('app.name'))
@extends('layouts.metronic.app')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-opname-report" title="Panduan Halaman Laporan Stok Opname">
        <x-slot:function>
            <p>Halaman ini menampilkan berita acara stok opname sebagai laporan resmi yang bisa dicetak. Memuat informasi scope, progress, selisih, detail item, dan area tanda tangan PIC, approver, dan Owner/Audit.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Laporan otomatis terbuat setelah opname selesai.</li><li>Informasi disajikan dalam format berita acara resmi.</li><li>Klik Print untuk mencetak atau menyimpan PDF.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Status Badge:</strong> status opname.</li><li><strong>Gudang/Cabang:</strong> lokasi opname.</li><li><strong>Scope:</strong> zona/bin/kategori.</li><li><strong>Periode:</strong> tanggal dan snapshot.</li><li><strong>Item:</strong> jumlah item dalam opname.</li><li><strong>Progress:</strong> item sudah di-counting.</li><li><strong>Selisih Qty/Nilai:</strong> total selisih.</li><li><strong>Tabel Item:</strong> sistem vs fisik, selisih, alasan.</li><li><strong>Tanda Tangan:</strong> area tanda tangan PIC, Approver, Owner.</li><li><strong>Print:</strong> mencetak laporan.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Laporan tidak mengubah data. Ini adalah dokumen resmi yang dapat digunakan untuk arsip dan audit.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Periksa seluruh informasi pada laporan.</li><li>Verifikasi tabel item dan selisih.</li><li>Klik <strong>Print</strong> untuk mencetak.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Print hanya menampilkan konten laporan tanpa layout aplikasi.</li><li>Pastikan opname sudah approved sebelum mencetak.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>SNAP-001 opname Gudang Pusat, 150 item, progress 100%. Selisih qty -5, nilai Rp 250.000. Tanda tangan PIC Budi, Approver Kepala Gudang.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <style>@media print {.aside,.header,.toolbar,.btn{display:none!important}.card{border:0!important;box-shadow:none!important}}</style>
    <x-metronic.page-title :title="'Laporan ' . $opname->number" description="Berita acara stok opname dan koreksi stok.">
        <x-slot:actions><button onclick="window.print()" class="btn btn-primary">Print</button></x-slot:actions>
    </x-metronic.page-title>

    <x-metronic.card>
        <div class="d-flex justify-content-between mb-8">
            <div><h2 class="mb-1">Berita Acara Stok Opname</h2><div class="text-muted">{{ $opname->number }}</div></div>
            <x-metronic.status-badge :status="$opname->status" />
        </div>
        <div class="row mb-8">
            <div class="col-md-4"><div class="text-muted">Gudang/Cabang</div><div class="fw-bold">{{ $opname->workLocation?->name }}</div></div>
            <div class="col-md-4"><div class="text-muted">Scope</div><div>{{ $opname->warehouseLocation?->full_code ?: 'Semua bin' }} / {{ $opname->category?->name ?: 'Semua kategori' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Periode</div><div>{{ $opname->scheduled_at?->format('d/m/Y') }} · Snapshot {{ $opname->started_at?->format('d/m/Y H:i') ?: '-' }}</div></div>
        </div>
        <div class="row mb-8">
            <div class="col-md-3"><div class="border rounded p-4"><div class="text-muted">Item</div><div class="fs-2 fw-bold">{{ $opname->items->count() }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-4"><div class="text-muted">Progress</div><div class="fs-2 fw-bold">{{ $opname->countedProgress() }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-4"><div class="text-muted">Selisih Qty</div><div class="fs-2 fw-bold">{{ qty($opname->total_difference_qty) }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-4"><div class="text-muted">Nilai Selisih</div><div class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($opname->total_difference_value) }}</div></div></div>
        </div>
        <table class="table table-row-dashed">
            <thead><tr class="fw-bold text-muted"><th>Produk</th><th>Lokasi</th><th class="text-end">Sistem</th><th class="text-end">Fisik</th><th class="text-end">Selisih</th><th>Alasan</th></tr></thead>
            <tbody>
                @foreach($opname->items->sortByDesc(fn($item) => abs((float) $item->difference_qty))->take(50) as $item)
                    <tr><td>{{ $item->product_sku_snapshot }} — {{ $item->product_name_snapshot }}</td><td>{{ $item->warehouseLocation?->full_code ?: '-' }}</td><td class="text-end">{{ qty($item->system_qty_snapshot) }}</td><td class="text-end">{{ qty($item->counted_qty) }}</td><td class="text-end">{{ qty($item->difference_qty) }}</td><td>{{ $item->reason?->label() ?: '-' }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <div class="row mt-10">
            <div class="col-md-4 text-center"><div style="height:80px"></div><div class="border-top pt-2">PIC: {{ $opname->pic?->name ?: '-' }}</div></div>
            <div class="col-md-4 text-center"><div style="height:80px"></div><div class="border-top pt-2">Approver: {{ $opname->approver?->name ?: '-' }}</div></div>
            <div class="col-md-4 text-center"><div style="height:80px"></div><div class="border-top pt-2">Owner/Audit</div></div>
        </div>
    </x-metronic.card>
@endsection

