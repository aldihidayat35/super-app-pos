<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Uji Coba Cetak Thermal ({{ $settings['printer_thermal_width'] }})</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace, monospace;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
            background: #f4f6f8;
            padding: 20px;
        }
        .receipt-container {
            background: #fff;
            margin: 0 auto;
            padding: 12px 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: {{ $settings['printer_thermal_width'] === '58mm' ? '210px' : '300px' }};
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
        .barcode-text {
            font-size: 9px;
            color: #444;
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
                size: {{ $settings['printer_thermal_width'] === '58mm' ? '58mm' : '80mm' }} auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <button type="button" class="btn-print" onclick="window.print()">🖨️ Cetak Sekarang</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup Jendela</button>
    </div>

    <div class="receipt-container">
        @if($settings['print_show_store_header'])
            <div class="center">
                <span class="bold" style="font-size: 13px;">{{ $companyName }}</span><br>
                <span>Jl. Raya Pusat No. 123, Jakarta</span><br>
                <span>Telp: 021-5550123 / WA: 0812-3456-7890</span>
            </div>
            <div class="line"></div>
        @endif

        <div class="row">
            <span>No: POS-{{ date('Ymd') }}-0001</span>
            <span>{{ date('d/m/Y H:i') }}</span>
        </div>
        @if($settings['print_show_cashier_name'] || $settings['print_show_customer_name'])
            <div class="row">
                @if($settings['print_show_cashier_name'])
                    <span>Kasir: Budi Santoso</span>
                @endif
                @if($settings['print_show_customer_name'])
                    <span>Plg: Toko Makmur</span>
                @endif
            </div>
        @endif
        <div class="line"></div>

        {{-- Item Sampel 1 --}}
        <div class="item-row">
            <div class="bold">Minyak Goreng Sania 2L</div>
            @if($settings['print_show_item_barcode'])
                <div class="barcode-text">SKU: MYK-SAN-2L · 8991234567890</div>
            @endif
            <div class="row">
                <span>2 x 34.000</span>
                <span class="bold">68.000</span>
            </div>
        </div>

        {{-- Item Sampel 2 --}}
        <div class="item-row">
            <div class="bold">Gula Pasir Gulaku 1kg</div>
            @if($settings['print_show_item_barcode'])
                <div class="barcode-text">SKU: GUL-KUN-1K · 8999876543210</div>
            @endif
            <div class="row">
                <span>3 x 17.500</span>
                <span class="bold">52.500</span>
            </div>
        </div>

        {{-- Item Sampel 3 --}}
        <div class="item-row">
            <div class="bold">Kecap Manis Bango 550ml</div>
            @if($settings['print_show_item_barcode'])
                <div class="barcode-text">SKU: KCP-BNG-55 · 8995551234567</div>
            @endif
            <div class="row">
                <span>1 x 24.000</span>
                <span class="bold">24.000</span>
            </div>
        </div>

        <div class="line"></div>

        {{-- Total --}}
        <div class="row bold">
            <span>SUBTOTAL</span>
            <span>144.500</span>
        </div>
        <div class="row">
            <span>Diskon Member</span>
            <span>-4.500</span>
        </div>
        <div class="double-line"></div>
        <div class="row bold" style="font-size: 13px;">
            <span>TOTAL BELANJA</span>
            <span>Rp 140.000</span>
        </div>

        @if($settings['print_show_payment_details'])
            <div class="line"></div>
            <div class="row">
                <span>Pembayaran (Tunai)</span>
                <span>150.000</span>
            </div>
            <div class="row bold">
                <span>KEMBALIAN</span>
                <span>10.000</span>
            </div>
        @endif

        <div class="line"></div>
        <div class="center" style="margin-top: 6px;">
            <span>{{ $settings['print_receipt_footer_note'] ?? 'Terima kasih atas kunjungan Anda' }}</span><br>
            <span class="bold" style="margin-top: 4px; display: block;">*** UJI COBA SUKSES ***</span>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            // Uncomment jika ingin auto-print saat halaman tes dimuat
            // window.print();
        });
    </script>
</body>
</html>
