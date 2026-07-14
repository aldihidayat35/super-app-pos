@extends('layouts.metronic.app')

@section('title', 'Check-in/Check-out')
@section('page_title', 'Check-in/Check-out')

@section('content')
    <x-metronic.page-title title="Check-in/Check-out" description="HR-04 absensi berbasis jadwal aktif, waktu server, bukti opsional, dan status telat/pulang cepat." />
    <div class="row g-5">
        <div class="col-lg-5"><x-metronic.card title="Status Hari Ini">
            <div class="mb-3"><span class="text-muted">Waktu Server</span><div class="fs-3 fw-bold">{{ now()->format('d/m/Y H:i:s') }}</div></div>
            <div class="mb-3"><span class="text-muted">Karyawan</span><div class="fw-bold">{{ $employee?->name ?: 'Belum terhubung' }}</div></div>
            <div class="mb-3"><span class="text-muted">Shift Aktif</span><div>{{ $schedule?->workShift?->name ?: 'Tidak ada jadwal aktif' }}</div><div class="text-muted">{{ $schedule?->scheduled_start_at?->format('d/m/Y H:i') }} - {{ $schedule?->scheduled_end_at?->format('d/m/Y H:i') }}</div></div>
            <div><span class="text-muted">Absensi Aktif</span><div>{{ $openAttendance?->check_in_at?->format('d/m/Y H:i') ?: '-' }}</div></div>
        </x-metronic.card></div>
        <div class="col-lg-7"><x-metronic.card title="Aksi Absensi">
            <form method="POST" action="{{ route('attendance.check.in') }}" enctype="multipart/form-data" class="row g-3 mb-5">@csrf
                <div class="col-md-3"><select name="method" class="form-select"><option value="login">Login</option><option value="pin">PIN</option><option value="qr">QR</option></select></div>
                <div class="col-md-4"><input type="file" name="proof" class="form-control"></div>
                <div class="col-md-5"><input name="location_note" class="form-control" placeholder="Catatan lokasi (opsional, bukan geo presisi)"></div>
                <div class="col-12"><button class="btn btn-success">Check-in</button></div>
            </form>
            <form method="POST" action="{{ route('attendance.check.out') }}" class="row g-3">@csrf
                <div class="col-md-8"><input name="notes" class="form-control" placeholder="Catatan pulang"></div>
                <div class="col-md-4"><button class="btn btn-warning w-100">Check-out</button></div>
            </form>
        </x-metronic.card></div>
    </div>
@endsection
