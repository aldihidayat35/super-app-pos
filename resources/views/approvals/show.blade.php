@extends('layouts.metronic.app')

@section('title', 'Detail Approval')
@section('page_title', 'Detail Approval')

@section('content')
    <x-metronic.page-title title="Detail Approval #{{ $approval->id }}" description="APP-02 data sebelum/sesudah, dampak risiko, histori, komentar, dan signature waktu.">
        <a href="{{ route('approvals.index') }}" class="btn btn-light">Kembali</a>
    </x-metronic.page-title>
    <div class="row g-5">
        <div class="col-lg-7">
            <x-metronic.card title="Ringkasan">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Jenis</dt><dd class="col-sm-8">{{ $approval->approval_type }}</dd>
                    <dt class="col-sm-4">Subject</dt><dd class="col-sm-8">{{ class_basename($approval->subject_type) }} #{{ $approval->subject_id }}</dd>
                    <dt class="col-sm-4">Requester</dt><dd class="col-sm-8">{{ $approval->requester?->name }}</dd>
                    <dt class="col-sm-4">Nilai/Risiko</dt><dd class="col-sm-8">{{ \App\Support\CurrencyFormatter::rupiah((string) $approval->risk_value) }} · {{ $approval->risk_level }}</dd>
                    <dt class="col-sm-4">Alasan</dt><dd class="col-sm-8">{{ $approval->reason }}</dd>
                    <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><x-metronic.status-badge :status="$approval->current_status->value" :label="$approval->current_status->label()" /></dd>
                </dl>
            </x-metronic.card>
            <x-metronic.card title="Before / After" class="mt-5">
                <div class="row">
                    <div class="col-md-6"><h6>Sebelum</h6><pre class="bg-light p-3 rounded small">{{ json_encode($approval->before_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
                    <div class="col-md-6"><h6>Sesudah</h6><pre class="bg-light p-3 rounded small">{{ json_encode($approval->after_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-5">
            <x-metronic.card title="Keputusan">
                @if($approval->current_status->value === 'pending')
                    <form method="POST" action="{{ route('approvals.approve', $approval) }}" class="mb-4">@csrf
                        <textarea name="comments" class="form-control mb-3" rows="3" placeholder="Komentar approval"></textarea>
                        <button class="btn btn-success w-100">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('approvals.reject', $approval) }}">@csrf
                        <textarea name="comments" class="form-control mb-3" rows="3" placeholder="Alasan reject"></textarea>
                        <button class="btn btn-light-danger w-100">Reject</button>
                    </form>
                @else
                    <div class="text-muted">Diputus oleh {{ $approval->approver?->name ?: '-' }} pada {{ $approval->approved_at?->format('d/m/Y H:i') ?: $approval->rejected_at?->format('d/m/Y H:i') }}</div>
                    <div class="mt-3">{{ $approval->decision_notes }}</div>
                @endif
            </x-metronic.card>
            <x-metronic.card title="Histori Step" class="mt-5">
                @foreach($approval->steps as $step)
                    <div class="border-bottom py-3">
                        <div class="fw-bold">Step {{ $step->step_order }} · {{ $step->status->label() }}</div>
                        <div class="text-muted">{{ $step->approver?->name ?: '-' }} · {{ $step->decided_at?->format('d/m/Y H:i') ?: '-' }}</div>
                        <div>{{ $step->comments }}</div>
                    </div>
                @endforeach
            </x-metronic.card>
        </div>
    </div>
@endsection
