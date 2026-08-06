@extends('layouts.metronic.app')

@section('title', 'Histori HPP dan Harga Supplier - ' . config('app.name'))
@section('page_title', 'Histori HPP dan Harga Supplier')

@section('toolbar_actions')
    <a href="{{ route('pricing.hpp-history.export', request()->query()) }}" class="btn btn-light-success"><i class="ki-outline ki-file-down"></i> Unduh CSV</a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="pricing-hpp-history" title="Panduan Histori HPP">
        <x-slot:function><p>Menjelaskan perubahan biaya modal rata-rata produk setelah penerimaan barang. HPP bukan harga jual; HPP adalah biaya modal rata-rata produk.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih produk, supplier, atau rentang tanggal.</li><li>Periksa ringkasan perubahan terbaru.</li><li>Lihat tren kronologis.</li><li>Buka dokumen penerimaan untuk menelusuri sumber biaya.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Nilai Stok Lama:</strong> jumlah lama dikalikan HPP sebelum.</li><li><strong>Harga Barang Masuk:</strong> biaya barang yang diterima sebelum biaya alokasi.</li><li><strong>Biaya Dialokasikan:</strong> bagian ongkir dan biaya tambahan yang dibebankan ke item.</li><li><strong>HPP Sesudah:</strong> biaya modal rata-rata baru.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Histori terbentuk ketika penerimaan diposting dan tidak mengubah harga jual secara langsung.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Terapkan filter.</li><li>Bandingkan HPP terbaru dan sebelumnya.</li><li>Periksa komponen biaya.</li><li>Buka nomor penerimaan bila perlu audit.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0">Kenaikan HPP dapat memengaruhi margin, tetapi perubahan harga jual tetap mengikuti modul harga dan persetujuannya.</div></x-slot:warnings>
        <x-slot:example><p>Stok lama bernilai Rp1.000.000, barang dan biaya masuk Rp500.000, lalu total dibagi jumlah setelah penerimaan untuk memperoleh HPP baru.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Histori HPP dan Harga Supplier" description="HPP adalah biaya modal rata-rata produk, bukan harga jual." />

    <x-metronic.card class="mb-6">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">Produk</label><select name="product_id" class="form-select form-select-solid"><option value="">Semua produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select form-select-solid"><option value="">Semua supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Dari Tanggal</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><label class="form-label">Sampai Tanggal</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Terapkan Filter</button></div>
        </form>
    </x-metronic.card>

    @php
        $latest = $summary['latest'];
        $previous = $summary['previous'];
    @endphp
    <div class="row g-4 mb-6">
        <div class="col-6 col-xl-3"><x-metronic.card><div class="text-muted fs-7">HPP Terbaru</div><div class="fs-4 fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($latest?->hpp_after ?? 0) }}</div><div class="text-muted fs-8">{{ $latest?->product?->name ?: 'Belum ada data' }}</div></x-metronic.card></div>
        <div class="col-6 col-xl-3"><x-metronic.card><div class="text-muted fs-7">HPP Sebelumnya</div><div class="fs-4 fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($previous?->hpp_after ?? 0) }}</div><div class="text-muted fs-8">Rekaman sebelumnya pada filter aktif</div></x-metronic.card></div>
        <div class="col-6 col-xl-3"><x-metronic.card><div class="text-muted fs-7">Perubahan</div><div class="fs-4 fw-bold {{ \App\Support\Decimal::compare($summary['difference'], '0', 2) > 0 ? 'text-danger' : 'text-success' }}">{{ \App\Support\CurrencyFormatter::rupiah($summary['difference']) }}</div><div class="text-muted fs-8">{{ $summary['percentage'] }}%</div></x-metronic.card></div>
        <div class="col-6 col-xl-3"><x-metronic.card><div class="text-muted fs-7">Sumber Terakhir</div><div class="fw-bold">{{ $latest?->supplier?->name ?: '-' }}</div><div class="text-muted fs-8">{{ $latest?->goodsReceipt?->number ?: '-' }} · {{ $latest?->effective_at?->format('d/m/Y H:i') ?: '-' }}</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Tren HPP Berdasarkan Filter Aktif" class="mb-6">
        <div class="alert alert-light-info">Grafik menampilkan maksimal 40 perubahan terbaru dalam hasil filter aktif, lalu menyusunnya secara kronologis. Setiap produk mempunyai garis sendiri. Arahkan kursor ke titik untuk melihat produk, supplier, tanggal, dan HPP.</div>
        <div id="hpp-trend-chart" style="min-height: 320px"></div>
        @if($chartHistories->isEmpty())<x-metronic.empty-state title="Belum ada tren HPP" description="Ubah filter atau posting penerimaan barang terlebih dahulu." />@endif
    </x-metronic.card>

    <x-metronic.card title="Rincian Perubahan Biaya">
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk dan Sumber</th><th>Jumlah</th><th class="text-end">Nilai Stok Lama</th><th class="text-end">Harga Barang Masuk</th><th class="text-end">Biaya Dialokasikan</th><th class="text-end">Total Biaya Masuk</th><th class="text-end">HPP Sebelum → Sesudah</th><th>Tanggal</th></tr></thead><tbody>
            @forelse($histories as $history)
                @php
                    $oldValue = \App\Support\Decimal::mul((string) $history->qty_before, (string) $history->hpp_before);
                    $goodsCost = \App\Support\Decimal::sub((string) $history->incoming_cost, (string) $history->landed_cost_allocated, 2);
                @endphp
                <tr>
                    <td class="fw-bold">{{ $history->product?->sku }} — {{ $history->product?->name }}<div class="text-muted fs-8">{{ $history->supplier?->name ?: '-' }} · @if($history->goodsReceipt)<a href="{{ route('warehouse.goods-receipts.show', $history->goodsReceipt) }}">{{ $history->goodsReceipt->number }}</a>@else-@endif</div></td>
                    <td>{{ qty($history->qty_before) }} + {{ qty($history->qty_incoming) }} = <strong>{{ qty($history->qty_after) }}</strong></td>
                    <td class="text-end">{{ \App\Support\CurrencyFormatter::rupiah($oldValue) }}</td>
                    <td class="text-end">{{ \App\Support\CurrencyFormatter::rupiah($goodsCost) }}</td>
                    <td class="text-end">{{ \App\Support\CurrencyFormatter::rupiah($history->landed_cost_allocated) }}<div class="text-muted fs-8" data-bs-toggle="tooltip" title="Gabungan alokasi ongkir dan biaya tambahan pada item ini. Ongkir dokumen: {{ \App\Support\CurrencyFormatter::rupiah($history->goodsReceipt?->actual_freight_cost ?? 0) }}; biaya tambahan: {{ \App\Support\CurrencyFormatter::rupiah($history->goodsReceipt?->actual_additional_cost ?? 0) }}.">Ongkir + biaya tambahan</div></td>
                    <td class="text-end fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($history->incoming_cost) }}</td>
                    <td class="text-end">{{ \App\Support\CurrencyFormatter::rupiah($history->hpp_before) }} → <strong>{{ \App\Support\CurrencyFormatter::rupiah($history->hpp_after) }}</strong></td>
                    <td>{{ $history->effective_at?->format('d/m/Y H:i') }}</td>
                </tr>
            @empty<tr><td colspan="8"><x-metronic.empty-state title="Belum ada histori HPP" description="Histori terbentuk ketika barang yang diterima diposting." /></td></tr>@endforelse
        </tbody></table></div>
        {{ $histories->links() }}
    </x-metronic.card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const target = document.getElementById('hpp-trend-chart');
    const rows = @json($chartData);
    if (!target || rows.length === 0 || typeof window.ApexCharts === 'undefined') return;
    const escapeHtml = (value) => { const node = document.createElement('div'); node.textContent = String(value ?? '-'); return node.innerHTML; };
    const productNames = [...new Set(rows.map(row => row.product || 'Tanpa nama'))];
    const grouped = productNames.map(name => ({ name, data: rows.filter(row => row.product === name && row.x).map(row => ({...row, y: Number(row.y)})) }));
    if (rows.length === 1) target.insertAdjacentHTML('beforebegin', '<div class="alert alert-light-info py-3">Baru tersedia satu rekaman HPP. Titik akan membentuk tren setelah ada penerimaan berikutnya.</div>');
    new window.ApexCharts(target, {
        chart: { type: 'line', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
        series: grouped,
        xaxis: { type: 'datetime', labels: { datetimeUTC: false, format: 'dd MMM yy' } },
        stroke: { curve: 'straight', width: 3, connectNulls: false }, markers: { size: 5, hover: { size: 7 } },
        yaxis: { labels: { formatter: value => new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value) } },
        tooltip: { custom: ({ seriesIndex, dataPointIndex }) => { const row = grouped[seriesIndex]?.data[dataPointIndex] || {}; return `<div class="p-3"><strong>${escapeHtml(row.product)}</strong><div>Supplier: ${escapeHtml(row.supplier)}</div><div>Tanggal: ${escapeHtml(row.dateLabel)}</div><div>Receipt: ${escapeHtml(row.receipt)}</div><div class="text-success fw-bold">${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(row.y || 0))}</div></div>`; } },
        noData: { text: 'Belum ada data tren.' }, grid: { borderColor: '#eff2f5' },
    }).render();
});
</script>
@endpush
