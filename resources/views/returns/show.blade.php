@extends('layouts.metronic.app')

@section('title', 'Detail Retur - ' . config('app.name'))
@section('page_title', 'Detail Retur')

@section('content')
    <x-metronic.page-title :title="$return->number" description="Detail dokumen retur, QC, settlement, mutasi stok, dan timeline.">
        <x-slot:actions>
            @can('update', $return)<a href="{{ route('returns.edit', $return) }}" class="btn btn-light-primary"><i class="ki-outline ki-pencil fs-5 me-2"></i>Ubah Draft</a>@endcan
            @can('inspect', $return)<a href="{{ route('returns.inspection', $return) }}" class="btn btn-light-primary">QC Retur</a>@endcan
            @can('settle', $return)<a href="{{ route('returns.settlement', $return) }}" class="btn btn-primary">Settlement</a>@endcan
            <a href="{{ route('returns.approval', $return) }}" class="btn btn-light-info">Approval</a>
        </x-slot:actions>
    </x-metronic.page-title>

    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Header">
                <div class="mb-2">Status: <x-metronic.status-badge :status="$return->status" /></div>
                <div class="mb-2">Sumber: {{ strtoupper($return->source_type) }} - {{ $return->source_name ?: '-' }}</div>
                <div class="mb-2">Referensi: {{ $return->reference_no ?: '-' }}</div>
                <div class="mb-2">Requester: {{ $return->requester?->name }}</div>
                <div class="mb-2">Nilai: {{ \App\Support\CurrencyFormatter::rupiah($return->total_value) }}</div>
                <div>Loss: {{ \App\Support\CurrencyFormatter::rupiah($return->total_loss_value) }}</div>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Item">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Produk</th><th>Qty</th><th>Baik</th><th>Rusak</th><th>Ditolak</th><th>Nilai</th></tr></thead>
                        <tbody>@foreach($return->items as $item)<tr><td>{{ $item->product_sku_snapshot }} - {{ $item->product_name_snapshot }}</td><td>{{ qty($item->quantity_requested) }}</td><td>{{ qty($item->quantity_accepted_good) }}</td><td>{{ qty($item->quantity_accepted_damaged) }}</td><td>{{ qty($item->quantity_rejected) }}</td><td>{{ \App\Support\CurrencyFormatter::rupiah($item->line_value) }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </x-metronic.card>
        </div>
    </div>

    <x-metronic.card title="Mutasi & Settlement" class="mt-6">
        <div class="row">
            <div class="col-md-6"><h6>Mutasi Stok</h6><ul>@forelse($return->stockMutations as $mutation)<li>{{ $mutation->mutation_type->label() }} {{ qty($mutation->quantity_on_hand_change) }} - {{ $mutation->product?->name }}</li>@empty<li>Belum ada mutasi.</li>@endforelse</ul></div>
            <div class="col-md-6"><h6>Settlement</h6><ul>@forelse($return->settlements as $settlement)<li>{{ $settlement->resolution->label() }} - {{ \App\Support\CurrencyFormatter::rupiah($settlement->amount) }} - {{ $settlement->document_no }}</li>@empty<li>Belum settlement.</li>@endforelse</ul></div>
        </div>
    </x-metronic.card>

    @if($return->attachments->isNotEmpty())
        <x-metronic.card title="Bukti Retur" class="mt-6">
            <div class="d-flex flex-wrap gap-3">
                @foreach($return->attachments as $attachment)
                    <a href="{{ Storage::disk($attachment->disk)->url($attachment->path) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="ki-outline ki-file fs-5 me-2"></i>{{ $attachment->original_name }}</a>
                @endforeach
            </div>
        </x-metronic.card>
    @endif
@endsection
