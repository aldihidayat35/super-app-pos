@extends('layouts.metronic.app')

@section('title', 'Shift Aktif - '.config('app.name'))
@section('page_title', 'Shift Aktif')

@section('toolbar_actions')
    @if($shift)
        <a href="{{ route('retail.pos.index') }}" class="btn btn-primary"><i class="ki-outline ki-shop fs-4 me-1"></i>Lanjut ke POS</a>
    @endif
@endsection

@section('content')
    @if(!$shift)
        <x-metronic.card><x-metronic.empty-state title="Belum Ada Shift Aktif" description="Buka shift sebelum memulai transaksi POS." icon="ki-outline ki-time"><a href="{{ route('retail.shifts.open') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Buka Shift</a></x-metronic.empty-state></x-metronic.card>
    @else
        <x-metronic.card class="mb-6">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-5"><div><div class="text-muted fs-8">SHIFT AKTIF</div><h2 class="mb-1">{{ $shift->number }}</h2><div class="text-muted">Dimulai {{ $shift->opened_at?->format('d/m/Y H:i') }}</div></div><x-metronic.status-badge :status="$shift->status" /></div>
            <div class="row g-5 mt-2"><div class="col-md-3"><div class="text-muted fs-8">KASIR</div><strong>{{ $shift->cashier?->name }}</strong></div><div class="col-md-3"><div class="text-muted fs-8">CABANG</div><strong>{{ $shift->branch?->name }}</strong></div><div class="col-md-3"><div class="text-muted fs-8">MODAL AWAL</div><strong>Rp {{ number_format((float) $shift->opening_cash_amount, 0, ',', '.') }}</strong></div><div class="col-md-3"><div class="text-muted fs-8">KEHADIRAN</div><strong>{{ $shift->attendance ? 'Check-in terverifikasi' : 'Override supervisor' }}</strong></div></div>
        </x-metronic.card>
        <div class="row g-4 mb-6">
            @foreach([['label' => 'Jumlah Transaksi', 'value' => number_format((int) ($summary['sales_count'] ?? 0), 0, ',', '.'), 'money' => false], ['label' => 'Penjualan Tunai', 'value' => $summary['cash_sales'] ?? 0, 'money' => true], ['label' => 'Penjualan Non Tunai', 'value' => $summary['non_cash_sales'] ?? 0, 'money' => true], ['label' => 'Expected Cash', 'value' => $summary['expected_cash'] ?? 0, 'money' => true]] as $metric)
                <div class="col-sm-6 col-xl-3"><x-metronic.card><div class="text-muted fs-8">{{ $metric['label'] }}</div><div class="fs-2 fw-bold mt-2">{{ $metric['money'] ? 'Rp '.number_format((float) $metric['value'], 0, ',', '.') : $metric['value'] }}</div></x-metronic.card></div>
            @endforeach
        </div>
        <div class="d-flex flex-wrap gap-3"><a href="{{ route('retail.pos.index') }}" class="btn btn-primary btn-lg"><i class="ki-outline ki-shop fs-3 me-1"></i>Lanjut ke POS</a><a href="{{ route('retail.shifts.report', $shift) }}" class="btn btn-light-primary">Detail Shift</a><a href="{{ route('retail.shifts.expenses', $shift) }}" class="btn btn-light-warning">Pengeluaran</a>@can('close', $shift)<a href="{{ route('retail.shifts.close', $shift) }}" class="btn btn-light-danger">Tutup Shift</a>@endcan</div>
    @endif
@endsection
