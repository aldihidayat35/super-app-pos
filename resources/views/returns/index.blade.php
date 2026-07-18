@section('title', 'Daftar Retur - ' . config('app.name'))
@section('page_title', 'Daftar Retur')
@extends('layouts.metronic.app')

@section('content')
    <x-metronic.page-title title="Daftar Retur" description="Pantau retur supplier, cabang, POS, dan B2B.">
        <x-slot:actions>
            <x-metronic.permission-button permission="returns.create" :href="route('returns.create')" icon="ki-outline ki-plus">Ajukan Retur</x-metronic.permission-button>
            <a href="{{ route('returns.export') }}" class="btn btn-light-success">Export</a>
        </x-slot:actions>
    </x-metronic.page-title>
    <x-metronic.card>
        <form class="row g-3 mb-5">
            <div class="col-md-3"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-solid" placeholder="Nomor/referensi/nama"></div>
            <div class="col-md-3"><select name="source_type" class="form-select form-select-solid"><option value="">Semua sumber</option>@foreach(['supplier'=>'Supplier','branch'=>'Cabang','pos'=>'POS','b2b'=>'B2B','transfer'=>'Transfer','manual'=>'Manual'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['source_type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-light w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Nomor</th><th>Sumber</th><th>Referensi</th><th>Tanggal</th><th class="text-end">Qty/Nilai</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse($returns as $return)
                    <tr>
                        <td class="fw-bold">{{ $return->number }}<div class="text-muted">{{ $return->workLocation?->name }}</div></td>
                        <td>{{ strtoupper($return->source_type) }}<div class="text-muted">{{ $return->source_name ?: '-' }}</div></td>
                        <td>{{ $return->reference_no ?: '-' }}</td>
                        <td>{{ $return->return_date?->format('d/m/Y') }}</td>
                        <td class="text-end">{{ qty($return->total_quantity) }}<div class="text-muted">{{ \App\Support\CurrencyFormatter::rupiah($return->total_value) }}</div></td>
                        <td><x-metronic.status-badge :status="$return->status" /></td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('returns.show', $return) }}">Detail</a>@can('inspect', $return)<a class="btn btn-sm btn-light-primary" href="{{ route('returns.inspection', $return) }}">QC</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada retur" description="Retur supplier/cabang/POS/B2B akan muncul di sini." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $returns->links() }}
    </x-metronic.card>
@endsection
