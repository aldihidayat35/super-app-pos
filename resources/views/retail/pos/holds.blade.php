@extends('layouts.metronic.app')

@section('title', 'Transaksi Ditahan - ' . config('app.name'))
@section('page_title', 'Transaksi Ditahan / Hold')

@section('content')
    <x-metronic.card>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>No</th><th>Cabang/Pelanggan</th><th>Total Estimasi</th><th>Waktu</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($holds as $hold)
                    <tr>
                        <td class="fw-bold">{{ $hold->number }}</td>
                        <td>{{ $hold->branch?->name }}<div class="text-muted">{{ $hold->customer?->business_name ?? 'Umum' }}</div></td>
                        <td>Rp {{ number_format((float) $hold->estimated_total, 0, ',', '.') }}</td>
                        <td>{{ $hold->created_at?->format('d/m/Y H:i') }}</td>
                        <td><x-metronic.status-badge :status="$hold->status" /></td>
                        <td>
                            @if($hold->status->value === 'held')
                                <form method="POST" action="{{ route('retail.pos.holds.resume', $hold) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary">Resume</button></form>
                                <form method="POST" action="{{ route('retail.pos.holds.cancel', $hold) }}" class="d-inline">@csrf<input type="hidden" name="reason" value="Dibatalkan kasir"><button class="btn btn-sm btn-light-danger">Batalkan</button></form>
                            @else
                                <span class="text-muted">Selesai</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-metronic.empty-state title="Belum ada transaksi hold" description="Cart yang ditahan per shift akan tampil di sini." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $holds->links() }}
    </x-metronic.card>
@endsection
