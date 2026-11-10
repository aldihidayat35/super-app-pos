<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Uji Coba Cetak Epson LX-310 (Setengah Folio 9.5" x 5.5")</title>
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
            width: 820px;
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
            body {
                background: #fff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .invoice-box {
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
        <button type="button" class="btn-print" onclick="window.print()">🖨️ Cetak Epson LX-310</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup Jendela</button>
    </div>

    <div class="invoice-box">
        {{-- Header Bar --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="width: 55%;">
                <div class="bold" style="font-size: 14px;">{{ $companyName }}</div>
                <div>Jl. Raya Gudang Pusat No. 88, Kawasan Industri</div>
                <div>Telp: (021) 555-8989 / WhatsApp: 0812-9988-7766</div>
            </div>
            <div style="width: 42%; text-align: right;">
                <div class="bold" style="font-size: 13px;">FAKTUR PENJUALAN / NOTA</div>
                <div>No. Faktur : <span class="bold">INV-{{ date('Ymd') }}-0088</span></div>
                <div>Tanggal    : {{ date('d/m/Y H:i') }}</div>
                <div>Pelanggan  : <span class="bold">CV. JAYA ABADI SENTOSA</span></div>
                <div>Alamat     : Ruko Indah Blok C/12, Kota Bekasi</div>
            </div>
        </div>

        {{-- Tabel Barang --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 5%;">NO</th>
                    <th style="width: 18%;">KODE / SKU</th>
                    <th style="width: 37%;">NAMA BARANG</th>
                    <th style="width: 10%; text-align: center;">QTY</th>
                    <th style="width: 15%; text-align: right;">HARGA</th>
                    <th style="width: 15%; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>MYK-SAN-2L</td>
                    <td>Minyak Goreng Sania 2L (Dus @ 6 pcs)</td>
                    <td class="center">10 dus</td>
                    <td class="right">200.000</td>
                    <td class="right">2.000.000</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>GUL-KUN-1K</td>
                    <td>Gula Pasir Gulaku Kuning 1kg (Karton @ 20 pcs)</td>
                    <td class="center">5 krt</td>
                    <td class="right">340.000</td>
                    <td class="right">1.700.000</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>TRG-SEG-1K</td>
                    <td>Tepung Segitiga Biru 1kg (Dus @ 12 pcs)</td>
                    <td class="center">8 dus</td>
                    <td class="right">150.000</td>
                    <td class="right">1.200.000</td>
                </tr>
            </tbody>
        </table>

        <div class="dashed-line"></div>

        {{-- Ringkasan Total & Keterangan --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="width: 55%;">
                <div><strong>Terbilang:</strong> Empat Juta Delapan Ratus Lima Puluh Ribu Rupiah.</div>
                <div style="margin-top: 4px; font-size: 10px;">
                    * {{ $settings['print_invoice_footer_note'] ?? 'Pembayaran sah jika telah diterbitkan kuitansi resmi.' }}
                </div>
            </div>
            <div style="width: 42%;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Subtotal</span>
                    <span class="bold">Rp 4.900.000</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Diskon Khusus</span>
                    <span>Rp -50.000</span>
                </div>
                <div class="dashed-line"></div>
                <div style="display: flex; justify-content: space-between; font-size: 13px;" class="bold">
                    <span>GRAND TOTAL</span>
                    <span>Rp 4.850.000</span>
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
                    <div class="bold">( Admin / Kasir )</div>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
