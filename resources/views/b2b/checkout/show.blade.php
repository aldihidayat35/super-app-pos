@extends('layouts.metronic.app')

@section('title', 'Checkout Order B2B')
@section('page_title', 'Checkout Order B2B')

@section('content')
    <x-metronic.page-title title="Checkout Order B2B" description="Validasi alamat, pembayaran, stok indikatif, minimum order, dan limit sebelum order dikirim ke gudang.">
        <a href="{{ route('langganan.keranjang.index') }}" class="btn btn-light">Kembali ke Keranjang</a>
    </x-metronic.page-title>
    <div class="row g-5">
        <div class="col-lg-8">
            <x-metronic.card title="Data Checkout">
                <form method="POST" action="{{ route('langganan.checkout.store') }}" id="checkout-form" class="row g-3">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <div class="col-md-6">
                        <x-metronic.form-group name="customer_address_id" label="Alamat Pengiriman">
                            <select name="customer_address_id" class="form-select">
                                <option value="">Alamat utama/usaha</option>
                                @foreach($customer->addresses as $address)
                                    <option value="{{ $address->id }}">{{ $address->label }} — {{ $address->recipient_name }} — {{ $address->city }}</option>
                                @endforeach
                            </select>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-6">
                        <x-metronic.form-group name="requested_delivery_date" label="Tanggal Harapan Kirim">
                            <input type="date" name="requested_delivery_date" value="{{ old('requested_delivery_date') }}" class="form-control">
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-4">
                        <x-metronic.form-group name="delivery_method" label="Metode Pengiriman" required>
                            <select name="delivery_method" class="form-select" required>
                                <option value="courier">Kurir Internal</option>
                                <option value="pickup">Ambil Sendiri</option>
                                <option value="expedition">Ekspedisi</option>
                            </select>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-4">
                        <x-metronic.form-group name="courier_name" label="Kurir/Ekspedisi">
                            <input name="courier_name" value="{{ old('courier_name') }}" class="form-control" placeholder="Opsional">
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-4">
                        <x-metronic.form-group name="payment_preference" label="Pembayaran" required>
                            <select name="payment_preference" class="form-select" required>
                                <option value="credit">Tempo/Kredit</option>
                                <option value="transfer">Transfer</option>
                                <option value="cash">Tunai</option>
                            </select>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-12">
                        <x-metronic.form-group name="notes" label="Catatan Order">
                            <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-12">
                        <label class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="terms_accepted" value="1" required>
                            <span class="form-check-label">Saya menyetujui syarat order, validasi stok, harga snapshot, dan proses konfirmasi gudang.</span>
                        </label>
                        @error('terms_accepted')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </form>
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            <x-metronic.card title="Ringkasan">
                <div class="mb-3">Minimum Order: <span class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($customer->minimum_order) }}</span></div>
                <div class="mb-3">Limit: <span class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($customer->credit_limit) }}</span></div>
                <div class="mb-3">Piutang: <span class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($customer->receivable_balance) }}</span></div>
                <div class="separator my-4"></div>
                @foreach($cart->items as $item)
                    <div class="d-flex justify-content-between mb-2"><span>{{ $item->product->name }} × {{ qty($item->quantity) }}</span><span>{{ App\Support\CurrencyFormatter::rupiah($item->line_total) }}</span></div>
                @endforeach
                <div class="separator my-4"></div>
                <div class="d-flex justify-content-between fs-4 fw-bold"><span>Total</span><span>{{ App\Support\CurrencyFormatter::rupiah($totals['grand_total']) }}</span></div>
                <button form="checkout-form" class="btn btn-primary w-100 mt-5">Konfirmasi Order</button>
            </x-metronic.card>
        </div>
    </div>
@endsection
