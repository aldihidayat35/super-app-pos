<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Faktur {{ $sale->number }}</title>
    @php
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
            line-height: 1.3;
            color: #000;
            background: #eef2f5;
            padding: 20px;
        }
        .invoice-box {
            background: #fff;
            margin: 0 auto;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 720px;
            max-width: 100%;
            min-height: 480px;
            position: relative;
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
            margin: 5px 0;
        }
        .double-line {
            border-top: 2px double #000;
            margin: 5px 0;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }
        table.items th {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 2px;
            font-size: 11px;
            text-align: left;
        }
        table.items td {
            padding: 3px 2px;
            font-size: 11px;
            vertical-align: top;
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
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            text-align: center;
        }
        .sig-block {
            width: 28%;
        }
        .sig-space {
            height: 45px;
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
            .invoice-box {
                width: 185mm !important;
                max-width: 185mm !important;
                box-shadow: none !important;
                padding: 4mm 6mm !important;
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
        <button type="button" class="btn-print" onclick="window.print()">🖨️ Cetak Faktur LX-310</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup Jendela</button>
    </div>

    <div class="invoice-box">
        {{-- Header Bar --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="width: 55%;">
                <div class="bold" style="font-size: 14px;">{{ $sale->branch?->name ?? $companyName }}</div>
                @if($sale->branch?->address)
                    <div>{{ $sale->branch->address }}</div>
                @endif
                @if($sale->branch?->phone)
                    <div>Telp: {{ $sale->branch->phone }}</div>
                @endif
            </div>
            <div style="width: 42%; text-align: right;">
                <div class="bold" style="font-size: 13px;">FAKTUR PENJUALAN</div>
                <div>No. Faktur : <span class="bold">{{ $sale->number }}</span></div>
                <div>Tanggal    : {{ $sale->completed_at?->format('d/m/Y H:i') ?? date('d/m/Y H:i') }}</div>
                <div>Pelanggan  : <span class="bold">{{ $sale->customer?->business_name ?? $sale->customer?->name ?? 'Pelanggan Umum' }}</span></div>
                @if($sale->customer?->address)
                    <div>Alamat     : {{ $sale->customer->address }}</div>
                @endif
            </div>
        </div>

        {{-- Tabel Barang --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 5%;">NO</th>
                    @if($settings['print_show_item_barcode'])
                        <th style="width: 18%;">KODE / SKU</th>
                        <th style="width: 37%;">NAMA BARANG</th>
                    @else
                        <th style="width: 55%;">NAMA BARANG</th>
                    @endif
                    <th style="width: 10%; text-align: center;">QTY</th>
                    <th style="width: 15%; text-align: right;">HARGA</th>
                    <th style="width: 15%; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        @if($settings['print_show_item_barcode'])
                            <td>{{ $item->product?->sku ?? $item->product?->barcode ?? '-' }}</td>
                        @endif
                        <td>{{ $item->product_name_snapshot ?? $item->product?->name }}</td>
                        <td class="center">{{ qty($item->quantity) }} {{ $item->unit?->code ?? '' }}</td>
                        <td class="right">{{ number_format((float) $item->selected_price, 0, ',', '.') }}</td>
                        <td class="right">{{ number_format((float) $item->line_total, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="dashed-line"></div>

        {{-- Ringkasan Total & Keterangan --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="width: 55%;">
                <div style="font-size: 10px; color: #333;">
                    * {{ $settings['print_invoice_footer_note'] ?? 'Pembayaran sah jika telah diterbitkan kuitansi atau bukti transaksi resmi.' }}
                </div>
            </div>
            <div style="width: 42%;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Subtotal</span>
                    <span class="bold">Rp {{ number_format((float) $sale->subtotal_amount, 0, ',', '.') }}</span>
                </div>
                @if((float) $sale->discount_amount > 0)
                    <div style="display: flex; justify-content: space-between;">
                        <span>Diskon / Potongan</span>
                        <span>-Rp {{ number_format((float) $sale->discount_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if((float) $sale->tax_amount > 0)
                    <div style="display: flex; justify-content: space-between;">
                        <span>Pajak (PPN)</span>
                        <span>Rp {{ number_format((float) $sale->tax_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="dashed-line"></div>
                <div style="display: flex; justify-content: space-between; font-size: 13px;" class="bold">
                    <span>GRAND TOTAL</span>
                    <span>Rp {{ number_format((float) $sale->grand_total_amount, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Kolom Tanda Tangan 3 Pihak --}}
        @if($settings['print_show_signatures'])
            <div class="signatures">
                <div class="sig-block">
                    <div>Penerima / Pembeli,</div>
                    <div class="sig-space"></div>
                    <div>( ........................ )</div>
                </div>
                <div class="sig-block">
                    <div>Sopir / Pengirim,</div>
                    <div class="sig-space"></div>
                    <div>( ........................ )</div>
                </div>
                <div class="sig-block">
                    <div>Hormat Kami,</div>
                    <div class="sig-space"></div>
                    <div class="bold">( {{ $sale->cashier?->name ?? 'Admin / Kasir' }} )</div>
                </div>
            </div>
        @endif
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

