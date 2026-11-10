<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Jalan {{ $transfer->number }}</title>
    @php
        $settings = \App\Models\SystemSetting::getPrinterSettings();
        $companyName = \App\Models\SystemSetting::getCompanyName();
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
        .sheet {
            background: #fff;
            margin: 0 auto;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 820px;
            max-width: 100%;
            min-height: 480px;
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
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            text-align: center;
        }
        .sig-block {
            width: 28%;
        }
        .sig-space {
            height: 45px;
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
            body {
                background: #fff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .sheet {
                width: 100% !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            @page {
                size: 9.5in 5.5in;
                margin: 0.25in 0.3in;
            }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <button type="button" class="btn-print" onclick="window.print()">🖨️ Cetak Surat Jalan LX-310</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup Jendela</button>
    </div>

    <div class="sheet">
        {{-- Header --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="width: 55%;">
                <div class="bold" style="font-size: 14px;">{{ $companyName }}</div>
                <div>SURAT JALAN / PENGIRIMAN TRANSFER STOK</div>
                <div>Asal   : <span class="bold">{{ $transfer->sourceWorkLocation?->name ?? 'Gudang Pusat' }}</span></div>
                <div>Tujuan : <span class="bold">{{ $transfer->destinationWorkLocation?->name ?? 'Toko Cabang' }}</span></div>
            </div>
            <div style="width: 42%; text-align: right;">
                <div>No. Transfer : <span class="bold">{{ $transfer->number }}</span></div>
                <div>Tanggal     : {{ $transfer->created_at?->format('d/m/Y H:i') ?? date('d/m/Y H:i') }}</div>
                <div>Kendaraan   : {{ $transfer->vehicle_number ?: ($transfer->tracking_number ?: '-') }}</div>
                <div>Sopir/Kurir : {{ $transfer->carrier ?: '-' }}</div>
            </div>
        </div>

        {{-- Tabel Barang --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 5%;">NO</th>
                    <th style="width: 20%;">KODE / SKU</th>
                    <th style="width: 45%;">NAMA PRODUK</th>
                    <th style="width: 15%; text-align: center;">QTY KIRIM</th>
                    <th style="width: 15%;">CATATAN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transfer->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->product_sku_snapshot ?? '-' }}</td>
                        <td>{{ $item->product_name_snapshot ?? '-' }}</td>
                        <td class="center bold">{{ qty($item->quantity_shipped > 0 ? $item->quantity_shipped : ($item->quantity_picked > 0 ? $item->quantity_picked : $item->quantity_approved)) }}</td>
                        <td>{{ $item->notes ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="dashed-line"></div>

        <div style="display: flex; justify-content: space-between; font-size: 10px;">
            <div>* Barang telah diperiksa dan diserahkan dalam kondisi baik dan lengkap sesuai dokumen ini.</div>
            <div class="right">Total Item: <span class="bold">{{ $transfer->items->count() }} macam</span></div>
        </div>

        {{-- Tanda Tangan 3 Pihak --}}
        @if($settings['print_show_signatures'])
            <div class="signatures">
                <div class="sig-block">
                    <div>Penerima (Tujuan),</div>
                    <div class="sig-space"></div>
                    <div>( ........................ )</div>
                </div>
                <div class="sig-block">
                    <div>Sopir / Kurir Pengantar,</div>
                    <div class="sig-space"></div>
                    <div>( {{ $transfer->carrier ?: '........................' }} )</div>
                </div>
                <div class="sig-block">
                    <div>Pengirim (Asal),</div>
                    <div class="sig-space"></div>
                    <div class="bold">( {{ $transfer->shipper?->name ?: 'Petugas Gudang' }} )</div>
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
