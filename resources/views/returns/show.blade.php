@section('title', 'Detail Retur - ' . config('app.name'))
@section('page_title', 'Detail Retur')
@extends('layouts.metronic.app')

@section('content')
    <x-metronic.page-title :title="$return->number" description="Detail dokumen retur, QC, settlement, mutasi stok, dan timeline.">
        <x-slot:actions>@can('inspect', $return)<a href="{{ route('returns.inspection', $return) }}" class="btn btn-light-primary">QC Retur</a>@endcan @can('settle', $return)<a href="{{ route('returns.settlement', $return) }}" class="btn btn-primary">Settlement</a>@endcan <a href="{{ route('returns.approval', $return) }}" class="btn btn-light-info">Approval</a></x-slot:actions>
    </x-metronic.page-title>
    <div class="row g-6">
        <div class="col-lg-4"><x-metronic.card title="Header"><div>Status: <x-metronic.status-badge :status="$return->status" /></div><div>Sumber: {{ strtoupper($return->source_type) }} — {{ $return->source_name ?: '-' }}</div><div>Referensi: {{ $return->reference_no ?: '-' }}</div><div>Requester: {{ $return->requester?->name }}</div><div>Nilai: {{ \App\Support\CurrencyFormatter::rupiah($return->total_value) }}</div><div>Loss: {{ \App\Support\CurrencyFormatter::rupiah($return->total_loss_value) }}</div></x-metronic.card></div>
        <div class="col-lg-8"><x-metronic.card title="Item"><table class="table"><thead><tr><th>Produk</th><th>Qty</th><th>Good</th><th>Rusak</th><th>Reject</th><th>Nilai</th></tr></thead><tbody>@foreach($return->items as $item)<tr><td>{{ $item->product_sku_snapshot }} — {{ $item->product_name_snapshot }}</td><td>{{ qty($item->quantity_requested) }}</td><td>{{ qty($item->quantity_accepted_good) }}</td><td>{{ qty($item->quantity_accepted_damaged) }}</td><td>{{ qty($item->quantity_rejected) }}</td><td>{{ \App\Support\CurrencyFormatter::rupiah($item->line_value) }}</td></tr>@endforeach</tbody></table></x-metronic.card></div>
    </div>
    <x-metronic.card title="Mutasi & Settlement" class="mt-6"><div class="row"><div class="col-md-6"><h6>Mutasi Stok</h6><ul>@forelse($return->stockMutations as $mutation)<li>{{ $mutation->mutation_type->label() }} {{ qty($mutation->quantity_on_hand_change) }} — {{ $mutation->product?->name }}</li>@empty<li>Belum ada mutasi.</li>@endforelse</ul></div><div class="col-md-6"><h6>Settlement</h6><ul>@forelse($return->settlements as $settlement)<li>{{ $settlement->resolution->label() }} — {{ \App\Support\CurrencyFormatter::rupiah($settlement->amount) }} — {{ $settlement->document_no }}</li>@empty<li>Belum settlement.</li>@endforelse</ul></div></div></x-metronic.card>
@endsection
