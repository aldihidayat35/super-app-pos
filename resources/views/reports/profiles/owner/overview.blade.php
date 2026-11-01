@extends('layouts.metronic.app')
@section('title', $currentReport['label'].' - '.config('app.name'))
@section('hide_toolbar', '1')
@section('content')
    @php
        $ownerSummary = $report['summary'] ?? [];
        $ownerChannels = collect(data_get($report, 'charts.channel_mix', []))->map(fn (array $row): array => [
            'label' => (string) ($row['label'] ?? '-'),
            'value' => \App\Support\CurrencyFormatter::rupiah((string) ($row['value'] ?? 0)),
            'numeric' => (float) ($row['value'] ?? 0),
        ])->all();
        $ownerStores = collect(data_get($report, 'sections.stores', []))->map(fn (array $row): array => [
            (string) ($row['name'] ?? '-'),
            \App\Support\CurrencyFormatter::rupiah((string) ($row['revenue'] ?? 0)),
            $canViewSensitive ? \App\Support\CurrencyFormatter::rupiah((string) ($row['margin'] ?? 0)) : 'Terbatas',
            (string) ($row['critical_count'] ?? 0),
        ])->all();
        $ownerReceivables = collect(data_get($report, 'sections.overdue_receivables', []))->map(fn (array $row): array => [
            (string) ($row['number'] ?? '-'),
            (string) ($row['customer'] ?? '-'),
            (string) ($row['location'] ?? '-'),
            \App\Support\CurrencyFormatter::rupiah((string) ($row['outstanding'] ?? 0)),
        ])->all();
        $dashboard = [
            'theme' => 'owner', 'icon' => 'ki-outline ki-chart-line-up-2', 'eyebrow' => 'EXECUTIVE BUSINESS REPORT',
            'title' => 'Laporan Owner', 'description' => 'Pandangan menyeluruh lintas toko, gudang, channel, piutang, dan risiko usaha.',
            'summary_title' => 'Ringkasan Eksekutif', 'summary_subtitle' => 'Seluruh lokasi yang diizinkan · keputusan berbasis periode aktif',
            'kpis' => [
                ['label' => 'Pendapatan', 'value' => \App\Support\CurrencyFormatter::rupiah((string) data_get($ownerSummary, 'revenue', 0)), 'note' => 'Retail dan B2B'],
                ['label' => 'Margin Kotor', 'value' => $canViewSensitive ? \App\Support\CurrencyFormatter::rupiah((string) data_get($ownerSummary, 'gross_margin', 0)) : 'Akses terbatas', 'note' => $canViewSensitive ? str_replace('.', ',', (string) data_get($ownerSummary, 'margin_percent', 0)).'%' : 'Izin diperlukan'],
                ['label' => 'Nilai Stok', 'value' => $canViewSensitive ? \App\Support\CurrencyFormatter::rupiah((string) data_get($ownerSummary, 'stock_value', 0)) : 'Akses terbatas', 'note' => 'HPP lapisan aktual'],
                ['label' => 'Piutang', 'value' => \App\Support\CurrencyFormatter::rupiah((string) data_get($ownerSummary, 'receivable_outstanding', 0)), 'note' => 'Outstanding aktif'],
            ],
            'panels' => [
                ['title' => 'Pendapatan per Channel', 'subtitle' => 'Komposisi sumber pendapatan', 'detail' => 'income', 'metrics' => [
                    ['label' => 'Transaksi Hari Ini', 'value' => (string) data_get($ownerSummary, 'transactions_today', 0)],
                    ['label' => 'Retur', 'value' => \App\Support\CurrencyFormatter::rupiah((string) data_get($ownerSummary, 'returns_value', 0))],
                    ['label' => 'Selisih Kas', 'value' => \App\Support\CurrencyFormatter::rupiah((string) data_get($ownerSummary, 'cash_difference', 0))],
                ], 'bars' => $ownerChannels],
                ['title' => 'Performa Lokasi', 'subtitle' => 'Pendapatan dan kesehatan stok toko', 'detail' => 'locations', 'columns' => ['Lokasi', 'Pendapatan', 'Margin', 'Kritis'], 'rows' => $ownerStores],
                ['title' => 'Risiko & Piutang', 'subtitle' => 'Item yang memerlukan keputusan owner', 'detail' => 'receivables', 'metrics' => [
                    ['label' => 'Piutang Overdue', 'value' => \App\Support\CurrencyFormatter::rupiah((string) data_get($ownerSummary, 'overdue_receivable', 0))],
                    ['label' => 'Anomali Terbuka', 'value' => (string) data_get($ownerSummary, 'anomaly_open', 0)],
                    ['label' => 'Approval Menunggu', 'value' => (string) data_get($ownerSummary, 'pending_approval', 0)],
                ], 'columns' => ['Piutang', 'Customer', 'Lokasi', 'Outstanding'], 'rows' => $ownerReceivables],
            ],
            'links' => ['income' => 'Pendapatan', 'profitability' => 'Profitabilitas', 'inventory' => 'Kesehatan Stok', 'receivables' => 'Piutang', 'purchasing' => 'Pembelian', 'locations' => 'Perbandingan Lokasi', 'workforce' => 'Tenaga Kerja'],
        ];
    @endphp
    @include('reports.profiles.partials.profile-overview-dashboard')
@endsection
