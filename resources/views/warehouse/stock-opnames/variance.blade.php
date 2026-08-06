@extends('layouts.metronic.app')

@section('title', 'Selisih Stok Opname - ' . config('app.name'))
@section('page_title', 'Selisih Stok Opname')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-opname-variance" title="Panduan Selisih Stok">
        <x-slot:function><p>Membandingkan acuan stok sistem dengan hasil penghitungan fisik sebelum persetujuan diberikan.</p></x-slot:function>
        <x-slot:workflow><ol><li>Periksa kartu ringkasan.</li><li>Utamakan item berwarna peringatan.</li><li>Buka Kartu Stok atau detail perubahan stok bila ada transaksi setelah acuan.</li><li>Lanjutkan ke Persetujuan setelah data diyakini benar.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Stok Sistem:</strong> jumlah ketika acuan stok disimpan.</li><li><strong>Jumlah Fisik:</strong> hasil hitung lapangan.</li><li><strong>Selisih:</strong> jumlah fisik dikurangi stok sistem.</li><li><strong>Transaksi Setelah Acuan:</strong> perubahan stok yang terjadi setelah penghitungan dimulai.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Halaman ini hanya untuk pemeriksaan. Stok belum berubah sampai opname disetujui dan diselesaikan.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa seluruh item berselisih.</li><li>Periksa transaksi setelah acuan.</li><li>Unduh CSV bila diperlukan.</li><li>Klik Persetujuan untuk melanjutkan.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0">Jangan menyetujui koreksi sebelum transaksi sah setelah acuan stok selesai diperiksa.</div></x-slot:warnings>
        <x-slot:example><p>Stok sistem 100 dan jumlah fisik 97 berarti kurang 3. Jika ada penjualan setelah acuan, periksa Kartu Stok agar penjualan tersebut tidak ikut dikoreksi.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title :title="'Selisih Stok ' . $opname->number" description="Perbandingan acuan stok dengan hasil fisik dan nilai selisih berdasarkan HPP saat acuan dibuat.">
        <x-slot:actions>
            <a href="{{ route('warehouse.stock-opnames.variance.export', $opname) }}" class="btn btn-light-success"><i class="ki-outline ki-file-down"></i> Unduh CSV</a>
            <a href="{{ route('warehouse.stock-opnames.approval', $opname) }}" class="btn btn-primary">Persetujuan</a>
        </x-slot:actions>
    </x-metronic.page-title>

    @if($summary['after_reference'] > 0)
        <div class="alert alert-warning d-flex align-items-start gap-3"><i class="ki-outline ki-information-5 fs-2"></i><div><strong>Perlu pemeriksaan khusus.</strong><div>Ditemukan transaksi stok setelah opname dimulai pada beberapa produk. Periksa produk tersebut sebelum menyetujui hasil opname agar transaksi yang sah tidak ikut terkoreksi.</div></div></div>
    @else
        <div class="alert alert-success"><strong>Aman:</strong> tidak ditemukan transaksi stok setelah acuan pada cakupan opname ini.</div>
    @endif

    <div class="row g-4 mb-6">
        @foreach([['Belum Dihitung', $opname->items->whereNull('counted_qty')->count(), 'secondary'], ['Item Aman', $summary['matching'], 'success'], ['Item Berselisih', $summary['different'], 'warning'], ['Melewati Batas Toleransi', $summary['above_threshold'], 'danger']] as [$label, $value, $color])
            <div class="col-6 col-xl-3"><x-metronic.card><div class="text-muted fs-7">{{ $label }}</div><div class="fs-2 fw-bold text-{{ $color }}">{{ $value }}</div></x-metronic.card></div>
        @endforeach
    </div>

    <x-metronic.card title="Daftar Selisih dan Pemeriksaan">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi</th><th class="text-end">Stok Sistem</th><th class="text-end">Jumlah Fisik</th><th class="text-end">Selisih</th><th class="text-end">Nilai Selisih</th><th>Alasan</th><th>Transaksi Setelah Acuan Stok</th></tr></thead>
                <tbody>
                @foreach($opname->items as $item)
                    @php
                        $absQty = ltrim((string) $item->difference_qty, '-');
                        $absValue = ltrim((string) $item->estimated_value, '-');
                        $above = \App\Support\Decimal::compare($absQty, (string) $opname->threshold_qty) > 0 || \App\Support\Decimal::compare($absValue, (string) $opname->threshold_value, 2) > 0;
                        $mutation = $lastMutations->get($item->product_id.'|'.($item->warehouse_location_id ?? 'null'));
                    @endphp
                    <tr class="{{ $above || $item->has_transaction_after_snapshot ? 'table-warning' : '' }}">
                        <td class="fw-bold">{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div>@if($above)<span class="badge badge-light-danger mt-1">Melewati batas toleransi</span>@endif</td>
                        <td>{{ $item->warehouseLocation?->full_code ?: '-' }}</td>
                        <td class="text-end">{{ qty($item->system_qty_snapshot) }}</td>
                        <td class="text-end">{{ $item->counted_qty === null ? '-' : qty($item->counted_qty) }}</td>
                        <td class="text-end fw-bold">{{ $item->counted_qty === null ? 'Belum dihitung' : qty($item->difference_qty) }}</td>
                        <td class="text-end">{{ $item->counted_qty === null ? '-' : \App\Support\CurrencyFormatter::rupiah($item->estimated_value) }}</td>
                        <td>{{ $item->reason?->label() ?: '-' }}<div class="text-muted fs-8">{{ $item->note }}</div></td>
                        <td>
                            @if($item->has_transaction_after_snapshot)
                                <span class="badge badge-light-warning mb-2">Perlu diperiksa</span>
                                <div class="fs-8">Terakhir: {{ $mutation?->occurred_at?->format('d/m/Y H:i') ?: 'Waktu tidak tersedia' }}</div>
                                <div class="text-muted fs-8">Referensi: {{ $mutation?->reference_no ?: '-' }}</div>
                                <div class="d-flex gap-2 mt-2">
                                    <a class="btn btn-sm btn-light-primary" href="{{ route('warehouse.stock-card.index', ['product_id' => $item->product_id, 'work_location_id' => $opname->work_location_id, 'warehouse_location_id' => $item->warehouse_location_id]) }}">Kartu Stok</a>
                                    @if($mutation)<a class="btn btn-sm btn-light" href="{{ route('warehouse.stock-mutations.show', $mutation) }}">Detail</a>@endif
                                </div>
                            @else
                                <span class="badge badge-light-success">Tidak ada</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-metronic.card>
@endsection
