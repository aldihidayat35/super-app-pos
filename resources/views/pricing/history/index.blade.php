@extends('layouts.metronic.app')

@section('title', 'Histori Perubahan Harga - ' . config('app.name'))
@section('page_title', 'Histori Perubahan Harga')

@section('toolbar_actions')
    <a href="{{ route('pricing.history.export', request()->query()) }}" class="btn btn-light-success"><i class="ki-outline ki-file-down"></i> Export CSV</a>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-6">
            <div class="col-md-3"><select name="product_id" class="form-select"><option value="">Semua produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="channel" class="form-select"><option value="">Semua channel</option><option value="retail" @selected(($filters['channel'] ?? '') === 'retail')>Retail</option><option value="b2b" @selected(($filters['channel'] ?? '') === 'b2b')>B2B</option><option value="pos" @selected(($filters['channel'] ?? '') === 'pos')>POS</option></select></div>
            <div class="col-md-2"><select name="user_id" class="form-select"><option value="">Semua user</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control"></div>
            <div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control"></div>
            <div class="col-md-1"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Scope</th><th>Old → New</th><th>Snapshot</th><th>User</th><th>Waktu</th><th>Alasan</th></tr></thead>
                <tbody>
                @forelse($histories as $history)
                    <tr>
                        <td>{{ $history->product?->sku }}<div class="text-muted">{{ $history->product?->name }}</div></td>
                        <td>{{ strtoupper($history->channel) }}<div class="text-muted">{{ $history->price_ring ?: '-' }}</div></td>
                        <td>Rp {{ number_format((float) $history->old_price, 0, ',', '.') }} → <strong>Rp {{ number_format((float) $history->new_price, 0, ',', '.') }}</strong></td>
                        <td>HPP Rp {{ number_format((float) $history->hpp_snapshot, 0, ',', '.') }}<div class="text-muted">Min Rp {{ number_format((float) $history->minimum_price_snapshot, 0, ',', '.') }}</div></td>
                        <td>{{ $history->changer?->name }}</td>
                        <td>{{ $history->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $history->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada histori harga" description="Histori terbentuk saat ring harga atau harga khusus berubah." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $histories->links() }}
    </x-metronic.card>
@endsection
