@section('title', 'Variance Stok Opname - ' . config('app.name'))
@section('page_title', 'Variance Stok Opname')
@extends('layouts.metronic.app')

@section('content')
    <x-metronic.page-title :title="'Variance ' . $opname->number" description="Perbandingan saldo sistem dengan hasil fisik dan estimasi nilai selisih.">
        <x-slot:actions>
            <a href="{{ route('warehouse.stock-opnames.variance.export', $opname) }}" class="btn btn-light-success">Export CSV</a>
            <a href="{{ route('warehouse.stock-opnames.approval', $opname) }}" class="btn btn-primary">Approval</a>
        </x-slot:actions>
    </x-metronic.page-title>

    @if($opname->items->contains('has_transaction_after_snapshot', true))
        <div class="alert alert-warning">Ada transaksi setelah snapshot pada sebagian item. Review sebelum approval agar koreksi tidak menimpa transaksi valid.</div>
    @endif

    <div class="row g-4 mb-6">
        <div class="col-md-3"><x-metronic.card title="Total Selisih Qty"><div class="fs-2 fw-bold">{{ qty($opname->total_difference_qty) }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Nilai Selisih"><div class="fs-5 fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($opname->total_difference_value) }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Threshold Qty"><div class="fs-2 fw-bold">{{ qty($opname->threshold_qty) }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Approval Owner"><div class="fs-4 fw-bold">{{ $opname->requires_owner_approval ? 'Wajib' : 'Tidak' }}</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Daftar Selisih">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi</th><th class="text-end">Sistem</th><th class="text-end">Fisik</th><th class="text-end">Selisih</th><th class="text-end">Nilai</th><th>Alasan</th><th>Risiko</th></tr></thead>
                <tbody>
                @foreach($opname->items as $item)
                    @php
                        $absQty = abs((float) $item->difference_qty);
                        $risk = $item->has_transaction_after_snapshot ? 'Review transaksi' : ($absQty > (float) $opname->threshold_qty || (float) $item->estimated_value > (float) $opname->threshold_value ? 'Approval tinggi' : 'Normal');
                    @endphp
                    <tr>
                        <td class="fw-bold">{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td>
                        <td>{{ $item->warehouseLocation?->full_code ?: '-' }}</td>
                        <td class="text-end">{{ qty($item->system_qty_snapshot) }}</td>
                        <td class="text-end">{{ $item->counted_qty === null ? '-' : qty($item->counted_qty) }}</td>
                        <td class="text-end fw-bold">{{ qty($item->difference_qty) }}</td>
                        <td class="text-end">{{ \App\Support\CurrencyFormatter::rupiah($item->estimated_value) }}</td>
                        <td>{{ $item->reason?->label() ?: '-' }}<div class="text-muted fs-8">{{ $item->note }}</div></td>
                        <td><span class="badge badge-light-{{ $risk === 'Normal' ? 'success' : 'warning' }}">{{ $risk }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-metronic.card>
@endsection
