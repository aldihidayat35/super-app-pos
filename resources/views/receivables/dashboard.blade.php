@extends('layouts.metronic.app')

@section('title', 'Dashboard Piutang')
@section('page_title', 'Dashboard Piutang')

@section('content')
    <x-metronic.page-title title="Dashboard Piutang" description="Ringkasan piutang gudang B2B dan toko internal.">
        <a href="{{ route('receivables.payments.create') }}" class="btn btn-primary">Input Pembayaran</a>
    </x-metronic.page-title>

    <div class="row g-5 mb-5">
        @foreach ([
            ['label' => 'Total Outstanding', 'value' => $total, 'class' => 'primary'],
            ['label' => 'Belum Jatuh Tempo', 'value' => $notDue, 'class' => 'success'],
            ['label' => 'Overdue', 'value' => $overdue, 'class' => 'danger'],
            ['label' => 'Jatuh Tempo Hari Ini', 'value' => $todayDue, 'class' => 'warning'],
        ] as $card)
            <div class="col-md-3">
                <x-metronic.card>
                    <div class="text-muted">{{ $card['label'] }}</div>
                    <div class="fs-2 fw-bold text-{{ $card['class'] }}">{{ App\Support\CurrencyFormatter::rupiah($card['value']) }}</div>
                </x-metronic.card>
            </div>
        @endforeach
    </div>

    <div class="row g-5">
        <div class="col-lg-7">
            <x-metronic.card title="Aging Piutang">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Bucket</th><th class="text-end">Outstanding</th></tr></thead>
                        <tbody>
                            @foreach (['not_due' => 'Belum jatuh tempo', '1_7' => '1-7 hari', '8_30' => '8-30 hari', '31_60' => '31-60 hari', 'over_60' => '> 60 hari'] as $bucket => $label)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-end fw-bold">{{ App\Support\CurrencyFormatter::rupiah($aging[$bucket] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-5">
            <x-metronic.card title="Kontrol Kredit">
                <div class="d-flex justify-content-between border-bottom py-3">
                    <span>Piutang Gudang/B2B</span>
                    <span class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($warehouseTotal) }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-3">
                    <span>Piutang Toko Internal</span>
                    <span class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($retailTotal) }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-3">
                    <span>Pembayaran Hari Ini</span>
                    <span class="fw-bold text-success">{{ App\Support\CurrencyFormatter::rupiah($paidToday) }}</span>
                </div>
                <div class="d-flex justify-content-between py-3">
                    <span>Pelanggan Lewat Limit</span>
                    <span class="badge badge-light-danger">{{ $overLimitCustomers }}</span>
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
