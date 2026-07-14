@extends('layouts.metronic.app')

@section('title', 'Checkout POS - ' . config('app.name'))
@section('page_title', 'Checkout dan Pembayaran')

@section('content')
    <x-metronic.card title="Checkout Manual">
        <div class="alert alert-info">Halaman ini menerima input checkout langsung. Harga kosong akan dihitung otomatis oleh PriceResolver; harga di bawah minimum atau diskon berlebih akan ditolak sampai ada approval.</div>
        <form method="POST" action="{{ route('retail.pos.store') }}" class="row g-3">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) str()->uuid() }}">
            <div class="col-md-4"><x-metronic.form-group name="branch_id" label="Cabang/Toko" required><select name="branch_id" class="form-select">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="customer_id" label="Pelanggan"><select name="customer_id" class="form-select"><option value="">Umum</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->business_name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="payments.0.method" label="Metode Bayar" required><select name="payments[0][method]" class="form-select">@foreach($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="items.0.product_id" label="ID Produk" required><input name="items[0][product_id]" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="items.0.unit_id" label="ID Unit"><input name="items[0][unit_id]" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="items.0.quantity" label="Qty" required><input type="number" step="0.0001" min="0.0001" name="items[0][quantity]" value="1" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="items.0.selected_price" label="Harga"><input type="number" step="0.01" min="0" name="items[0][selected_price]" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="items.0.discount_percent" label="Diskon %"><input type="number" step="0.01" min="0" max="100" name="items[0][discount_percent]" value="0" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="payments.0.amount" label="Nominal Bayar" required><input type="number" step="0.01" min="0" name="payments[0][amount]" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="payments.0.reference_no" label="Referensi"><input name="payments[0][reference_no]" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-12"><x-metronic.form-group name="notes" label="Catatan"><textarea name="notes" rows="2" class="form-control"></textarea></x-metronic.form-group></div>
            <div class="col-md-12"><button class="btn btn-primary">Simpan Checkout</button></div>
        </form>
    </x-metronic.card>
@endsection
