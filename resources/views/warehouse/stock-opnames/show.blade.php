@extends('layouts.metronic.app')

@section('title', 'Detail Stok Opname - ' . config('app.name'))
@section('page_title', 'Detail Stok Opname')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-opname-show" title="Panduan Halaman Detail Stok Opname">
        <x-slot:function>
            <p>Halaman ini menampilkan ringkasan detail satu opname. Info mencakup dokumen, scope, status, progress, selisih, dan timeline perubahan status. Digunakan untuk memantau progress dan navigasi ke proses berikutnya.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Halaman menampilkan ringkasan dokumen opname.</li><li>Kartu ringkasan menampilkan item, progress, selisih qty dan nilai.</li><li>Timeline menampilkan histori perubahan status.</li><li>Navigasi ke Counting, Variance, dan Laporan tersedia pada tombol aksi.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Status:</strong> progress status opname.</li><li><strong>Gudang/Cabang:</strong> lokasi opname.</li><li><strong>Zona/Rak/Bin:</strong> scope lokasi detail.</li><li><strong>Kategori:</strong> filter kategori produk.</li><li><strong>PIC/Dibuat oleh:</strong> orang yang bertanggung jawab.</li><li><strong>Snapshot:</strong> waktu snapshot diambil.</li><li><strong>Catatan:</strong> keterangan opname.</li><li><strong>Item/Progress/Selisih Qty/Nilai:</strong> kartu ringkasan.</li><li><strong>Buat Snapshot:</strong> memulai snapshot dari draft.</li><li><strong>Counting/Variance/Laporan:</strong> navigasi halaman.</li><li><strong>Timeline:</strong> histori perubahan status.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Halaman ini read-only. Navigasi ke halaman lain akan melanjutkan proses opname.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Periksa ringkasan dokumen dan scope.</li><li>Monitor progress counting.</li><li>Periksa timeline perubahan status.</li><li>Navigasi ke halaman sesuai proses opname.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Snapshot hanya bisa dibuat dari status Draft.</li><li>Setelah counting dimulai, jangan mengubah scope.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>SNAP-001 status Counting. Progress 85%. Selisih qty -5, nilai Rp 250.000. Timeline: Draft → Snapshot → Counting. Klik Variance untuk lihat detail selisih.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title :title="$opname->number" description="Ringkasan status, scope, item, dan audit stok opname.">
        <x-slot:actions>
            @if($opname->status === \App\Enums\StockOpnameStatus::DRAFT)
                <form method="POST" action="{{ route('warehouse.stock-opnames.start', $opname) }}" class="d-inline">@csrf<button class="btn btn-primary">Simpan Acuan Stok</button></form>
            @endif
            @can('count', $opname)<a href="{{ route('warehouse.stock-opnames.count', $opname) }}" class="btn btn-light-primary">Penghitungan</a>@endcan
            <a href="{{ route('warehouse.stock-opnames.variance', $opname) }}" class="btn btn-light-info">Selisih Stok</a>
            <a href="{{ route('warehouse.stock-opnames.report', $opname) }}" class="btn btn-light-success">Laporan</a>
        </x-slot:actions>
    </x-metronic.page-title>

    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Dokumen">
                <div class="mb-3"><div class="text-muted">Status</div><x-metronic.status-badge :status="$opname->status" /></div>
                <div class="mb-3"><div class="text-muted">Gudang/Cabang</div><div class="fw-bold">{{ $opname->workLocation?->name }}</div></div>
                <div class="mb-3"><div class="text-muted">Zona/Rak/Bin</div><div>{{ $opname->warehouseLocation?->full_code ?: 'Semua lokasi detail' }}</div></div>
                <div class="mb-3"><div class="text-muted">Kategori</div><div>{{ $opname->category?->name ?: 'Semua kategori' }}</div></div>
                <div class="mb-3"><div class="text-muted">PIC (Penanggung Jawab) / Dibuat oleh</div><div>{{ $opname->pic?->name ?: '-' }} / {{ $opname->creator?->name ?: '-' }}</div></div>
                <div class="mb-3"><div class="text-muted">Acuan Stok <i class="ki-outline ki-information-5 fs-7" data-bs-toggle="tooltip" title="Waktu kondisi stok sistem disimpan sebagai angka pembanding awal opname."></i></div><div>{{ $opname->started_at?->format('d/m/Y H:i') ?: '-' }}</div></div>
                <div class="mb-3"><div class="text-muted">Catatan</div><div>{{ $opname->notes ?: '-' }}</div></div>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <div class="row g-4 mb-6">
                <div class="col-md-3"><x-metronic.card title="Jumlah Item"><div class="fs-2 fw-bold">{{ $opname->items->count() }}</div><div class="text-muted fs-8">Jumlah produk/lokasi dalam cakupan.</div></x-metronic.card></div>
                <div class="col-md-3"><x-metronic.card title="Kemajuan"><div class="fs-2 fw-bold">{{ $opname->countedProgress() }}</div><div class="text-muted fs-8">Item dihitung dibanding total item.</div></x-metronic.card></div>
                <div class="col-md-3"><x-metronic.card title="Kuantitas Fisik"><div class="fs-2 fw-bold">{{ qty($opname->items->reduce(fn($carry, $item) => \App\Support\Decimal::add($carry, (string)($item->counted_qty ?? 0)), '0.0000')) }}</div><div class="text-muted fs-8">Total hasil fisik yang sudah dimasukkan.</div></x-metronic.card></div>
                <div class="col-md-3"><x-metronic.card title="Nilai Selisih"><div class="fs-5 fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($opname->total_difference_value) }}</div><div class="text-muted fs-8">Nilai selisih berdasarkan HPP saat acuan dibuat.</div></x-metronic.card></div>
            </div>
            <x-metronic.card title="Timeline Status">
                <div class="timeline">
                    @forelse($opname->statusHistories as $history)
                        <div class="timeline-item">
                            <div class="timeline-line w-40px"></div>
                            <div class="timeline-icon symbol symbol-circle symbol-40px"><div class="symbol-label bg-light"><i class="ki-outline ki-check fs-3"></i></div></div>
                            <div class="timeline-content mb-6">
                                <div class="fw-bold">{{ $history->from_status ?: 'awal' }} → {{ $history->to_status }}</div>
                                <div class="text-muted">{{ $history->actor?->name ?: '-' }} · {{ $history->created_at?->format('d/m/Y H:i') }}</div>
                                <div>{{ $history->notes }}</div>
                            </div>
                        </div>
                    @empty
                        <x-metronic.empty-state title="Belum ada timeline" description="Timeline akan tercatat saat status berubah." />
                    @endforelse
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
