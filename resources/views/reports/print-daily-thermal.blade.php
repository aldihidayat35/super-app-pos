<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Harian (Thermal) - {{ $filters['start_date'] }}</title>
    @php
        use App\Support\CurrencyFormatter;
        $summary = $report['summary'];
        $settings = $settings ?? \App\Models\SystemSetting::getPrinterSettings();
        $companyName = $companyName ?? \App\Models\SystemSetting::getCompanyName();
        $is58mm = ($settings['printer_thermal_width'] ?? '80mm') === '58mm';
    @endphp
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: {{ $is58mm ? '10px' : '11px' }};
            line-height: 1.35;
            color: #000;
            background: #f8fafc;
            padding: 20px;
        }
        .receipt-container {
            background: #fff;
            margin: 0 auto;
            padding: 12px 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: {{ $is58mm ? '210px' : '300px' }};
            max-width: 100%;
        }
        .center {
            text-align: center;
        }
        .right {
            text-align: right;
        }
        .bold {
            font-weight: bold;
        }
        .line {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .double-line {
            border-top: 2px double #000;
            margin: 6px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .no-print-bar {
            text-align: center;
            margin-bottom: 15px;
        }
        .btn-print {
            background: #009ef7;
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 4px;
        }
        .btn-close {
            background: #7e8299;
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 4px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .receipt-container {
                width: 100% !important;
                box-shadow: none !important;
                padding: 2px 4px !important;
            }
            @page {
                size: {{ $is58mm ? '58mm' : '80mm' }} auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <button type="button" class="btn-print" onclick="window.print()">🖨️ Cetak Slip Laporan (Thermal)</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup Jendela</button>
    </div>

    <div class="receipt-container">
        <div class="center">
            <span class="bold" style="font-size: 13px;">{{ $companyName }}</span><br>
            <span class="bold">RINGKASAN LAPORAN HARIAN</span><br>
            <span>Periode: {{ $filters['start_date'] }} s/d {{ $filters['end_date'] }}</span>
        </div>

        <div class="line"></div>

        <div class="row">
            <span>Dicetak: {{ date('d/m/Y H:i') }}</span>
            <span>Kasir: {{ auth()->user()?->name ?? 'Admin' }}</span>
        </div>

        <div class="line"></div>

        {{-- Ringkasan Penjualan --}}
        <div class="bold center mb-1">--- PERFORMA OMZET ---</div>
        <div class="row bold">
            <span>TOTAL OMZET</span>
            <span>{{ CurrencyFormatter::rupiah($summary['revenue'] ?? 0) }}</span>
        </div>
        <div class="row">
            <span>Total Transaksi</span>
            <span>{{ number_format($summary['transaction_count'] ?? 0, 0, ',', '.') }} struk</span>
        </div>
        <div class="row">
            <span>Rata-rata / Tiket</span>
            <span>{{ CurrencyFormatter::rupiah($summary['average_ticket'] ?? 0) }}</span>
        </div>
        <div class="row">
            <span>Laba Kotor</span>
            <span>{{ CurrencyFormatter::rupiah($summary['margin'] ?? ($summary['gross_margin'] ?? 0)) }}</span>
        </div>

        <div class="line"></div>

        {{-- Operasional Kasir --}}
        <div class="bold center mb-1">--- ARUS KAS &amp; RETUR ---</div>
        <div class="row">
            <span>Selisih Kas</span>
            <span class="bold">{{ CurrencyFormatter::rupiah($summary['cash_difference'] ?? 0) }}</span>
        </div>
        <div class="row">
            <span>Transaksi Batal (Void)</span>
            <span>{{ $summary['void_count'] ?? 0 }}</span>
        </div>
        <div class="row">
            <span>Nilai Retur</span>
            <span>{{ CurrencyFormatter::rupiah($summary['return_amount'] ?? ($summary['returns_value'] ?? 0)) }}</span>
        </div>

        <div class="line"></div>

        {{-- Nilai Persediaan --}}
        <div class="row">
            <span>Nilai Stok Toko</span>
            <span class="bold">{{ CurrencyFormatter::rupiah($summary['stock_value'] ?? 0) }}</span>
        </div>
        <div class="row">
            <span>Stok Kritis / Kosong</span>
            <span>{{ $summary['critical_stock_count'] ?? 0 }} / {{ $summary['empty_stock_count'] ?? 0 }}</span>
        </div>

        <div class="double-line"></div>

        <div class="center" style="margin-top: 6px;">
            <span>Laporan dicetak otomatis dari sistem {{ config('app.name') }}</span>
        </div>
    </div>

    <script>
        (() => {
            const closePrintWindow = () => {
                if (window.opener && !window.closed) window.close();
            };

            window.addEventListener('afterprint', closePrintWindow, { once: true });
            window.addEventListener('load', () => window.setTimeout(() => {
                window.print();
                window.setTimeout(closePrintWindow, 300);
            }, 150), { once: true });
        })();
    </script>
</body>
</html>

