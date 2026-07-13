@extends('layouts.metronic.app')
@section('title', 'Alamat dan Pengguna B2B')
@section('page_title', 'Alamat dan Pengguna B2B')
@section('content')
@php
    $addressRows = old('addresses', $customer->addresses->map(fn($address) => $address->only(['id','label','recipient_name','phone_number','address','city','postal_code','directions','is_primary']))->values()->all() ?: [['label'=>'Utama','address'=>$customer->business_address,'is_primary'=>true]]);
    $userRows = old('users', $customer->users->map(fn($user) => ['id'=>$user->id,'name'=>$user->name,'username'=>$user->username,'email'=>$user->email,'role'=>$user->pivot->role,'is_active'=>$user->pivot->is_active,'blocked_reason'=>$user->pivot->blocked_reason])->values()->all() ?: [['role'=>'langganan_owner','is_active'=>true]]);

    while (count($addressRows) < 3) {
        $addressRows[] = ['label' => '', 'address' => '', 'is_primary' => false];
    }
    while (count($userRows) < 2) {
        $userRows[] = ['role' => 'langganan_staff', 'is_active' => true];
    }
@endphp
<form method="POST" action="{{ route('admin.customers.access.update', $customer) }}">
@csrf @method('PUT')
<x-metronic.card title="Alamat Kirim">
    <div class="alert alert-info">Pilih satu alamat utama. Sistem menyimpan guard unik agar hanya satu alamat utama per pelanggan.</div>
    @foreach($addressRows as $index => $address)
        <div class="border rounded p-4 mb-4">
            <input type="hidden" name="addresses[{{ $index }}][id]" value="{{ $address['id'] ?? '' }}">
            <div class="row"><div class="col-md-3"><x-metronic.form-group name="addresses.{{ $index }}.label" label="Label"><input name="addresses[{{ $index }}][label]" value="{{ $address['label'] ?? '' }}" class="form-control"></x-metronic.form-group></div><div class="col-md-3"><x-metronic.form-group name="addresses.{{ $index }}.recipient_name" label="PIC"><input name="addresses[{{ $index }}][recipient_name]" value="{{ $address['recipient_name'] ?? '' }}" class="form-control"></x-metronic.form-group></div><div class="col-md-3"><x-metronic.form-group name="addresses.{{ $index }}.phone_number" label="Telepon"><input name="addresses[{{ $index }}][phone_number]" value="{{ $address['phone_number'] ?? '' }}" class="form-control"></x-metronic.form-group></div><div class="col-md-3"><label class="form-label">Alamat Utama</label><label class="form-check"><input type="radio" class="form-check-input" name="primary_address_index" value="{{ $index }}" @checked($address['is_primary'] ?? $loop->first)> Utama</label></div></div>
            <x-metronic.form-group name="addresses.{{ $index }}.address" label="Alamat"><textarea name="addresses[{{ $index }}][address]" rows="2" class="form-control">{{ $address['address'] ?? '' }}</textarea></x-metronic.form-group>
            <div class="row"><div class="col-md-4"><x-metronic.form-group name="addresses.{{ $index }}.city" label="Kota"><input name="addresses[{{ $index }}][city]" value="{{ $address['city'] ?? '' }}" class="form-control"></x-metronic.form-group></div><div class="col-md-4"><x-metronic.form-group name="addresses.{{ $index }}.postal_code" label="Kode Pos"><input name="addresses[{{ $index }}][postal_code]" value="{{ $address['postal_code'] ?? '' }}" class="form-control"></x-metronic.form-group></div><div class="col-md-4"><x-metronic.form-group name="addresses.{{ $index }}.directions" label="Petunjuk"><input name="addresses[{{ $index }}][directions]" value="{{ $address['directions'] ?? '' }}" class="form-control"></x-metronic.form-group></div></div>
        </div>
    @endforeach
</x-metronic.card>
<x-metronic.card title="User Langganan" class="mt-6">
    @foreach($userRows as $index => $row)
        <div class="border rounded p-4 mb-4">
            <input type="hidden" name="users[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
            <div class="row"><div class="col-md-3"><x-metronic.form-group name="users.{{ $index }}.name" label="Nama"><input name="users[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" class="form-control"></x-metronic.form-group></div><div class="col-md-3"><x-metronic.form-group name="users.{{ $index }}.username" label="Username"><input name="users[{{ $index }}][username]" value="{{ $row['username'] ?? '' }}" class="form-control"></x-metronic.form-group></div><div class="col-md-3"><x-metronic.form-group name="users.{{ $index }}.email" label="Email"><input name="users[{{ $index }}][email]" value="{{ $row['email'] ?? '' }}" class="form-control"></x-metronic.form-group></div><div class="col-md-3"><x-metronic.form-group name="users.{{ $index }}.role" label="Role"><select name="users[{{ $index }}][role]" class="form-select"><option value="langganan_owner" @selected(($row['role'] ?? '') === 'langganan_owner')>Langganan Owner</option><option value="langganan_staff" @selected(($row['role'] ?? '') === 'langganan_staff')>Langganan Staff</option></select></x-metronic.form-group></div></div>
            <input type="hidden" name="users[{{ $index }}][is_active]" value="0"><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="users[{{ $index }}][is_active]" value="1" @checked($row['is_active'] ?? true)> Aktif</label>
            <x-metronic.form-group name="users.{{ $index }}.blocked_reason" label="Alasan Blokir"><input name="users[{{ $index }}][blocked_reason]" value="{{ $row['blocked_reason'] ?? '' }}" class="form-control"></x-metronic.form-group>
            @if(!empty($row['email']))<form method="POST" action="{{ route('admin.customers.access.reset-password', $customer) }}">@csrf<input type="hidden" name="email" value="{{ $row['email'] }}"><button class="btn btn-sm btn-light-primary">Kirim Reset Password</button></form>@endif
        </div>
    @endforeach
</x-metronic.card>
<div class="d-flex justify-content-end gap-3 mt-6"><a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-light">Kembali</a><button class="btn btn-primary">Simpan Akses</button></div>
</form>
@endsection
