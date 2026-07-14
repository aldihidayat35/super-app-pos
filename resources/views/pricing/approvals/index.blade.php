@extends('layouts.metronic.app')

@section('title', 'Antrian Approval Harga - ' . config('app.name'))
@section('page_title', 'Antrian Approval Harga')

@section('content')
    <x-metronic.card>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Jenis</th><th>Produk/Pelanggan</th><th>Harga</th><th>Snapshot</th><th>Requester</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($approvals as $approval)
                    <tr>
                        <td>{{ str_replace('_', ' ', $approval->approval_type) }}<div class="text-muted">{{ $approval->document_type }} #{{ $approval->document_id }}</div></td>
                        <td>{{ $approval->product?->sku }}<div class="text-muted">{{ $approval->product?->name }} · {{ $approval->customer?->business_name ?? 'Umum' }}</div></td>
                        <td>Rp {{ number_format((float) $approval->requested_price, 0, ',', '.') }}<div class="text-muted">Diskon {{ $approval->discount_percent }}%</div></td>
                        <td>Min Rp {{ number_format((float) $approval->minimum_price_snapshot, 0, ',', '.') }}<div class="text-muted">Max Rp {{ number_format((float) $approval->maximum_price_snapshot, 0, ',', '.') }} · HPP Rp {{ number_format((float) $approval->hpp_snapshot, 0, ',', '.') }}</div></td>
                        <td>{{ $approval->requester?->name }}<div class="text-muted">{{ $approval->created_at?->format('d/m/Y H:i') }}</div></td>
                        <td><x-metronic.status-badge :status="$approval->status" /></td>
                        <td>
                            @if($approval->status->value === 'pending')
                                <form method="POST" action="{{ route('pricing.approvals.approve', $approval) }}" class="d-inline">@csrf<textarea name="notes" class="form-control form-control-sm mb-2" placeholder="Catatan approval"></textarea><button class="btn btn-sm btn-success">Approve</button></form>
                                <form method="POST" action="{{ route('pricing.approvals.reject', $approval) }}" class="d-inline">@csrf<button class="btn btn-sm btn-light-danger mt-2">Reject</button></form>
                            @else
                                <span class="text-muted">Selesai</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Tidak ada approval harga" description="Permintaan below minimum, overpricing, atau diskon besar akan masuk ke sini." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $approvals->links() }}
    </x-metronic.card>
@endsection
