@extends('layouts.metronic.app')

@section('title', 'Pengaturan Notifikasi WhatsApp')
@section('page_title', 'Pengaturan Notifikasi WhatsApp')

@section('content')
<x-metronic.page-title title="Pengaturan Notifikasi WhatsApp" description="Pilih kejadian bisnis yang boleh mengirim pesan WhatsApp dan atur jeda agar penerima tidak menerima pesan berulang." />

<div class="alert alert-light-primary d-flex align-items-start gap-3">
    <i class="ki-outline ki-information-5 fs-2 text-primary"></i>
    <div><strong>Nomor penerima mengikuti akun.</strong><div class="text-muted">Sistem memakai nomor WhatsApp pada data karyawan atau nomor telepon akun, lalu membatasi penerima sesuai role dan lokasi kerja.</div></div>
</div>

<div class="row g-5">
@foreach($settings as $setting)
    <div class="col-xl-6">
        <x-metronic.card class="h-100">
            <form method="POST" action="{{ route('admin.notifications.business-settings.update', $setting) }}">
                @csrf @method('PUT')
                <div class="d-flex justify-content-between gap-4 mb-3">
                    <div><h3 class="fs-5 mb-1">{{ $setting->name }}</h3><p class="text-muted mb-0">{{ $setting->description }}</p></div>
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="event-{{ $setting->id }}" @checked($setting->is_active)>
                        <label class="form-check-label" for="event-{{ $setting->id }}">Aktif</label>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    @foreach($setting->recipient_roles as $role)
                        <span class="badge badge-light-primary">{{ config("rbac.roles.{$role}.label", str($role)->headline()) }}</span>
                    @endforeach
                    @if($setting->location_scoped)<span class="badge badge-light-info">Sesuai lokasi kerja</span>@else<span class="badge badge-light-secondary">Lingkup global</span>@endif
                </div>
                <div class="row align-items-end g-3">
                    <div class="col-sm-8"><label class="form-label">Jeda pesan berulang (menit)</label><input type="number" min="0" max="10080" name="cooldown_minutes" value="{{ $setting->cooldown_minutes }}" class="form-control" required><div class="form-text">Isi 0 untuk kejadian unik yang tidak memakai jeda.</div></div>
                    <div class="col-sm-4"><button class="btn btn-primary w-100">Simpan</button></div>
                </div>
            </form>
        </x-metronic.card>
    </div>
@endforeach
</div>
@endsection
