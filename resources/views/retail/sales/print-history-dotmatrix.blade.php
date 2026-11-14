<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Riwayat Transaksi POS - {{ $startDate }} s/d {{ $endDate }}</title>
    @php
        use App\Support\CurrencyFormatter;
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
            font-size: 10.5px;
            line-height: 1.25;
            color: #000;
            background: #eef2f5;
            padding: 15px;
        }

        .report-box {
            background: #fff;
            margin: 0 auto;
            padding: 12px 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            width: 720px;
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
            margin: 4px 0;
        }

        .double-line {
            border-top: 2px double #000;
            margin: 5px 0;
        }

        .sale-header {
            margin-top: 6px;
            margin-bottom: 2px;
            font-size: 10.5px;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 3px 0 5px 0;
        }

        table.items-table th {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 2px 3px;
            font-size: 10px;
            text-align: left;
        }

        table.items-table td {
            padding: 2px 3px;
            font-size: 10px;
            vertical-align: top;
        }

        table.summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }

        table.summary-table td {
            padding: 2px 3px;
            font-size: 10.5px;
            vertical-align: top;
        }

        .no-print-bar {
            text-align: center;
            margin-bottom: 12px;
        }

        .btn-print {
            background: #10b981;
            color: #fff;
            border: none;
            padding: 7px 16px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 4px;
        }

        .btn-close {
            background: #64748b;
            color: #fff;
            border: none;
            padding: 7px 16px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 4px;
        }

        .signatures {
            display: flex;
            justify-content: space-around;
            align-items: flex-start;
            margin-top: 18px;
            text-align: center;
            width: 100%;
        }

        .sig-block {
            width: 42%;
            text-align: center;
        }

        .sig-title {
            font-size: 10.5px;
            margin-bottom: 2px;
            white-space: nowrap;
        }

        .sig-space {
            height: 38px;
        }

        .sig-name {
            font-size: 10.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        .sig-dots {
            display: inline-block;
            white-space: nowrap;
            letter-spacing: 1px;
        }

        @media print {

            html,
            body {
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
                padding: 3mm 5mm !important;
                margin: 0 auto !important;
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
        <button type="button" id="btn-do-print" class="btn-print" onclick="window.print()">🖨️ Cetak Riwayat Transaksi (LX-310) [Enter]</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup [ESC]</button>
    </div>

    <div class="report-box">
        {{-- Header Bar --}}
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="width: 50%;">
                <div class="bold" style="font-size: 13px;">{{ $branch?->name ?? $companyName }}</div>
                @if ($branch?->address)
                    <div>{{ $branch->address }}</div>
                @endif
                @if ($branch?->phone)
                    <div>Telp: {{ $branch->phone }}</div>
                @endif
            </div>
            <div style="width: 48%; text-align: right;">
                <div class="bold" style="font-size: 12px;">RIWAYAT TRANSAKSI &amp; DETAIL BARANG</div>
                <div>Periode : <span class="bold">{{ \Carbon\Carbon::parse($startDateTime)->format('d/m/Y H:i') }} -
                        {{ \Carbon\Carbon::parse($endDateTime)->format('d/m/Y H:i') }}</span></div>
                <div>Cetak : {{ now()->format('d/m/Y H:i') }} | Opr: {{ $currentUser->name }}</div>
            </div>
        </div>

        <div class="double-line"></div>

        {{-- Daftar Transaksi & Rincian Barang --}}
        @if ($sales->isEmpty())
            <div class="center" style="padding: 35px 0;">
                <div class="bold" style="font-size: 12px; margin-bottom: 5px;">TIDAK ADA TRANSAKSI DITEMUKAN</div>
                <div>Tidak ada transaksi POS pada rentang waktu
                    {{ \Carbon\Carbon::parse($startDateTime)->format('d/m/Y H:i') }} s/d
                    {{ \Carbon\Carbon::parse($endDateTime)->format('d/m/Y H:i') }}.</div>
            </div>
        @else
            @foreach ($sales as $index => $sale)
                <div class="sale-header">
                    <div style="display: flex; justify-content: space-between; font-weight: bold;">
                        <div>
                            [{{ $index + 1 }}] {{ $sale->number }}
                            <span style="font-weight: normal;">|
                                {{ ($sale->completed_at ?? $sale->created_at)->format('d/m/Y H:i') }}</span>
                        </div>
                        <div>
                            <span>Kasir: {{ $sale->cashier?->name ?? '-' }}</span>
                            @if ($sale->status->value !== 'completed')
                                <span style="margin-left: 6px;">[{{ strtoupper($sale->status->label()) }}]</span>
                            @endif
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 10px; color: #111;">
                        <div>Pelanggan:
                            {{ $sale->customer?->business_name ?? ($sale->customer?->name ?? 'Pelanggan Umum') }}</div>
                        <div>
                            @php
                                $payMethods = $sale->payments
                                    ->map(
                                        fn($p) => ($p->method?->label() ?? 'Bayar') .
                                            ($p->reference_number ? ' (' . $p->reference_number . ')' : ''),
                                    )
                                    ->implode(', ');
                            @endphp
                            Bayar: {{ $payMethods ?: '-' }}
                        </div>
                    </div>
                </div>

                {{-- Tabel Barang Per Transaksi --}}
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 20px;">No</th>
                            <th>Nama Barang / Produk</th>
                            <th class="right" style="width: 65px;">Qty</th>
                            <th style="width: 45px;">Satuan</th>
                            <th class="right" style="width: 80px;">Harga</th>
                            <th class="right" style="width: 70px;">Diskon</th>
                            <th class="right" style="width: 90px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sale->items as $itemIdx => $item)
                            @php
                                $itemRetQty = (float) ($item->returned_quantity ?? 0);
                                $itemNetQty = max(0, (float) $item->quantity - $itemRetQty);
                                $itemUPrice = (float) $item->selected_price;
                                $itemRetSubtotal = $itemRetQty * $itemUPrice;
                                $itemNetSubtotal = max(0, (float) $item->line_total - $itemRetSubtotal);
                            @endphp
                            <tr>
                                <td>{{ $itemIdx + 1 }}</td>
                                <td>
                                    {{ $item->product_name_snapshot ?? ($item->product?->name ?? 'Barang') }}
                                    @if ($item->sku_snapshot || $item->product?->sku)
                                        <span
                                            style="font-size: 9px; color: #444;">({{ $item->sku_snapshot ?? $item->product?->sku }})</span>
                                    @endif
                                    @if ($itemRetQty > 0)
                                        <div style="font-size: 9px; color: #b91c1c;">* Retur: -{{ qty($itemRetQty) }} {{ $item->unit_name_snapshot ?? ($item->unit?->symbol ?? 'Pcs') }} (-{{ CurrencyFormatter::rupiah($itemRetSubtotal) }})</div>
                                    @endif
                                </td>
                                <td class="right">
                                    {{ qty($item->quantity) }}
                                    @if ($itemRetQty > 0)
                                        <div style="font-size: 8.5px; font-weight: bold;">(Net: {{ qty($itemNetQty) }})</div>
                                    @endif
                                </td>
                                <td>{{ $item->unit_name_snapshot ?? ($item->unit?->symbol ?? 'Pcs') }}</td>
                                <td class="right">{{ CurrencyFormatter::rupiah($item->selected_price) }}</td>
                                <td class="right">
                                    {{ (float) $item->discount_amount > 0 ? '-' . CurrencyFormatter::rupiah($item->discount_amount) : '-' }}
                                </td>
                                <td class="right">
                                    @if ($itemRetQty > 0)
                                        <div style="text-decoration: line-through; color: #777; font-size: 9px;">{{ CurrencyFormatter::rupiah($item->line_total) }}</div>
                                        <div style="font-weight: bold;">{{ CurrencyFormatter::rupiah($itemNetSubtotal) }}</div>
                                    @else
                                        {{ CurrencyFormatter::rupiah($item->line_total) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="center">Tidak ada item tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Baris Ringkasan Nota --}}
                @php
                    $notaRefAmount = (float) $sale->returns->sum('refund_amount');
                    $notaNetAmount = max(0, (float) $sale->grand_total_amount - $notaRefAmount);
                @endphp
                <div style="display: flex; justify-content: flex-end; font-size: 10px; margin-bottom: 6px;">
                    <div style="text-align: right; line-height: 1.2;">
                        <span>Subtotal: {{ CurrencyFormatter::rupiah($sale->subtotal_amount) }}</span>
                        @if ((float) $sale->discount_amount > 0)
                            <span style="margin-left: 8px;">| Diskon: -{{ CurrencyFormatter::rupiah($sale->discount_amount) }}</span>
                        @endif
                        @if ($notaRefAmount > 0)
                            <span style="margin-left: 8px; text-decoration: line-through; color: #777;">| Asli: {{ CurrencyFormatter::rupiah($sale->grand_total_amount) }}</span>
                            <span style="margin-left: 8px; color: #b91c1c;">| Retur: -{{ CurrencyFormatter::rupiah($notaRefAmount) }}</span>
                            <span class="bold" style="margin-left: 8px;">| Net: {{ CurrencyFormatter::rupiah($notaNetAmount) }}</span>
                        @else
                            <span class="bold" style="margin-left: 8px;">| Total: {{ CurrencyFormatter::rupiah($sale->grand_total_amount) }}</span>
                        @endif
                    </div>
                </div>

                <div class="dashed-line"></div>
            @endforeach

            {{-- Ringkasan Rekapitulasi Akhir --}}
            <div class="double-line" style="margin-top: 8px;"></div>
            <div class="bold" style="font-size: 11px; margin-bottom: 4px;">RINGKASAN REKAPITULASI PENJUALAN</div>

            <table class="summary-table">
                <tr>
                    <td style="width: 25%;">Total Transaksi</td>
                    <td style="width: 25%;">: <span class="bold">{{ $totalCount }}</span> nota (Selesai:
                        {{ $totalCompleted }}@if ($totalVoidOrReturned > 0)
                            , Batal/Retur: {{ $totalVoidOrReturned }}
                        @endif)</td>
                    <td style="width: 25%;">Total Subtotal Kotor</td>
                    <td class="right" style="width: 25%;">: {{ CurrencyFormatter::rupiah($totalGrossAmount) }}</td>
                </tr>
                <tr>
                    <td>Total Qty Terjual (Netto)</td>
                    <td>: <span class="bold">{{ qty($totalNetQuantity) }}</span> unit (Kotor: {{ qty($totalQuantitySold) }}@if($totalReturnedQuantity > 0), Retur: -{{ qty($totalReturnedQuantity) }}@endif)</td>
                    <td>Total Diskon Nota/Item</td>
                    <td class="right">: -{{ CurrencyFormatter::rupiah($totalDiscountAmount) }}</td>
                </tr>
                <tr>
                    <td>Total Nilai Retur/Refund</td>
                    <td style="color: #b91c1c;">: <span class="bold">-{{ CurrencyFormatter::rupiah($totalRefundAmount) }}</span></td>
                    <td style="width: 25%;">Total Penjualan Kotor</td>
                    <td class="right" style="width: 25%;">: {{ CurrencyFormatter::rupiah($totalGrandAmount) }}</td>
                </tr>
                <tr>
                    <td>Rincian Pembayaran</td>
                    <td>:
                        @forelse($paymentSummary as $methodName => $amount)
                            {{ $methodName }}: {{ CurrencyFormatter::rupiah($amount) }}@if (!$loop->last)
                                ,
                            @endif
                        @empty
                            -
                        @endforelse
                    </td>
                    <td class="bold" style="font-size: 11.5px;">TOTAL OMZET BERSIH (NET)</td>
                    <td class="right bold" style="font-size: 11.5px;">:
                        {{ CurrencyFormatter::rupiah($totalNetAmount) }}</td>
                </tr>
            </table>

            <div class="dashed-line"></div>

            {{-- Tanda Tangan 2 Kolom --}}
            <div class="signatures">
                <div class="sig-block">
                    <div class="sig-title">Disiapkan Oleh (Kasir / Petugas),</div>
                    <div class="sig-space"></div>
                    <div class="sig-name">( {{ $currentUser->name }} )</div>
                </div>
                <div class="sig-block">
                    <div class="sig-title">Diketahui Oleh (Kepala Toko / Supervisor),</div>
                    <div class="sig-space"></div>
                    <div class="sig-name">(<span
                            class="sig-dots">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>)
                    </div>
                </div>
            </div>

            <div style="font-size: 9px; color: #555; text-align: center; margin-top: 15px;">
                Dokumen ini dicetak otomatis dari Aplikasi GudangToko POS &bull; Format Continuous Form Epson LX-310
            </div>
        @endif
    </div>

    <script>
        (() => {
            const printBtn = document.getElementById('btn-do-print');
            if (printBtn) {
                printBtn.focus();
            }

            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    window.close();
                } else if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                    e.preventDefault();
                    window.print();
                }
            });
        })();
    </script>
</body>

</html>
