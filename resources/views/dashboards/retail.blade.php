@extends('layouts.metronic.app')

@php($kpis = $dashboard['kpis'])

@section('title', 'Dashboard Retail - ' . config('app.name'))
@section('page_title', 'Dashboard Retail')

@section('content')
    <x-metronic.page-title title="Dashboard Retail / Cabang" description="DASH-03 omzet, margin, transaksi, stok kritis, shift, closing, retur, void, dan pembayaran.">
        <a href="{{ route('reports.retail.index', request()->query()) }}" class="btn btn-light-primary">Laporan Toko</a>
    </x-metronic.page-title>

    @include('reports.partials.filter', ['filters' => $filters])

    @include('reports.partials.kpi-grid', ['items' => [
        ['label' => 'Omzet', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['revenue']), 'color' => 'primary'],
        ['label' => 'Margin', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['margin']).' / '.$kpis['margin_percent'].'%', 'color' => 'success'],
        ['label' => 'Transaksi', 'value' => $kpis['transaction_count'], 'color' => 'info'],
        ['label' => 'Rata-rata Nota', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['average_ticket']), 'color' => 'primary'],
        ['label' => 'Stok Kritis', 'value' => $kpis['critical_stock_count'], 'color' => 'danger'],
        ['label' => 'Shift Aktif', 'value' => $kpis['active_shift_count'], 'color' => 'warning'],
        ['label' => 'Closing Pending', 'value' => $kpis['closing_pending_count'], 'color' => 'warning'],
        ['label' => 'Selisih Kas', 'value' => \App\Support\CurrencyFormatter::rupiah($kpis['cash_difference']), 'color' => 'danger'],
    ]])

    <div class="row g-5">
        <div class="col-lg-7">
            <x-metronic.card title="Metode Pembayaran">
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Metode</th><th>Nilai</th></tr></thead><tbody>
                    @forelse($dashboard['charts']['payment_methods'] as $row)
                        <tr><td>{{ $row['label'] }}</td><td>{{ \App\Support\CurrencyFormatter::rupiah($row['value']) }}</td></tr>
                    @empty
                        <tr><td colspan="2"><x-metronic.empty-state title="Belum ada pembayaran" description="Metode pembayaran muncul setelah transaksi POS." /></td></tr>
                    @endforelse
                </tbody></table></div>
            </x-metronic.card>
        </div>
        <div class="col-lg-5">
            @include('reports.partials.definitions', ['definitions' => $definitions])
        </div>
    </div>
@endsection
