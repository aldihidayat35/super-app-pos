@extends('layouts.metronic.app')

@section('title', 'Detail Penjualan POS - ' . config('app.name'))
@section('page_title', 'Detail Penjualan dan Struk')

@section('toolbar_actions')
    <a href="{{ route('retail.sales.print', $sale) }}" target="_blank" class="btn btn-light-primary"><i class="ki-outline ki-receipt-square"></i> Cetak Struk (Thermal)</a>
    <a href="{{ route('retail.sales.print', [$sale, 'format' => 'dotmatrix']) }}" target="_blank" class="btn btn-light-success"><i class="ki-outline ki-document"></i> Cetak Faktur (LX-310)</a>
    @can('void', $sale)<a href="{{ route('retail.sales.void', $sale) }}" class="btn btn-light-danger">Batalkan</a>@endcan
    @can('return', $sale)<a href="{{ route('retail.sales.return', $sale) }}" class="btn btn-light-warning">Retur</a>@endcan
@endsection

@section('content')
    @php($canSensitive = auth()->user()?->can('margins.view_sensitive'))
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

                <div class="mb-3"><div class="text-muted">Nomor</div><div class="fw-bold">{{ $sale->number }}</div></div>
                <div class="mb-3"><div class="text-muted">Status</div><x-metronic.status-badge :status="$sale->status" /></div>
                <div class="mb-3"><div class="text-muted">Waktu</div>{{ $sale->completed_at?->format('d/m/Y H:i') }}</div>
                <div class="mb-3"><div class="text-muted">Pelanggan</div>{{ $sale->customer?->business_name ?? 'Umum' }}</div>
                
                <div class="separator my-4"></div>

                <div class="mb-3">
                    <div class="text-muted">Subtotal</div>
                    <div class="fw-bold">Rp {{ number_format((float) $sale->subtotal_amount, 0, ',', '.') }}</div>
                </div>
                @if($sale->discount_amount > 0)
                <div class="mb-3">
                    <div class="text-muted">Diskon / Potongan</div>
                    <div class="fw-bold text-danger">- Rp {{ number_format((float) $sale->discount_amount, 0, ',', '.') }}</div>
                </div>
                @endif
                <div class="mb-3">
                    <div class="text-muted">Grand Total</div>
                    <div class="fs-1 fw-bolder text-success">Rp {{ number_format((float) $sale->grand_total_amount, 0, ',', '.') }}</div>
                </div>
                
                <div class="separator my-4"></div>

                <div class="mb-3">
                    <div class="text-muted">Bayar</div>
                    <div class="fw-bold text-primary">Rp {{ number_format((float) $sale->paid_amount, 0, ',', '.') }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted">Kembali</div>
                    <div class="fw-bold text-warning">Rp {{ number_format((float) $sale->change_amount, 0, ',', '.') }}</div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Item">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Qty</th>@if($canSensitive)<th>HPP/Margin</th>@endif<th>Harga</th><th>Total</th></tr></thead>
                        <tbody>
                        @foreach($sale->items as $item)
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
                                            <span class="text-gray-800 fw-bold">{{ $item->product_name_snapshot }}</span>
                                            <span class="text-muted fs-7">{{ $item->sku_snapshot }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ qty($item->quantity) }} {{ $item->unit_name_snapshot }}<div class="text-muted fs-7 mt-1">Base {{ qty($item->base_quantity) }}</div>
                                    @foreach($item->allocations as $allocation)
                                        <div class="text-muted fs-7">{{ $allocation->source === 'normal' ? 'Stok toko' : 'Darurat' }}: {{ qty($allocation->base_quantity) }}
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
                                <td class="fw-bolder text-gray-800 text-end">Rp {{ number_format((float) $item->line_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>
            <x-metronic.card title="Pembayaran & Mutasi" class="mt-6">
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
