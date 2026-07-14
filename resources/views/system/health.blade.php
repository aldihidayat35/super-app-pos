@extends('layouts.metronic.app')

@section('title', 'Kesehatan Sistem - ' . config('app.name'))
@section('page_title', 'Kesehatan Sistem')

@section('toolbar_actions')
    <a href="{{ request()->url() }}" class="btn btn-sm btn-light-primary">
        <i class="ki-outline ki-arrows-circle fs-4"></i>Refresh
    </a>
@endsection

@section('content')
    <x-metronic.page-title
        title="Kesehatan Sistem"
        description="SYS-01 — Pemeriksaan database, cache, session, queue, scheduler, storage, permission folder, versi aplikasi, dan waktu server tanpa menampilkan secret."
    />

    <div class="alert alert-info d-flex align-items-start gap-3">
        <i class="ki-outline ki-information-5 fs-2"></i>
        <div>
            <div class="fw-bold">Informasi keamanan</div>
            <div>Halaman ini hanya menampilkan status operasional. Nilai kunci aplikasi, password database, token API, dan secret integrasi tidak pernah ditampilkan.</div>
        </div>
    </div>

    <div class="row g-5">
        @foreach ($checks as $name => $check)
            @php($color = match ($check['status']) { 'ok' => 'success', 'warning' => 'warning', default => 'danger' })
            <div class="col-md-6 col-xl-4">
                <x-metronic.card class="h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="text-muted fs-8 text-uppercase">{{ str_replace('_', ' ', $name) }}</div>
                            <h2 class="fs-5 fw-bold mb-0">{{ ucwords(str_replace('_', ' ', $name)) }}</h2>
                        </div>
                        <x-metronic.status-badge :status="$check['status']" />
                    </div>
                    <p class="text-gray-700 mb-0">{{ $check['message'] }}</p>
                </x-metronic.card>
            </div>
        @endforeach
    </div>
@endsection
