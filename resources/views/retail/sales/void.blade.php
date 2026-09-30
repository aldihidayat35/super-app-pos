@extends('layouts.metronic.app')

@section('title', 'Pembatalan Transaksi POS - ' . config('app.name'))
@section('page_title', 'Pembatalan Transaksi')

@section('content')
    <x-metronic.card title="Pembatalan Transaksi {{ $sale->number }}">
        <div class="alert alert-warning">Pembatalan tidak menghapus transaksi asli. Sistem membuat pengembalian stok dan mengubah status transaksi.</div>
        <form method="POST" action="{{ route('retail.sales.void.store', $sale) }}">
            @csrf
            <x-metronic.form-group name="reason" label="Alasan Pembatalan" required>
                <textarea name="reason" rows="4" class="form-control" required></textarea>
            </x-metronic.form-group>
            <button class="btn btn-danger" data-confirm="Pembatalan akan mengembalikan stok dan mengunci transaksi. Lanjutkan?">Batalkan Transaksi</button>
        </form>
    </x-metronic.card>
@endsection
