@extends('layouts.metronic.app')

@section('title', 'Void Transaksi POS - ' . config('app.name'))
@section('page_title', 'Void/Pembatalan Transaksi')

@section('content')
    <x-metronic.card title="Void {{ $sale->number }}">
        <div class="alert alert-warning">Void tidak menghapus transaksi asli. Sistem membuat reversal stok dan mengubah status transaksi.</div>
        <form method="POST" action="{{ route('retail.sales.void.store', $sale) }}">
            @csrf
            <x-metronic.form-group name="reason" label="Alasan Void" required>
                <textarea name="reason" rows="4" class="form-control" required></textarea>
            </x-metronic.form-group>
            <button class="btn btn-danger" data-confirm="Void akan mengembalikan stok dan mengunci transaksi. Lanjutkan?">Ajukan / Proses Void</button>
        </form>
    </x-metronic.card>
@endsection
