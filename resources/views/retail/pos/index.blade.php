@extends('layouts.metronic.app')

@section('title', 'Kasir POS - ' . config('app.name'))
@section('page_title', 'Kasir POS')

@section('toolbar_actions')
    <a href="{{ route('retail.pos.holds') }}" class="btn btn-light-warning"><i class="ki-outline ki-time"></i> Transaksi Hold</a>
    <a href="{{ route('retail.pos.checkout') }}" class="btn btn-light-primary"><i class="ki-outline ki-wallet"></i> Checkout Manual</a>
@endsection

@section('content')
    <div class="row g-6">
        <div class="col-xl-7">
            <x-metronic.card title="Scan / Cari Produk">
                <form method="GET" class="row g-3 mb-6">
                    <div class="col-md-9"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-lg" placeholder="Scan barcode, SKU, nama produk, atau kategori favorit"></div>
                    <div class="col-md-3"><button class="btn btn-primary btn-lg w-100">Cari</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Stok Toko</th><th>Unit</th><th>Warning</th></tr></thead>
                        <tbody>
                        @forelse($products as $product)
                            @php($stock = $stocks->get($product->id))
                            <tr>
                                <td class="fw-semibold">{{ $product->sku }}<div class="text-muted">{{ $product->name }}</div></td>
                                <td>{{ $stock?->available_quantity ?? '0.0000' }}</td>
                                <td>{{ $product->baseUnit?->name }}</td>
                                <td>
                                    @if($stock && (float) $stock->available_quantity <= (float) $product->minimum_stock)
                                        <span class="badge badge-light-warning">Stok rendah</span>
                                    @else
                                        <span class="badge badge-light-success">Siap jual</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-metronic.empty-state title="Produk tidak ditemukan" description="Coba scan barcode, SKU, atau nama produk lain." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-xl-5">
            <x-metronic.card title="Keranjang Cepat">
                <form method="POST" action="{{ route('retail.pos.store') }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) str()->uuid() }}">
                    <x-metronic.form-group name="branch_id" label="Cabang/Toko" required>
                        <select name="branch_id" class="form-select">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="customer_id" label="Pelanggan Opsional">
                        <select name="customer_id" class="form-select"><option value="">Umum</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->business_name }}</option>@endforeach</select>
                    </x-metronic.form-group>
                    <div class="border rounded p-4 mb-5">
                        <div class="fw-bold mb-3">Item 1</div>
                        <x-metronic.form-group name="items.0.product_id" label="Produk" required>
                            <select name="items[0][product_id]" class="form-select">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select>
                        </x-metronic.form-group>
                        <div class="row">
                            <div class="col-md-4"><x-metronic.form-group name="items.0.quantity" label="Qty" required><input type="number" step="0.0001" min="0.0001" name="items[0][quantity]" value="1" class="form-control"></x-metronic.form-group></div>
                            <div class="col-md-4"><x-metronic.form-group name="items.0.selected_price" label="Harga"><input type="number" step="0.01" min="0" name="items[0][selected_price]" class="form-control" placeholder="Auto"></x-metronic.form-group></div>
                            <div class="col-md-4"><x-metronic.form-group name="items.0.discount_percent" label="Diskon %"><input type="number" step="0.01" min="0" max="100" name="items[0][discount_percent]" value="0" class="form-control"></x-metronic.form-group></div>
                        </div>
                    </div>
                    <div class="border rounded p-4 mb-5">
                        <div class="fw-bold mb-3">Pembayaran</div>
                        <div class="row">
                            <div class="col-md-5"><select name="payments[0][method]" class="form-select">@foreach($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-7"><input type="number" step="0.01" min="0" name="payments[0][amount]" class="form-control" placeholder="Nominal bayar"></div>
                        </div>
                    </div>
                    <x-metronic.form-group name="notes" label="Catatan">
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </x-metronic.form-group>
                    <div class="d-flex gap-3">
                        <button class="btn btn-primary flex-fill" data-confirm="Checkout akan mengurangi stok toko dan menyimpan pembayaran. Lanjutkan?">Checkout</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('retail.pos.holds.store') }}" class="mt-3">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branches->first()?->id }}">
                    <input type="hidden" name="estimated_total" value="0">
                    <input type="hidden" name="cart_snapshot[manual]" value="Keranjang ditahan dari POS cepat">
                    <button class="btn btn-light-warning w-100">Hold Keranjang</button>
                </form>
            </x-metronic.card>
        </div>
    </div>
@endsection
