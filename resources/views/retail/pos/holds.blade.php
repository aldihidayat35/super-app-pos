@extends('layouts.metronic.app')

@section('title', 'Transaksi Ditahan - '.config('app.name'))
@section('page_title', 'Transaksi Ditahan')
@section('toolbar_actions')<a href="{{ route('retail.pos.index') }}" class="btn btn-primary"><i class="ki-outline ki-shop fs-5 me-1"></i>Kembali ke POS</a>@endsection

@section('content')
    <div class="alert alert-light-info">Keranjang pada daftar ini belum dibayar dan dapat dilanjutkan tanpa melakukan scan ulang.</div>
    <x-metronic.card><div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Nomor Hold</th><th>Waktu</th><th>Cabang</th><th>Kasir</th><th>Customer</th><th>Item</th><th>Total Qty</th><th>Estimasi Total</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    @forelse($holds as $hold)
        <tr><td class="fw-bold">{{ $hold->number }}</td><td>{{ $hold->created_at?->format('d/m/Y H:i') }}</td><td>{{ $hold->branch?->name }}</td><td>{{ $hold->cashier?->name }}</td><td>{{ $hold->customer?->business_name ?? 'Pelanggan Umum' }}</td><td>{{ $hold->snapshot_item_count }}</td><td>{{ qty($hold->snapshot_total_qty) }}</td><td class="fw-bold">Rp {{ number_format((float) $hold->estimated_total, 0, ',', '.') }}</td><td><x-metronic.status-badge :status="$hold->status" /></td><td>@if($hold->status->value === 'held')<div class="d-flex gap-2"><form method="POST" action="{{ route('retail.pos.holds.resume', $hold) }}">@csrf<button class="btn btn-sm btn-primary">Lanjutkan Transaksi</button></form><form method="POST" action="{{ route('retail.pos.holds.cancel', $hold) }}">@csrf<input type="hidden" name="reason" value="Dibatalkan kasir"><button class="btn btn-sm btn-light-danger" data-confirm="Batalkan transaksi hold {{ $hold->number }}?">Batalkan</button></form></div>@else<span class="text-muted">Selesai</span>@endif</td></tr>
    @empty<tr><td colspan="10"><x-metronic.empty-state title="Belum ada transaksi ditahan" description="Keranjang yang ditahan dari POS akan tampil di sini." /></td></tr>@endforelse
    </tbody></table></div>{{ $holds->links() }}</x-metronic.card>
@endsection
