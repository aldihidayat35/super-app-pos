@extends('layouts.metronic.app')

@section('title', 'Buat Order Sales')
@section('page_title', 'Buat Order Sales')
@section('content')
    <x-metronic.page-title title="Buat Order B2B" description="Harga, minimum order, limit kredit, dan alur fulfillment menggunakan modul B2B existing." />
    @if($errors->has('order'))<div class="alert alert-danger">{{ $errors->first('order') }}</div>@endif
    <form method="POST" action="{{ route('sales.orders.store') }}">@csrf
        <x-metronic.card title="Customer & Pengiriman" class="mb-5">
            <div class="row g-4">
                <div class="col-md-6">
                    <x-metronic.form-group name="customer_id" label="Customer" required>
                        <select name="customer_id" id="customer_id" class="form-select" required>
                            <option value="">Pilih customer</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $selectedCustomerId) === (string) $customer->id)>{{ $customer->business_name }} — {{ $customer->code }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-6">
                    <x-metronic.form-group name="customer_address_id" label="Alamat Pengiriman">
                        <select name="customer_address_id" id="customer_address_id" class="form-select">
                            <option value="">Alamat utama/usaha customer</option>
                            @foreach($customers as $customer)
                                @foreach($customer->addresses as $address)
                                    <option value="{{ $address->id }}" data-customer="{{ $customer->id }}" @selected((string) old('customer_address_id') === (string) $address->id)>{{ $address->label }} — {{ Str::limit($address->address, 70) }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="requested_delivery_date" label="Tanggal Kirim">
                        <input type="date" min="{{ now()->toDateString() }}" name="requested_delivery_date" value="{{ old('requested_delivery_date') }}" class="form-control">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="delivery_method" label="Metode Pengiriman" required>
                        <select name="delivery_method" class="form-select">
                            <option value="courier" @selected(old('delivery_method', 'courier') === 'courier')>Kurir</option>
                            <option value="expedition" @selected(old('delivery_method') === 'expedition')>Ekspedisi</option>
                            <option value="pickup" @selected(old('delivery_method') === 'pickup')>Ambil Sendiri</option>
                        </select>
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="payment_preference" label="Pembayaran" required>
                        <select name="payment_preference" class="form-select">
                            <option value="credit" @selected(old('payment_preference', 'credit') === 'credit')>Kredit</option>
                            <option value="transfer" @selected(old('payment_preference') === 'transfer')>Transfer</option>
                            <option value="cash" @selected(old('payment_preference') === 'cash')>Tunai</option>
                        </select>
                    </x-metronic.form-group>
                </div>
            </div>
        </x-metronic.card>
        <x-metronic.card title="Item Order" class="mb-5">
            <div id="order-items">
                @foreach(old('items', [['product_id' => '', 'quantity' => 1]]) as $index => $item)
                    <div class="row g-3 align-items-end order-item mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Produk</label>
                            <select name="items[{{ $index }}][product_id]" class="form-select" required>
                                <option value="">Pilih produk</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>{{ $product->sku }} — {{ $product->name }} ({{ $product->baseUnit?->symbol }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Qty</label><input type="number" min="0.0001" step="0.0001" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" class="form-control" required></div>
                        <div class="col-md-1"><button type="button" class="btn btn-light-danger remove-item">×</button></div>
                    </div>
                @endforeach
            </div>
            <button type="button" id="add-item" class="btn btn-sm btn-light-primary">Tambah Item</button>
        </x-metronic.card>
        <x-metronic.card title="Konfirmasi"><x-metronic.form-group name="notes" label="Catatan"><textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea></x-metronic.form-group><label class="form-check form-check-custom form-check-solid"><input class="form-check-input" type="checkbox" name="terms_accepted" value="1" required @checked(old('terms_accepted'))><span class="form-check-label">Saya memastikan data order benar dan menyetujui syarat transaksi B2B.</span></label></x-metronic.card>
        <div class="d-flex justify-content-end gap-3 mt-5"><a href="{{ route('sales.orders.index') }}" class="btn btn-light">Batal</a><button class="btn btn-primary">Kirim Order</button></div>
    </form>

    <template id="item-template">
        <div class="row g-3 align-items-end order-item mb-3">
            <div class="col-md-8">
                <label class="form-label">Produk</label>
                <select class="form-select product-input" required>
                    <option value="">Pilih produk</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }} ({{ $product->baseUnit?->symbol }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Qty</label><input type="number" min="0.0001" step="0.0001" value="1" class="form-control quantity-input" required></div>
            <div class="col-md-1"><button type="button" class="btn btn-light-danger remove-item">×</button></div>
        </div>
    </template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const customer = document.getElementById('customer_id');
    const address = document.getElementById('customer_address_id');
    const syncAddresses = () => Array.from(address.options).forEach((option, index) => {
        const visible = index === 0 || option.dataset.customer === customer.value;
        option.hidden = !visible;
        option.disabled = !visible;
        if (!visible && option.selected) address.value = '';
    });
    customer.addEventListener('change', syncAddresses);
    syncAddresses();

    let index = document.querySelectorAll('.order-item').length;
    document.getElementById('add-item').addEventListener('click', function () {
        const node = document.getElementById('item-template').content.cloneNode(true);
        node.querySelector('.product-input').name = `items[${index}][product_id]`;
        node.querySelector('.quantity-input').name = `items[${index}][quantity]`;
        document.getElementById('order-items').appendChild(node);
        index++;
    });
    document.getElementById('order-items').addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-item') && document.querySelectorAll('.order-item').length > 1) event.target.closest('.order-item').remove();
    });
});
</script>
@endpush
