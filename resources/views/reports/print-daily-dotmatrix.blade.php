<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Harian Detail LX-310 - {{ $filters['start_date'] }}</title>
    @php
        use App\Support\CurrencyFormatter;
        $summary = $report['summary'];
        $settings = $settings ?? \App\Models\SystemSetting::getPrinterSettings();
        $companyName = $companyName ?? \App\Models\SystemSetting::getCompanyName();
    @endphp
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
            background: #eef2f5;
            padding: 20px;
        }
        .report-box {
            background: #fff;
            margin: 0 auto;
            padding: 20px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 720px;
            max-width: 100%;
            min-height: 520px;
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
        .dashed-line {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .double-line {
            border-top: 2px double #000;
            margin: 6px 0;
        }
        table.kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table.kpi-table th {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 4px;
            font-size: 11px;
            text-align: left;
        }
        table.kpi-table td {
            padding: 4px 4px;
            font-size: 11px;
            vertical-align: top;
        }
        .signatures {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
            text-align: center;
        }
        .sig-block {
            width: 35%;
        }
        .sig-space {
            height: 50px;
        }
        .no-print-bar {
            text-align: center;
            margin-bottom: 15px;
        }
        .btn-print {
            background: #10b981;
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
            html, body {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print-bar {
                display: none !important;
            }
            .report-box {
                width: 185mm !important;
                max-width: 185mm !important;
                box-shadow: none !important;
                padding: 6mm 8mm !important;
                margin: 0 auto !important;
                page-break-inside: avoid;
            }
            @page {
                size: auto;
                margin: 0mm;
            }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <button type="button" class="btn-print" onclick="window.print()">🖨️ Cetak Epson LX-310 (Full Folio)</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup Jendela</button>
    </div>

    <div class="report-box">
        {{-- Header Laporan --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="width: 60%;">
                <div class="bold" style="font-size: 14px;">{{ $companyName }}</div>
                <div class="bold" style="font-size: 13px;">REKAPITULASI LAPORAN HARIAN OPERASIONAL</div>
                <div>Periode Transaksi : <span class="bold">{{ $filters['start_date'] }} s/d {{ $filters['end_date'] }}</span></div>
            </div>
            <div style="width: 38%; text-align: right;">
                <div>Waktu Cetak : {{ date('d/m/Y H:i:s') }}</div>
                <div>Operator    : {{ auth()->user()?->name ?? 'Admin' }}</div>
                <div>Halaman     : 1 / 1</div>
            </div>
        </div>

        <div class="double-line"></div>

        {{-- Tabel Ringkasan KPI Utama --}}
        <div class="bold" style="margin-top: 10px;">I. REKAPITULASI FINANSIAL &amp; PENJUALAN</div>
        <table class="kpi-table">
            <thead>
                <tr>
                    <th style="width: 45%;">INDIKATOR KINERJA</th>
                    <th style="width: 25%; text-align: right;">NILAI NOMINAL</th>
                    <th style="width: 30%; text-align: left; padding-left: 15px;">KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Omzet Penjualan (Gross Revenue)</td>
                    <td class="right bold">{{ CurrencyFormatter::rupiah($summary['revenue'] ?? 0) }}</td>
                    <td style="padding-left: 15px;">Retail POS + B2B</td>
                </tr>
                <tr>
                    <td>Laba Kotor (Gross Margin)</td>
                    <td class="right bold">{{ CurrencyFormatter::rupiah($summary['margin'] ?? ($summary['gross_margin'] ?? 0)) }}</td>
                    <td style="padding-left: 15px;">{{ $summary['margin_percent'] ?? '0' }}% dari omzet</td>
                </tr>
                <tr>
                    <td>Total Transaksi Selesai</td>
                    <td class="right bold">{{ number_format($summary['transaction_count'] ?? 0, 0, ',', '.') }} Struk</td>
                    <td style="padding-left: 15px;">Rata-rata: {{ CurrencyFormatter::rupiah($summary['average_ticket'] ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Nilai Retur Barang &amp; Pembatalan (Loss)</td>
                    <td class="right">{{ CurrencyFormatter::rupiah($summary['return_amount'] ?? ($summary['returns_value'] ?? 0)) }}</td>
                    <td style="padding-left: 15px;">Total transaksi void: {{ $summary['void_count'] ?? 0 }}</td>
                </tr>
                <tr>
                    <td>Selisih Kas Fisik Kasir</td>
                    <td class="right">{{ CurrencyFormatter::rupiah($summary['cash_difference'] ?? 0) }}</td>
                    <td style="padding-left: 15px;">Closing kasir vs sistem</td>
                </tr>
            </tbody>
        </table>

        <div class="dashed-line"></div>

        {{-- Tabel Ringkasan Persediaan --}}
        <div class="bold" style="margin-top: 10px;">II. STATUS LOGISTIK &amp; PERSEDIAAN GUDANG</div>
        <table class="kpi-table">
            <thead>
                <tr>
                    <th style="width: 45%;">PARAMETER PERSEDIAAN</th>
                    <th style="width: 25%; text-align: right;">JUMLAH / NILAI</th>
                    <th style="width: 30%; text-align: left; padding-left: 15px;">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Nilai Persediaan Aktif</td>
                    <td class="right bold">{{ CurrencyFormatter::rupiah($summary['stock_value'] ?? 0) }}</td>
                    <td style="padding-left: 15px;">HPP modal berjalan</td>
                </tr>
                <tr>
                    <td>Total Varian Produk Terdaftar</td>
                    <td class="right">{{ number_format($summary['total_products'] ?? 0, 0, ',', '.') }} SKU</td>
                    <td style="padding-left: 15px;">Katalog aktif</td>
                </tr>
                <tr>
                    <td>Produk Stok Kritis (Di Bawah Min)</td>
                    <td class="right bold">{{ $summary['critical_stock_count'] ?? ($summary['critical_count'] ?? 0) }} SKU</td>
                    <td style="padding-left: 15px;">Perlu pengadaan / restock</td>
                </tr>
                <tr>
                    <td>Produk Stok Kosong (Nol)</td>
                    <td class="right bold" style="color: #000;">{{ $summary['empty_stock_count'] ?? ($summary['empty_count'] ?? 0) }} SKU</td>
                    <td style="padding-left: 15px;">Habis di rak</td>
                </tr>
            </tbody>
        </table>

        <div class="dashed-line"></div>

        {{-- Tanda Tangan --}}
        <div class="signatures">
            <div class="sig-block">
                <div>Disiapkan Oleh,</div>
                <div class="sig-space"></div>
                <div class="bold">( {{ auth()->user()?->name ?? 'Kasir / Petugas' }} )</div>
                <div style="font-size: 10px;">Staff Operasional</div>
            </div>
            <div class="sig-block">
                <div>Diketahui &amp; Disetujui Oleh,</div>
                <div class="sig-space"></div>
                <div class="bold">( ............................ )</div>
                <div style="font-size: 10px;">Kepala Toko / Owner</div>
            </div>
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

