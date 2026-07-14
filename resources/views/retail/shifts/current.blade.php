@extends('layouts.metronic.app')

@section('title', 'Shift Aktif - ' . config('app.name'))
@section('page_title', 'Shift Aktif')

@section('toolbar_actions')
    @if($shift)
        <a href="{{ route('retail.pos.index') }}" class="btn btn-light-primary">Ke POS</a>
        <a href="{{ route('retail.shifts.expenses', $shift) }}" class="btn btn-light-warning">Pengeluaran</a>
        @can('close', $shift)<a href="{{ route('retail.shifts.close', $shift) }}" class="btn btn-primary">Tutup Shift</a>@endcan
    @else
        <a href="{{ route('retail.shifts.open') }}" class="btn btn-primary">Buka Shift</a>
    @endif
@endsection

@section('content')
    @if(!$shift)
        <x-metronic.card><x-metronic.empty-state title="Belum ada shift aktif" description="Buka shift sebelum melakukan transaksi POS." /></x-metronic.card>
    @else
        <div class="row g-6">
            @foreach([
                ['label' => 'Tunai', 'value' => $summary['cash_sales'] ?? '0.00'],
                ['label' => 'Non Tunai', 'value' => $summary['non_cash_sales'] ?? '0.00'],
                ['label' => 'Retur/Refund', 'value' => $summary['refunds'] ?? '0.00'],
                ['label' => 'Pengeluaran', 'value' => $summary['expenses'] ?? '0.00'],
                ['label' => 'Expected Cash', 'value' => $summary['expected_cash'] ?? '0.00'],
                ['label' => 'Transaksi', 'value' => $summary['sales_count'] ?? '0'],
            ] as $metric)
                <div class="col-md-4"><x-metronic.card><div class="text-muted">{{ $metric['label'] }}</div><div class="fs-3 fw-bold">{{ is_numeric($metric['value']) ? 'Rp '.number_format((float) $metric['value'], 0, ',', '.') : $metric['value'] }}</div></x-metronic.card></div>
            @endforeach
        </div>
        <x-metronic.card title="Detail Shift" class="mt-6">
            <div class="row">
                <div class="col-md-3"><div class="text-muted">Nomor</div><div class="fw-bold">{{ $shift->number }}</div></div>
                <div class="col-md-3"><div class="text-muted">Cabang</div>{{ $shift->branch?->name }}</div>
                <div class="col-md-3"><div class="text-muted">Kasir</div>{{ $shift->cashier?->name }}</div>
                <div class="col-md-3"><div class="text-muted">Status</div><x-metronic.status-badge :status="$shift->status" /></div>
            </div>
        </x-metronic.card>
    @endif
@endsection
