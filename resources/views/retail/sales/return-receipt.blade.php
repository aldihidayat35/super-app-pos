<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nota Retur {{ $return->number }}</title>
    @php
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
            font-family: 'Courier New', Courier, monospace, monospace;
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
        .item-row {
            margin-bottom: 4px;
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
        <button type="button" class="btn-print" onclick="window.print()">🖨️ Cetak Nota Retur</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup Jendela</button>
    </div>

    <div class="receipt-container">
        {{-- Header Toko --}}
        @if($settings['print_show_store_header'])
            <div class="center">
                <span class="bold" style="font-size: 13px;">{{ $return->branch?->name ?? $companyName }}</span><br>
                @if($return->branch?->address)
                    <span>{{ $return->branch->address }}</span><br>
                @endif
                @if($return->branch?->phone)
                    <span>Telp: {{ $return->branch->phone }}</span>
                @endif
            </div>
            <div class="line"></div>
        @endif

        {{-- Meta Nota Retur --}}
        <div class="center bold" style="margin-bottom: 4px;">
            <span>BUKTI PENGEMBALIAN BARANG (RETUR)</span>
        </div>
        <div class="row">
            <span>No. Retur:</span>
            <span class="bold">{{ $return->number }}</span>
        </div>
        <div class="row">
            <span>Ref. Transaksi:</span>
            <span>{{ $return->sale?->number }}</span>
        </div>
        <div class="row">
            <span>Waktu:</span>
            <span>{{ $return->completed_at?->format('d/m/y H:i') ?? date('d/m/y H:i') }}</span>
        </div>
        <div class="row">
            <span>Kasir:</span>
            <span>{{ $return->cashier?->name ?? 'Kasir' }}</span>
        </div>
        @if($return->sale?->customer)
            <div class="row">
                <span>Pelanggan:</span>
                <span>{{ $return->sale->customer->business_name ?? $return->sale->customer->name }}</span>
            </div>
        @endif

        <div class="line"></div>

        {{-- Daftar Barang Diretur --}}
        <div class="bold" style="margin-bottom: 4px;">BARANG DIKEMBALIKAN:</div>
        @foreach($return->items as $item)
            <div class="item-row">
                <div class="bold">{{ $item->product?->name }}</div>
                <div class="row">
                    <span>
                        {{ qty($item->quantity) }} PCS 
                        <small>({{ $item->condition === 'damaged' ? 'Kondisi Rusak' : 'Kondisi Baik' }})</small>
                    </span>
                    <span class="bold">Rp {{ number_format((float) $item->refund_amount, 0, ',', '.') }}</span>
                </div>
            </div>
        @endforeach

        <div class="double-line"></div>

        {{-- Total Refund --}}
        <div class="row bold" style="font-size: 13px;">
            <span>TOTAL DANA KEMBALI</span>
            <span>Rp {{ number_format((float) $return->refund_amount, 0, ',', '.') }}</span>
        </div>
        <div class="row">
            <span>Metode Refund:</span>
            <span class="bold">{{ ucfirst($return->refund_method ?? 'Tunai') }}</span>
        </div>
        @if($return->reason)
            <div class="row" style="margin-top: 4px;">
                <span>Alasan:</span>
                <span>{{ $return->reason }}</span>
            </div>
        @endif

        {{-- Kolom Tanda Tangan --}}
        <div class="line"></div>
        <div style="display: flex; justify-content: space-between; text-align: center; margin-top: 8px; font-size: {{ $is58mm ? '9px' : '10px' }};">
            <div style="width: 48%; text-align: center;">
                <div>Pelanggan,</div>
                <div style="height: 36px;"></div>
                <div>( ................ )</div>
            </div>
            <div style="width: 48%; text-align: center;">
                <div>Kasir Bertugas,</div>
                <div style="height: 36px;"></div>
                <div class="bold">( {{ $return->cashier?->name ?? 'Kasir' }} )</div>
            </div>
        </div>

        <div class="line"></div>
        <div class="center" style="margin-top: 6px;">
            <span>Terima kasih atas kerja samanya.</span>
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
