@section('title', 'Laporan Loss - ' . config('app.name'))
@extends('layouts.metronic.app')
@section('content')
    <x-metronic.page-title title="Laporan Loss Tracking" description="Nilai kerugian per produk/lokasi/alasan/periode." />
    <x-metronic.card title="Ringkasan Penyebab" class="mb-6"><table class="table"><thead><tr><th>Penyebab</th><th class="text-end">Qty</th><th class="text-end">Nilai</th></tr></thead><tbody>@foreach($byReason as $row)<tr><td>{{ $row->loss_type }}</td><td class="text-end">{{ qty($row->total_qty) }}</td><td class="text-end">{{ \App\Support\CurrencyFormatter::rupiah($row->total_value) }}</td></tr>@endforeach</tbody></table></x-metronic.card>
    <x-metronic.card title="Detail Loss"><table class="table"><thead><tr><th>No</th><th>Produk</th><th>Lokasi</th><th>Jenis</th><th>Qty</th><th>Nilai</th><th>Tanggal</th></tr></thead><tbody>@foreach($losses as $loss)<tr><td>{{ $loss->number }}</td><td>{{ $loss->product?->name }}</td><td>{{ $loss->workLocation?->name }}</td><td>{{ $loss->loss_type }}</td><td>{{ qty($loss->quantity) }}</td><td>{{ \App\Support\CurrencyFormatter::rupiah($loss->loss_value) }}</td><td>{{ $loss->reported_at?->format('d/m/Y') }}</td></tr>@endforeach</tbody></table>{{ $losses->links() }}</x-metronic.card>
@endsection
