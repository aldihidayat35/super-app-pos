@extends('layouts.metronic.app')

@section('title', 'Maintenance dan Go-Live')
@section('page_title', 'Maintenance dan Go-Live')

@section('content')
    <x-metronic.page-title title="Maintenance dan Go-Live" description="OPS-04 — Mode maintenance, versi/build, migration status, queue/scheduler health, cache clear terkontrol, checklist go-live, dan banner pengumuman." />

    <div class="row g-5 mb-5">
        <div class="col-md-3"><x-metronic.card><div class="text-muted fs-7">Maintenance</div><div class="fs-3 fw-bold">{{ $isDown ? 'Aktif' : 'Nonaktif' }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card><div class="text-muted fs-7">Build</div><div class="fs-3 fw-bold">{{ $version }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card><div class="text-muted fs-7">Migration Ran</div><div class="fs-3 fw-bold">{{ $migrationStatus['ran'] }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card><div class="text-muted fs-7">Pending Migration</div><div class="fs-3 fw-bold {{ $migrationStatus['pending'] > 0 ? 'text-warning' : 'text-success' }}">{{ $migrationStatus['pending'] }}</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Aksi Maintenance Terkontrol">
        <form method="POST" action="{{ route('admin.system.maintenance.run') }}" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Aksi</label>
                <select name="action" class="form-select" required>
                    <option value="cache_clear">Clear Cache</option>
                    <option value="optimize">Optimize Cache</option>
                    <option value="queue_restart">Queue Restart</option>
                    <option value="down">Maintenance Down</option>
                    <option value="up">Maintenance Up</option>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Pesan/Banner</label><input name="message" class="form-control" maxlength="255" placeholder="Contoh: Go-live maintenance pukul 22.00"></div>
            <div class="col-md-3"><label class="form-label">Konfirmasi</label><input name="confirmation" class="form-control" placeholder="SAYA MENGERTI" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-warning w-100">Jalankan</button></div>
        </form>
    </x-metronic.card>

    <div class="row g-5 mt-1">
        <div class="col-lg-6">
            <x-metronic.card title="Health Ringkas">
                @foreach ($checks as $name => $check)
                    <div class="d-flex justify-content-between border-bottom py-2"><span>{{ str_replace('_', ' ', $name) }}</span><x-metronic.status-badge :status="$check['status']" /></div>
                @endforeach
            </x-metronic.card>
        </div>
        <div class="col-lg-6">
            <x-metronic.card title="Checklist Go-Live">
                @foreach ($checklist as $item)
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" disabled>
                        <label class="form-check-label">{{ $item['label'] }}</label>
                    </div>
                @endforeach
            </x-metronic.card>
        </div>
    </div>
@endsection
