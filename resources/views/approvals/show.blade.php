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
                @php
                    $beforePayload = $approval->before_payload ?? [];
                    $afterPayload = $approval->after_payload ?? [];
                    $allKeys = array_unique(array_merge(array_keys($beforePayload), array_keys($afterPayload)));
                @endphp
                @if(count($allKeys) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless diff-table">
                            <thead>
                                <tr class="text-muted small text-uppercase fw-semibold">
                                    <th class="ps-3" style="width:25%">Field</th>
                                    <th class="text-danger" style="width:37.5%"><i class="mdi mdi-arrow-left me-1"></i>Sebelum</th>
                                    <th class="pe-3 text-success" style="width:37.5%">Sesudah<i class="mdi mdi-arrow-right ms-1"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($allKeys as $key)
                                    @php
                                        $beforeVal = $beforePayload[$key] ?? null;
                                        $afterVal = $afterPayload[$key] ?? null;
                                        $beforeStr = $beforeVal === null ? 'null' : (is_array($beforeVal) ? json_encode($beforeVal) : $beforeVal);
                                        $afterStr = $afterVal === null ? 'null' : (is_array($afterVal) ? json_encode($afterVal) : $afterVal);
                                        $changed = (string)$beforeVal !== (string)$afterVal;
                                        $added = !array_key_exists($key, $beforePayload);
                                        $removed = !array_key_exists($key, $afterPayload);
                                    @endphp
                                    <tr class="{{ $changed || $added || $removed ? 'diff-highlight' : '' }}">
                                        <td class="ps-3 align-top">
                                            <span class="fw-bold text-primary field-name">{{ e($key) }}</span>
                                            @if($added)
                                                <span class="badge bg-success-subtle text-success ms-1 badge-pill" style="font-size: 0.65rem;">baru</span>
                                            @endif
                                            @if($removed)
                                                <span class="badge bg-danger-subtle text-danger ms-1 badge-pill" style="font-size: 0.65rem;">hapus</span>
                                            @endif
                                            @if($changed && !$added && !$removed)
                                                <span class="badge bg-warning-subtle text-warning-emphasis ms-1 badge-pill" style="font-size: 0.65rem;">ubah</span>
                                            @endif
                                        </td>
                                        <td class="align-top">
                                            <code class="d-block p-2 rounded small diff-before">{{ e($beforeStr) }}</code>
                                        </td>
                                        <td class="pe-3 align-top">
                                            @if($removed)
                                                <code class="d-block p-2 rounded small text-decoration-line-through diff-removed">{{ e($afterStr) }}</code>
                                            @elseif($added)
                                                <code class="d-block p-2 rounded small fw-bold diff-added">{{ e($afterStr) }}</code>
                                            @elseif($changed)
                                                <code class="d-block p-2 rounded small fw-bold diff-changed">{{ e($afterStr) }}</code>
                                            @else
                                                <code class="d-block p-2 rounded small text-muted diff-same">{{ e($afterStr) }}</code>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-muted small fst-italic">Tidak ada data payload</div>
                @endif
                <style>
                    .diff-table th {
                        border-bottom: 2px solid var(--bs-border-color);
                        font-size: 0.75rem;
                    }
                    .diff-table td {
                        vertical-align: top;
                        padding: 0.75rem 0.5rem;
                    }
                    .field-name {
                        font-family: 'JetBrains Mono', 'Fira Code', monospace;
                        font-size: 0.85rem;
                    }
                    .badge-pill {
                        border-radius: 20px !important;
                        padding: 0.25em 0.6em;
                    }
                    .diff-highlight {
                        background-color: rgba(var(--bs-success-rgb), 0.05);
                    }
                    .diff-highlight:hover td {
                        background-color: rgba(var(--bs-success-rgb), 0.1);
                    }
                    code.small {
                        font-family: 'JetBrains Mono', 'Fira Code', monospace;
                        font-size: 0.8rem;
                        line-height: 1.4;
                        display: block;
                        min-height: 2em;
                    }
                    .diff-before {
                        background-color: var(--bs-danger-bg-subtle);
                        color: var(--bs-danger-text-emphasis);
                    }
                    .diff-changed,
                    .diff-added {
                        background-color: var(--bs-success-bg-subtle);
                        color: var(--bs-success-text-emphasis);
                        font-weight: 600;
                    }
                    .diff-removed {
                        background-color: var(--bs-secondary-bg-subtle);
                        color: var(--bs-secondary-text-emphasis);
                    }
                    .diff-same {
                        background-color: var(--bs-light-bg-subtle);
                        color: var(--bs-secondary-text-emphasis);
                        opacity: 0.7;
                    }
                </style>
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
