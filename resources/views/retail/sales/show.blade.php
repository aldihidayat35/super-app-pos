@extends('layouts.metronic.app')

@section('title', 'Detail Penjualan POS - ' . config('app.name'))
@section('page_title', 'Detail Penjualan dan Struk')

@section('toolbar_actions')
    <a href="{{ route('retail.sales.print', $sale) }}" target="_blank" class="btn btn-light-primary"><i class="ki-outline ki-receipt-square"></i> Cetak Struk (Thermal)</a>
    <a href="{{ route('retail.sales.print', [$sale, 'format' => 'dotmatrix']) }}" target="_blank" class="btn btn-light-success"><i class="ki-outline ki-document"></i> Cetak Faktur (LX-310)</a>
    @if($sale->returns->isNotEmpty())
        <a href="{{ route('retail.sales.return-receipt', $sale->returns->last()) }}" target="_blank" class="btn btn-light-warning"><i class="ki-outline ki-arrows-loop"></i> Cetak Struk Retur</a>
    @endif
    @can('void', $sale)<a href="{{ route('retail.sales.void', $sale) }}" class="btn btn-light-danger">Batalkan</a>@endcan
    @can('return', $sale)<a href="{{ route('retail.sales.return', $sale) }}" class="btn btn-light-warning">Retur</a>@endcan
@endsection

@section('content')
    @php
        $canSensitive = auth()->user()?->can('margins.view_sensitive');
        $totalRefundAmount = (float) $sale->returns->sum('refund_amount');
        $totalReturnedQty = (float) $sale->items->sum('returned_quantity');
        $hasReturn = $totalRefundAmount > 0 || $totalReturnedQty > 0 || $sale->status->value === 'returned';
        $originalGrandTotal = (float) $sale->grand_total_amount;
        $netGrandTotal = max(0, $originalGrandTotal - $totalRefundAmount);
        $totalPurchasedQty = (float) $sale->items->sum('quantity');
        $isFullyReturned = $hasReturn && ($netGrandTotal <= 0 || $totalPurchasedQty <= $totalReturnedQty);
        $isPartiallyReturned = $hasReturn && ! $isFullyReturned;
    @endphp
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Ringkasan">
                <div class="mb-4">
                    <div class="text-muted mb-1">Kasir</div>
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40px symbol-circle me-3">
                            @if($sale->cashier?->avatar_url)
                                <img src="{{ $sale->cashier->avatar_url }}" alt="{{ $sale->cashier->name }}">
                            @else
                                <div class="symbol-label bg-light-primary text-primary fs-5 fw-bolder">
                                    {{ substr($sale->cashier?->name ?? '?', 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <div class="d-flex flex-column">
                            <span class="text-gray-800 fw-bold fs-6">{{ $sale->cashier?->name ?? 'Sistem' }}</span>
                            <span class="text-muted fs-7">{{ $sale->branch?->name }}</span>
                        </div>
                    </div>
                </div>

                <div class="mb-3"><div class="text-muted">Nomor Nota</div><div class="fw-bold fs-6">{{ $sale->number }}</div></div>
                <div class="mb-3">
                    <div class="text-muted">Status Transaksi</div>
                    @if($sale->status->value === 'void_approved')
                        <span class="badge badge-light-danger fs-7">Dibatalkan (Void)</span>
                    @elseif($isFullyReturned)
                        <span class="badge badge-light-danger fs-7">
                            <i class="ki-outline ki-arrows-loop text-danger me-1"></i>Diretur Penuh
                        </span>
                    @elseif($isPartiallyReturned)
                        <span class="badge badge-light-warning fs-7">
                            <i class="ki-outline ki-arrows-loop text-warning me-1"></i>Diretur Sebagian
                        </span>
                    @elseif($sale->status->value === 'completed')
                        <span class="badge badge-light-success fs-7">Selesai</span>
                    @else
                        <x-metronic.status-badge :status="$sale->status" />
                    @endif
                </div>
                <div class="mb-3"><div class="text-muted">Waktu Transaksi</div>{{ $sale->completed_at?->format('d/m/Y H:i') }}</div>
                <div class="mb-3"><div class="text-muted">Pelanggan</div>{{ $sale->customer?->business_name ?? 'Umum' }}</div>
                
                <div class="separator my-4"></div>

                <div class="mb-3">
                    <div class="text-muted">Subtotal Asli</div>
                    <div class="fw-bold">Rp {{ number_format((float) $sale->subtotal_amount, 0, ',', '.') }}</div>
                </div>
                @if($sale->discount_amount > 0)
                <div class="mb-3">
                    <div class="text-muted">Diskon / Potongan</div>
                    <div class="fw-bold text-danger">- Rp {{ number_format((float) $sale->discount_amount, 0, ',', '.') }}</div>
                </div>
                @endif
                @if((float) $sale->tax_amount > 0)
                <div class="mb-3">
                    <div class="text-muted">Pajak (PPN)</div>
                    <div class="fw-bold">Rp {{ number_format((float) $sale->tax_amount, 0, ',', '.') }}</div>
                </div>
                @endif
                <div class="mb-3">
                    <div class="text-muted">Total Pembelian Awal</div>
                    <div class="fs-4 fw-bold {{ $hasReturn ? 'text-decoration-line-through text-muted' : 'text-gray-900' }}">
                        Rp {{ number_format((float) $sale->grand_total_amount, 0, ',', '.') }}
                    </div>
                </div>

                @if($hasReturn)
                    <div class="p-3 bg-light-danger rounded border border-danger border-dashed mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-danger fw-bold fs-7">Dana Direfund (Retur):</span>
                            <span class="text-danger fw-bolder fs-6">- Rp {{ number_format($totalRefundAmount, 0, ',', '.') }}</span>
                        </div>
                        <div class="text-muted fs-8">Sebanyak {{ $sale->returns->count() }} kali transaksi retur</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted">Total Bersih (Netto Akhir)</div>
                        <div class="fs-1 fw-bolder text-success">Rp {{ number_format($netGrandTotal, 0, ',', '.') }}</div>
                    </div>
                @endif
                
                <div class="separator my-4"></div>

                <div class="mb-3">
                    <div class="text-muted">Jumlah Bayar Asli</div>
                    <div class="fw-bold text-primary">Rp {{ number_format((float) $sale->paid_amount, 0, ',', '.') }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted">Kembalian Awal</div>
                    <div class="fw-bold text-warning">Rp {{ number_format((float) $sale->change_amount, 0, ',', '.') }}</div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Item Pembelian & Status Retur">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase fs-7">
                                <th>Produk</th>
                                <th>Qty</th>
                                @if($canSensitive)<th>HPP/Margin</th>@endif
                                <th>Harga Satuan</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($sale->items as $item)
                            @php
                                $itemQty = (float) $item->quantity;
                                $retQty = (float) ($item->returned_quantity ?? 0);
                                $netQty = max(0, $itemQty - $retQty);
                                $uPrice = (float) $item->selected_price;
                                $origTotal = (float) $item->line_total;
                                $retTotal = $retQty * $uPrice;
                                $netTotal = max(0, $origTotal - $retTotal);
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-45px me-3">
                                            <x-metronic.image-preview 
                                                url="{{ $item->product?->main_image_url ?? \App\Models\Product::defaultImageUrl() }}" 
                                                title="{{ $item->product_name_snapshot }}" 
                                                size="45px" 
                                                classes="rounded object-fit-cover border border-gray-200" />
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-900 fw-bold fs-6">{{ $item->product_name_snapshot }}</span>
                                            <span class="text-muted fs-7">{{ $item->sku_snapshot }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-gray-800 fw-semibold">{{ qty($itemQty) }} {{ $item->unit_name_snapshot }}</div>
                                    @if($retQty > 0)
                                        <div class="badge badge-light-danger fs-8 mt-1">
                                            <i class="ki-outline ki-arrows-loop text-danger me-1"></i>Diretur: {{ qty($retQty) }} {{ $item->unit_name_snapshot }}
                                        </div>
                                        <div class="text-gray-900 fw-bold fs-7 mt-1">
                                            Netto: {{ qty($netQty) }} {{ $item->unit_name_snapshot }}
                                        </div>
                                    @endif
                                    <div class="text-muted fs-8 mt-1">Base: {{ qty($item->base_quantity) }}</div>
                                    @foreach($item->allocations as $allocation)
                                        <div class="text-muted fs-8">{{ $allocation->source === 'normal' ? 'Stok toko' : 'Darurat' }}: {{ qty($allocation->base_quantity) }}
                                            @if($allocation->emergencyItem?->purchase && auth()->user()->can('emergency_purchases.view'))
                                                · <a href="{{ route('retail.emergency.show', $allocation->emergencyItem->purchase) }}">{{ $allocation->emergencyItem->purchase->number }}</a>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                                @if($canSensitive)
                                <td>
                                    <span class="text-gray-800">HPP Rp {{ number_format((float) $item->hpp_snapshot, 0, ',', '.') }}</span>
                                    <div class="text-success fw-bold fs-7 mt-1">Margin Rp {{ number_format((float) $item->margin_amount, 0, ',', '.') }}</div>
                                    @foreach($item->allocations->where('source','emergency') as $allocation)
                                        <div class="text-danger fs-7">Biaya darurat Rp {{ number_format((float) $allocation->actual_cogs_amount, 0, ',', '.') }}</div>
                                    @endforeach
                                </td>
                                @endif
                                <td>
                                    <span class="text-gray-800">Rp {{ number_format((float) $item->selected_price, 0, ',', '.') }}</span>
                                    @if($item->discount_percent > 0)
                                    <div class="text-danger fs-7 mt-1">Diskon {{ $item->discount_percent }}%</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($retQty > 0)
                                        <div class="fw-bolder text-gray-900 fs-6">
                                            Rp {{ number_format($netTotal, 0, ',', '.') }}
                                        </div>
                                        <div class="text-muted fs-8 text-decoration-line-through mt-1">
                                            Asli: Rp {{ number_format($origTotal, 0, ',', '.') }}
                                        </div>
                                        <div class="text-danger fs-8 fw-semibold">
                                            - Rp {{ number_format($retTotal, 0, ',', '.') }} (Retur)
                                        </div>
                                    @else
                                        <div class="fw-bolder text-gray-800 fs-6">
                                            Rp {{ number_format($origTotal, 0, ',', '.') }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            {{-- Card Riwayat Retur Dokumen --}}
            <x-metronic.card title="Riwayat Pengembalian Barang (Retur)" class="mt-6">
                @if($sale->returns->isEmpty())
                    <div class="text-muted bg-light rounded p-4 text-center">
                        <i class="ki-outline ki-information-2 fs-2 text-muted me-1"></i>
                        Belum ada riwayat pengembalian barang (retur) untuk transaksi ini.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-7 gy-3">
                            <thead>
                                <tr class="text-muted fw-bold text-uppercase fs-8">
                                    <th>No. Retur</th>
                                    <th>Waktu Retur</th>
                                    <th>Kasir Pemroses</th>
                                    <th>Barang Diretur</th>
                                    <th>Metode Refund</th>
                                    <th class="text-end">Nominal Refund</th>
                                    <th>Alasan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sale->returns as $ret)
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-gray-900">{{ $ret->number }}</span>
                                            <div><span class="badge badge-light-success fs-9 py-0 px-1">{{ $ret->status->label() }}</span></div>
                                        </td>
                                        <td>
                                            {{ ($ret->completed_at ?? $ret->created_at)->format('d/m/Y H:i') }}
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-gray-800">{{ $ret->cashier?->name ?? 'Kasir' }}</div>
                                            @if($ret->cashShift)
                                                <div class="text-muted fs-8">Shift: {{ $ret->cashShift->shift_number }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach($ret->items as $ri)
                                                <div class="mb-1">
                                                    <span class="fw-bold text-gray-800">{{ $ri->product?->name ?? $ri->saleItem?->product_name_snapshot ?? 'Produk' }}</span>
                                                    <span class="badge badge-light-danger fs-9 ms-1">{{ qty($ri->quantity) }} {{ $ri->saleItem?->unit_name_snapshot ?? 'unit' }}</span>
                                                    <span class="text-muted fs-8">(Rp {{ number_format((float) $ri->refund_amount, 0, ',', '.') }})</span>
                                                </div>
                                            @endforeach
                                        </td>
                                        <td>
                                            <span class="badge badge-light-primary">
                                                {{ $ret->refund_method === 'cash' ? 'Tunai (Kas Laci)' : ucfirst($ret->refund_method) }}
                                            </span>
                                        </td>
                                        <td class="text-end fw-bolder text-danger fs-6">
                                            Rp {{ number_format((float) $ret->refund_amount, 0, ',', '.') }}
                                        </td>
                                        <td>
                                            <span class="text-gray-700">{{ $ret->reason ?: 'Tidak ada keterangan' }}</span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('retail.sales.return-receipt', $ret) }}" target="_blank" class="btn btn-sm btn-light-warning btn-icon" title="Cetak Ulang Struk Retur">
                                                <i class="ki-outline ki-printer fs-4"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-metronic.card>

            <x-metronic.card title="Pembayaran & Mutasi Stok" class="mt-6">
                <div class="row g-5">
                    <div class="col-md-6">
                        <div class="fw-bolder fs-5 mb-3 text-gray-800">Metode Pembayaran</div>
                        @foreach($sale->payments as $payment)
                            <div class="d-flex align-items-center mb-2">
                                <span class="bullet bullet-vertical h-20px bg-primary me-2"></span>
                                <div class="flex-grow-1">
                                    <span class="text-gray-800 fw-bold">{{ $payment->method->label() }}</span>
                                </div>
                                <span class="fw-bold text-success">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bolder fs-5 mb-3 text-gray-800">Mutasi Stok</div>
                        @forelse($sale->stockMutations as $mutation)
                            <div class="d-flex align-items-center mb-2">
                                <span class="bullet bullet-vertical h-20px bg-warning me-2"></span>
                                <div class="flex-grow-1">
                                    <span class="text-gray-800">{{ $mutation->product?->sku }}</span>
                                    <span class="text-muted fs-7 ms-1">({{ $mutation->mutation_type->label() }})</span>
                                </div>
                                @php($isMinus = $mutation->quantity_on_hand_change < 0)
                                <span class="fw-bold {{ $isMinus ? 'text-danger' : 'text-success' }}">
                                    {{ $isMinus ? '' : '+' }}{{ qty($mutation->quantity_on_hand_change) }}
                                </span>
                            </div>
                        @empty
                            <div class="text-muted bg-light rounded p-3 text-center">Belum ada mutasi tercatat.</div>
                        @endforelse
                    </div>
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
