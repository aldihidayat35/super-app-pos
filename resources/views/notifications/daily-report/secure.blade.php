@extends('layouts.metronic.app')

@section('title', 'Detail Laporan Aman')
@section('page_title', 'Detail Laporan Aman')

@section('content')
    <x-metronic.page-title title="Detail Laporan Aman" description="NTF-06 token unik hash, expiry, status dibaca, ringkasan, dan link login untuk detail penuh." />

    <x-metronic.card title="Status Token">
        <div class="row g-3">
            <div class="col-md-3"><div class="text-muted">Report Date</div><div class="fw-bold">{{ $report->report_date->format('d/m/Y') }}</div></div>
            <div class="col-md-3"><div class="text-muted">Berlaku Sampai</div><div class="fw-bold">{{ $token->expires_at->format('d/m/Y H:i') }}</div></div>
            <div class="col-md-3"><div class="text-muted">Dibaca</div><div class="fw-bold">{{ $token->read_at?->format('d/m/Y H:i') ?: '-' }}</div></div>
            <div class="col-md-3"><div class="text-muted">Akses</div><div class="fw-bold">{{ $token->access_count }}x</div></div>
        </div>
    </x-metronic.card>

    <x-metronic.card title="Ringkasan Laporan" class="mt-5">
        <div class="row g-3">
            @foreach($report->summary as $key => $value)
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted">{{ Str::headline($key) }}</div>
                        <div class="fs-4 fw-bold">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="alert alert-warning mt-4 mb-0">Link ini hanya menampilkan ringkasan terbatas. Untuk detail penuh, login ke aplikasi dan buka menu Laporan Harian Owner.</div>
    </x-metronic.card>

    <x-metronic.card title="Definisi" class="mt-5">
        <ul class="mb-0">
            @foreach($report->definitions ?? [] as $definition)
                <li>{{ $definition }}</li>
            @endforeach
        </ul>
    </x-metronic.card>
@endsection
