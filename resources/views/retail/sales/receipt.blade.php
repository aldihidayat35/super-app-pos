<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $sale->number }}</title>
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
                size: {{ $is58mm ? '58mm' : '80mm' }} auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <button type="button" id="btn-do-print" class="btn-print" onclick="window.print()">🖨️ Cetak Struk [Enter]</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup [ESC]</button>
    </div>

    <div class="receipt-container">
        {{-- Header Toko --}}
        @if($settings['print_show_store_header'])
            <div class="center">
                <span class="bold" style="font-size: 13px;">{{ $sale->branch?->name ?? $companyName }}</span><br>
                @if($sale->branch?->address)
                    <span>{{ $sale->branch->address }}</span><br>
                @endif
                @if($sale->branch?->phone)
                    <span>Telp: {{ $sale->branch->phone }}</span>
                @endif
            </div>
            <div class="line"></div>
        @endif

        {{-- Meta Transaksi --}}
        <div class="row">
            <span>No: {{ $sale->number }}</span>
            <span>{{ $sale->completed_at?->format('d/m/y H:i') ?? date('d/m/y H:i') }}</span>
        </div>

        @if($settings['print_show_cashier_name'] || ($settings['print_show_customer_name'] && $sale->customer))
            <div class="row">
                @if($settings['print_show_cashier_name'])
                    <span>Kasir: {{ $sale->cashier?->name ?? 'Kasir' }}</span>
                @endif
                @if($settings['print_show_customer_name'] && $sale->customer)
                    <span>Plg: {{ $sale->customer->business_name ?? $sale->customer->name }}</span>
                @endif
            </div>
        @endif

        @php
            $sale->loadMissing(['returns.items']);
            $totalRefundAmount = '0.00';
            if ($sale->relationLoaded('returns')) {
                $totalRefundAmount = (string) $sale->returns->where('status.value', 'completed')->sum('refund_amount');
            }
            $isReturnedSale = (float) $totalRefundAmount > 0 || $sale->status->value === 'returned';
        @endphp

        @if($isReturnedSale)
            <div class="row" style="margin-top: 3px;">
                <span class="bold" style="color: #b91c1c; font-size: {{ $is58mm ? '9px' : '10px' }};">** STRUK REVISI (TERDAPAT RETUR) **</span>
            </div>
        @endif

        <div class="line"></div>

        {{-- Daftar Barang --}}
        @php
            $hasAnyItemReturn = false;
        @endphp
        @foreach($sale->items as $item)
            @php
                $returnedQty = (float) ($item->returned_quantity ?? 0);
                if ($returnedQty > 0) {
                    $hasAnyItemReturn = true;
                }
                $netQty = max(0, (float) $item->quantity - $returnedQty);
                $unitPrice = (float) $item->selected_price;
                $returnedSubtotal = $returnedQty * $unitPrice;
                $netSubtotal = max(0, (float) $item->line_total - $returnedSubtotal);
            @endphp
            <div class="item-row">
                <div class="bold">{{ $item->product_name_snapshot ?? $item->product?->name }}</div>
                @if($settings['print_show_item_barcode'] && ($item->product?->barcode || $item->product?->sku))
                    <div class="barcode-text">{{ $item->product?->barcode ?? $item->product?->sku }}</div>
                @endif
                <div class="row">
                    <span>{{ qty($item->quantity) }} {{ $item->unit?->code ?? '' }} x {{ number_format($unitPrice, 0, ',', '.') }}</span>
                    <span class="bold">{{ number_format((float) $item->line_total, 0, ',', '.') }}</span>
                </div>
                @if($returnedQty > 0)
                    <div class="row" style="color: #b91c1c; font-size: {{ $is58mm ? '9px' : '10px' }}; padding-left: 6px;">
                        <span>&bull; Retur: -{{ qty($returnedQty) }} {{ $item->unit?->code ?? '' }}</span>
                        <span>-{{ number_format($returnedSubtotal, 0, ',', '.') }}</span>
                    </div>
                    <div class="row" style="font-weight: bold; font-size: {{ $is58mm ? '9px' : '10px' }}; padding-left: 6px;">
                        <span>Netto: {{ qty($netQty) }} {{ $item->unit?->code ?? '' }}</span>
                        <span>{{ number_format($netSubtotal, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="line"></div>

        {{-- Subtotal & Diskon --}}
        <div class="row">
            <span>Subtotal</span>
            <span>{{ number_format((float) $sale->subtotal_amount, 0, ',', '.') }}</span>
        </div>
        @if((float) $sale->discount_amount > 0)
            <div class="row">
                <span>Diskon / Potongan</span>
                <span>-{{ number_format((float) $sale->discount_amount, 0, ',', '.') }}</span>
            </div>
        @endif
        @if((float) $sale->tax_amount > 0)
            <div class="row">
                <span>Pajak (PPN)</span>
                <span>{{ number_format((float) $sale->tax_amount, 0, ',', '.') }}</span>
            </div>
        @endif

        @if($isReturnedSale || $hasAnyItemReturn)
            @php
                $netGrandTotal = max(0, (float) $sale->grand_total_amount - (float) $totalRefundAmount);
            @endphp
            <div class="double-line"></div>
            <div class="row">
                <span>Total Asli</span>
                <span>Rp {{ number_format((float) $sale->grand_total_amount, 0, ',', '.') }}</span>
            </div>
            <div class="row" style="color: #b91c1c;">
                <span>Total Pengembalian (Retur)</span>
                <span>-Rp {{ number_format((float) $totalRefundAmount, 0, ',', '.') }}</span>
            </div>
            <div class="line"></div>
            <div class="row bold" style="font-size: 13px;">
                <span>TOTAL SETELAH RETUR</span>
                <span>Rp {{ number_format($netGrandTotal, 0, ',', '.') }}</span>
            </div>
        @else
            <div class="double-line"></div>

            {{-- Grand Total --}}
            <div class="row bold" style="font-size: 13px;">
                <span>TOTAL</span>
                <span>Rp {{ number_format((float) $sale->grand_total_amount, 0, ',', '.') }}</span>
            </div>
        @endif

        {{-- Rincian Pembayaran --}}
        @if($settings['print_show_payment_details'])
            <div class="line"></div>
            @if($sale->payments && $sale->payments->isNotEmpty())
                @foreach($sale->payments as $payment)
                    <div class="row">
                        <span>Bayar ({{ ucfirst($payment->payment_method?->value ?? $payment->payment_method ?? 'Tunai') }})</span>
                        <span>{{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            @else
                <div class="row">
                    <span>Bayar</span>
                    <span>{{ number_format((float) $sale->paid_amount, 0, ',', '.') }}</span>
                </div>
            @endif

            <div class="row bold">
                <span>Kembali</span>
                <span>{{ number_format((float) $sale->change_amount, 0, ',', '.') }}</span>
            </div>
        @endif

        {{-- Kolom Tanda Tangan jika diaktifkan --}}
        @if($settings['print_show_signatures'])
            <div class="line"></div>
            <div style="display: flex; justify-content: space-between; text-align: center; margin-top: 8px; font-size: {{ $is58mm ? '9px' : '10px' }};">
                <div style="width: 48%; text-align: center;">
                    <div style="white-space: nowrap;">Penerima / Pembeli,</div>
                    <div style="height: 36px;"></div>
                    <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">( ................ )</div>
                </div>
                <div style="width: 48%; text-align: center;">
                    <div style="white-space: nowrap;">Hormat Kami,</div>
                    <div style="height: 36px;"></div>
                    <div class="bold" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">( {{ $sale->cashier?->name ?? 'Kasir' }} )</div>
                </div>
            </div>
        @endif

        {{-- Footer Catatan --}}
        <div class="line"></div>
        <div class="center" style="margin-top: 6px;">
            <span>{{ $settings['print_receipt_footer_note'] ?? 'Terima kasih atas kunjungan Anda' }}</span>
        </div>
    </div>

    <script>
        (() => {
            const printBtn = document.getElementById('btn-do-print');
            if (printBtn) {
                printBtn.focus();
            }

            window.addEventListener('afterprint', () => {
                window.close();
            });

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
