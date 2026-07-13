@section('title', 'Approval Retur - ' . config('app.name'))
@extends('layouts.metronic.app')
@section('content')
    <x-metronic.page-title :title="'Approval ' . $return->number" description="Approval retur bernilai besar sebelum settlement." />
    <x-metronic.card title="Keputusan Approval">
        <div class="mb-4">Status: <x-metronic.status-badge :status="$return->status" /> · Loss {{ \App\Support\CurrencyFormatter::rupiah($return->total_loss_value) }}</div>
        @can('approve', $return)<form method="POST" action="{{ route('returns.approve', $return) }}">@csrf<textarea name="notes" class="form-control mb-3">Disetujui.</textarea><button class="btn btn-success">Approve Retur</button></form>@else<x-metronic.empty-state title="Tidak perlu/Belum bisa approval" description="Approval hanya aktif pada status menunggu approval." />@endcan
    </x-metronic.card>
@endsection
