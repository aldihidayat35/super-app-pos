@extends('layouts.metronic.app')

@section('title', 'Kesehatan Sistem - ' . config('app.name'))
@section('page_title', 'Kesehatan Sistem')

@section('content')
    <x-metronic.page-title title="Kesehatan Sistem" description="Pemeriksaan fondasi aplikasi dan layanan pendukung." />
    <div class="row g-5">
        @foreach ($checks as $name => $check)
            @php($color = match ($check['status']) { 'ok' => 'success', 'warning' => 'warning', default => 'danger' })
            <div class="col-md-6 col-xl-4"><x-metronic.card class="h-100"><div class="d-flex align-items-center justify-content-between mb-3"><h2 class="fs-5 fw-bold text-capitalize mb-0">{{ $name }}</h2><x-metronic.status-badge :status="$check['status']" /></div><p class="text-gray-700 mb-0">{{ $check['message'] }}</p></x-metronic.card></div>
        @endforeach
    </div>
@endsection
