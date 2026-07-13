@extends('layouts.metronic.app')
@section('title', $customer->business_name)
@section('page_title', 'Detail Pelanggan')
@section('toolbar_actions')
    @can('manageAccess', $customer)<a href="{{ route('admin.customers.access.edit', $customer) }}" class="btn btn-light">Alamat & User B2B</a>@endcan
    @can('manageSettings', $customer)<a href="{{ route('admin.customers.settings.edit', $customer) }}" class="btn btn-light-primary">Verifikasi/Kredit</a>@endcan
    @can('update', $customer)<a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-primary">Edit Pelanggan</a>@endcan
@endsection
@section('content')
<div class="row g-6">
    <div class="col-lg-4"><x-metronic.card title="Profil Pelanggan"><div class="fw-bold fs-4">{{ $customer->business_name }}</div><div class="text-muted mb-4">{{ $customer->code }}</div><div>Tipe: {{ $customer->type->label() }}</div><div>PIC: {{ $customer->pic_name ?: '-' }}</div><div>WA: {{ $customer->whatsapp_number ?: '-' }}</div><div>Ring: {{ $customer->price_category }}</div><div>Limit: {{ App\Support\CurrencyFormatter::rupiah($customer->credit_limit) }}</div><div>Piutang: {{ App\Support\CurrencyFormatter::rupiah($customer->receivable_balance) }}</div><div class="mt-3"><x-metronic.status-badge :status="$customer->account_status->value" :label="$customer->account_status->label()" /></div></x-metronic.card></div>
    <div class="col-lg-8">
        <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">@foreach(['order'=>'Order','invoice'=>'Invoice','pembayaran'=>'Pembayaran','alamat'=>'Alamat','user'=>'User','dokumen'=>'Dokumen','piutang'=>'Kartu Piutang'] as $key => $label)<li class="nav-item"><a class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" href="#tab_{{ $key }}">{{ $label }}</a></li>@endforeach</ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_order"><x-metronic.card><x-metronic.empty-state title="Order pelanggan akan tampil setelah modul B2B/POS aktif." description="Tab ini disiapkan untuk fase transaksi berikutnya." /></x-metronic.card></div>
            <div class="tab-pane fade" id="tab_alamat"><x-metronic.card>@forelse($customer->addresses as $address)<div class="mb-3"><span class="fw-bold">{{ $address->label }}</span> @if($address->is_primary)<span class="badge badge-light-primary">Utama</span>@endif<div>{{ $address->address }}</div><div class="text-muted">{{ $address->recipient_name }} · {{ $address->phone_number }}</div></div>@empty<x-metronic.empty-state title="Belum ada alamat kirim" description="Kelola alamat dari halaman akses B2B." />@endforelse</x-metronic.card></div>
            <div class="tab-pane fade" id="tab_user"><x-metronic.card>@forelse($customer->users as $user)<div>{{ $user->name }} · {{ $user->email }} · {{ $user->pivot->role }} · {{ $user->pivot->is_active ? 'Aktif' : 'Nonaktif' }}</div>@empty<x-metronic.empty-state title="Belum ada user B2B" description="Kelola user dari halaman akses B2B." />@endforelse</x-metronic.card></div>
            <div class="tab-pane fade" id="tab_dokumen"><x-metronic.card>@forelse($customer->documents as $document)<div>{{ $document->name }} · {{ $document->type }}</div>@empty<x-metronic.empty-state title="Belum ada dokumen" description="Upload dokumen dari halaman verifikasi/kredit." />@endforelse</x-metronic.card></div>
            @foreach(['invoice'=>'Invoice akan tampil setelah modul invoice aktif.','pembayaran'=>'Pembayaran akan tampil setelah modul pembayaran aktif.','piutang'=>'Kartu piutang akan tampil setelah modul piutang aktif.'] as $key => $text)<div class="tab-pane fade" id="tab_{{ $key }}"><x-metronic.card><x-metronic.empty-state title="{{ $text }}" description="Tab disiapkan untuk fase berikutnya." /></x-metronic.card></div>@endforeach
        </div>
    </div>
</div>
@endsection
