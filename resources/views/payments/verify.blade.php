@extends('layouts.metronic.app')

@section('title', 'Verifikasi Pembayaran')
@section('page_title', 'Verifikasi Pembayaran')

@section('content')
    <x-metronic.page-title :title="$payment->number" description="Review bukti dan alokasi invoice." />
    <div class="row g-5">
        <div class="col-lg-7"><x-metronic.card title="Detail Pembayaran">
            <div>Customer: <strong>{{ $payment->customer?->business_name }}</strong></div><div>Nominal: <strong>{{ App\Support\CurrencyFormatter::rupiah($payment->amount) }}</strong></div><div>Metode: {{ $payment->method?->label() }}</div><div>Status: {{ $payment->status?->label() }}</div><div>Ref: {{ $payment->reference_no ?: '-' }}</div>
            @if($proofUrl)<a href="{{ $proofUrl }}" class="btn btn-sm btn-light-primary mt-3">Lihat Bukti Signed URL</a>@endif
            <div class="mt-5">@foreach($payment->allocations as $allocation)<div class="border-bottom py-2">{{ $allocation->invoice?->number }} · {{ App\Support\CurrencyFormatter::rupiah($allocation->amount) }}</div>@endforeach</div>
        </x-metronic.card></div>
        <div class="col-lg-5"><x-metronic.card title="Keputusan">
            <form method="POST" action="{{ route('payments.verify.store', $payment) }}" class="mb-4">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-success w-100">Approve Pembayaran</button></form>
            <form method="POST" action="{{ route('payments.verify.store', $payment) }}">@csrf<input type="hidden" name="decision" value="reject"><textarea name="reject_reason" class="form-control mb-3" placeholder="Alasan penolakan"></textarea><button class="btn btn-light-danger w-100">Reject</button></form>
        </x-metronic.card></div>
    </div>
@endsection
