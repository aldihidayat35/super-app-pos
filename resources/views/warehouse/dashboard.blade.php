@extends('layouts.metronic.app')

@php($kpis = $dashboard['kpis'])

@section('title', 'Dashboard Gudang - ' . config('app.name'))
@section('page_title', 'Dashboard Gudang')

@section('toolbar_actions')
    <x-metronic.permission-button permission="stock.create" :href="route('warehouse.location-transfers.index')" icon="ki-outline ki-arrow-right-left">Transfer Lokasi</x-metronic.permission-button>
@endsection

@section('content')
    <x-metronic.page-title title="Dashboard Gudang" description="DASH-02 stok, nilai, receipt/issue, PO/transfer/order pending, damaged, opname, dan workload.">
        <a href="{{ route('reports.warehouse.index', request()->query()) }}" class="btn btn-light-primary">Laporan Gudang</a>
    </x-metronic.page-title>

    @include('reports.partials.filter', ['filters' => $filters])

    @include('reports.partials.kpi-grid', ['items' => [
        ['label' => 'Stok Tersedia', 'value' => qty($kpis['available_quantity']), 'color' => 'primary', 'description' => 'On hand - reserved - rusak'],
        ['label' => 'Reserved', 'value' => qty($kpis['reserved_quantity']), 'color' => 'warning'],
        ['label' => 'Rusak', 'value' => qty($kpis['damaged_quantity']), 'color' => 'danger'],
        ['label' => 'Nilai Persediaan', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['stock_value']), 'color' => 'success'],
        ['label' => 'Stok Kritis', 'value' => $kpis['critical_count'], 'color' => 'danger'],
        ['label' => 'Stok Kosong', 'value' => $kpis['empty_count'], 'color' => 'danger'],
        ['label' => 'Masuk/Keluar', 'value' => $kpis['incoming_count'].' / '.$kpis['outgoing_count'], 'color' => 'info'],
        ['label' => 'Pending PO/Transfer', 'value' => $kpis['pending_po'].' / '.$kpis['pending_transfer'], 'color' => 'warning'],
    ]])

    <div class="row g-5 mb-5">
        <div class="col-lg-4">
            <x-metronic.card title="Dokumen Pending">
                <div class="d-flex justify-content-between mb-3"><span>Order B2B Pending</span><span class="fw-bold">{{ $kpis['pending_order'] }}</span></div>
                <div class="d-flex justify-content-between mb-3"><span>Receipt Posted</span><span class="fw-bold">{{ $kpis['posted_receipts'] }}</span></div>
                <div class="d-flex justify-content-between"><span>Opname Terbuka</span><span class="fw-bold">{{ $kpis['open_opname'] }}</span></div>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            @include('reports.partials.definitions', ['definitions' => $definitions])
        </div>
    </div>

    <x-metronic.card title="Mutasi Besar Terbaru">
        <div class="text-muted mb-4">Last updated: {{ $dashboard['last_updated_at']->format('d/m/Y H:i:s') }}</div>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Lokasi</th><th>Jenis</th><th>Perubahan</th></tr></thead>
                <tbody>
                @forelse ($dashboard['large_mutations'] as $mutation)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($mutation['occurred_at'])->format('d/m/Y H:i') }}</td>
                        <td>{{ $mutation['sku'] }} — {{ $mutation['product'] }}</td>
                        <td>{{ $mutation['location'] ?: '-' }}</td>
                        <td>{{ $mutation['mutation_type'] }}</td>
                        <td class="fw-bold">{{ qty($mutation['quantity_on_hand_change']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-metronic.empty-state title="Belum ada mutasi besar" description="Mutasi besar akan tampil setelah stok bergerak." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
@endsection
