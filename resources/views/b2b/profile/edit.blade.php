@extends('layouts.metronic.app')

@section('title', 'Profil Usaha')
@section('page_title', 'Profil Usaha')

@section('content')
    <x-metronic.page-title title="Profil Usaha dan Alamat" description="Kelola data usaha, alamat kirim, dan lihat status kontrak langganan." />
    <form method="POST" action="{{ route('langganan.profil.update') }}">
        @csrf @method('PUT')
        <div class="row g-5">
            <div class="col-lg-7"><x-metronic.card title="Data Usaha">
                <div class="row">
                    <div class="col-md-6"><x-metronic.form-group name="business_name" label="Nama Usaha" required><input name="business_name" value="{{ old('business_name', $customer->business_name) }}" class="form-control" @disabled(!auth()->user()->hasRole('langganan_owner'))></x-metronic.form-group></div>
                    <div class="col-md-6"><x-metronic.form-group name="pic_name" label="PIC"><input name="pic_name" value="{{ old('pic_name', $customer->pic_name) }}" class="form-control" @disabled(!auth()->user()->hasRole('langganan_owner'))></x-metronic.form-group></div>
                    <div class="col-md-6"><x-metronic.form-group name="whatsapp_number" label="Nomor WhatsApp"><input name="whatsapp_number" value="{{ old('whatsapp_number', $customer->whatsapp_number) }}" class="form-control" @disabled(!auth()->user()->hasRole('langganan_owner'))></x-metronic.form-group></div>
                    <div class="col-md-6"><x-metronic.form-group name="email" label="Email Usaha"><input name="email" value="{{ old('email', $customer->email) }}" class="form-control" @disabled(!auth()->user()->hasRole('langganan_owner'))></x-metronic.form-group></div>
                    <div class="col-md-8"><x-metronic.form-group name="business_address" label="Alamat Usaha"><textarea name="business_address" class="form-control" @disabled(!auth()->user()->hasRole('langganan_owner'))>{{ old('business_address', $customer->business_address) }}</textarea></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="city" label="Kota"><input name="city" value="{{ old('city', $customer->city) }}" class="form-control" @disabled(!auth()->user()->hasRole('langganan_owner'))></x-metronic.form-group></div>
                </div>
            </x-metronic.card></div>
            <div class="col-lg-5"><x-metronic.card title="Status Langganan">
                <div class="mb-3">Ring Harga: <span class="fw-bold">{{ $customer->price_category }}</span></div>
                <div class="mb-3">Termin: <span class="fw-bold">{{ $customer->payment_term_days }} hari</span></div>
                <div class="mb-3">Limit: <span class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($customer->credit_limit) }}</span></div>
                <div class="mb-3">Piutang: <span class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($customer->receivable_balance) }}</span></div>
                <x-metronic.status-badge :status="$customer->account_status->value" :label="$customer->account_status->label()" />
            </x-metronic.card></div>
        </div>
        <x-metronic.card title="Alamat Pengiriman" class="mt-5">
            @foreach($customer->addresses->values() as $index => $address)
                <div class="border rounded p-4 mb-4">
                    <input type="hidden" name="addresses[{{ $index }}][id]" value="{{ $address->id }}">
                    <label class="form-check form-check-custom form-check-solid mb-3"><input class="form-check-input" type="radio" name="primary_address_index" value="{{ $index }}" @checked($address->is_primary) @disabled(!auth()->user()->hasRole('langganan_owner'))><span class="form-check-label">Alamat utama</span></label>
                    <div class="row">
                        <div class="col-md-3"><input name="addresses[{{ $index }}][label]" value="{{ $address->label }}" class="form-control mb-3" placeholder="Label" @disabled(!auth()->user()->hasRole('langganan_owner'))></div>
                        <div class="col-md-3"><input name="addresses[{{ $index }}][recipient_name]" value="{{ $address->recipient_name }}" class="form-control mb-3" placeholder="Penerima" @disabled(!auth()->user()->hasRole('langganan_owner'))></div>
                        <div class="col-md-3"><input name="addresses[{{ $index }}][phone_number]" value="{{ $address->phone_number }}" class="form-control mb-3" placeholder="Telepon" @disabled(!auth()->user()->hasRole('langganan_owner'))></div>
                        <div class="col-md-3"><input name="addresses[{{ $index }}][city]" value="{{ $address->city }}" class="form-control mb-3" placeholder="Kota" @disabled(!auth()->user()->hasRole('langganan_owner'))></div>
                        <div class="col-md-9"><textarea name="addresses[{{ $index }}][address]" class="form-control mb-3" placeholder="Alamat" @disabled(!auth()->user()->hasRole('langganan_owner'))>{{ $address->address }}</textarea></div>
                        <div class="col-md-3"><input name="addresses[{{ $index }}][postal_code]" value="{{ $address->postal_code }}" class="form-control mb-3" placeholder="Kode Pos" @disabled(!auth()->user()->hasRole('langganan_owner'))></div>
                    </div>
                </div>
            @endforeach
            @role('langganan_owner')
                @php($new = $customer->addresses->count())
                <div class="border rounded p-4">
                    <div class="fw-bold mb-3">Alamat Baru</div>
                    <div class="row"><div class="col-md-3"><input name="addresses[{{ $new }}][label]" class="form-control mb-3" placeholder="Label"></div><div class="col-md-3"><input name="addresses[{{ $new }}][recipient_name]" class="form-control mb-3" placeholder="Penerima"></div><div class="col-md-3"><input name="addresses[{{ $new }}][phone_number]" class="form-control mb-3" placeholder="Telepon"></div><div class="col-md-3"><input name="addresses[{{ $new }}][city]" class="form-control mb-3" placeholder="Kota"></div><div class="col-md-9"><textarea name="addresses[{{ $new }}][address]" class="form-control mb-3" placeholder="Alamat"></textarea></div><div class="col-md-3"><input name="addresses[{{ $new }}][postal_code]" class="form-control mb-3" placeholder="Kode Pos"></div></div>
                </div>
                @slot('footer')<button class="btn btn-primary">Simpan Profil</button>@endslot
            @else
                @slot('footer')<span class="text-muted">Staff hanya dapat melihat profil. Perubahan dilakukan oleh owner langganan.</span>@endslot
            @endrole
        </x-metronic.card>
    </form>
    <x-metronic.card title="User Staff Customer" class="mt-5">
        @foreach($customer->users as $user)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ $user->name }} · {{ $user->email }}</span><span class="badge badge-light-primary">{{ $user->pivot->role }}</span></div>@endforeach
    </x-metronic.card>
@endsection
