@extends('layouts.metronic.app')

@section('title', 'Pembelian Darurat Toko')
@section('content')
    <x-metronic.page-title title="Pembelian Darurat Toko" description="Permintaan pelanggan dan restok darurat yang dapat dipakai lintas transaksi POS toko." />
    <div class="d-flex flex-wrap gap-2 mb-5">
        @can('emergency_purchases.create')<a class="btn btn-primary" href="{{ route('retail.emergency.create', ['branch_id' => $branchId, 'purpose' => 'customer_request']) }}">Kebutuhan pelanggan</a><a class="btn btn-light-warning" href="{{ route('retail.emergency.create', ['branch_id' => $branchId, 'purpose' => 'proactive_restock']) }}">Restok darurat toko</a>@endcan
        @can('emergency_reports.view')<a class="btn btn-light" href="{{ route('retail.emergency.report', ['branch_id' => $branchId]) }}">Laporan</a>@endcan
    </div>
    <x-metronic.card title="Permintaan toko">
        <form method="GET" class="row g-3 mb-4" action="{{ route('retail.emergency.index') }}">
            <div class="col-md-6"><label for="branch_id" class="form-label">Toko</label>
            <select id="branch_id" name="branch_id" class="form-select">
                @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branch->id == $branchId)>{{ $branch->name }}</option>@endforeach
            </select></div><div class="col-md-4"><label for="purpose" class="form-label">Jenis</label><select id="purpose" name="purpose" class="form-select"><option value="">Semua jenis</option><option value="customer_request" @selected($purpose === 'customer_request')>Permintaan pelanggan</option><option value="proactive_restock" @selected($purpose === 'proactive_restock')>Restok darurat toko</option></select></div><div class="col-md-2 align-self-end"><button class="btn btn-primary w-100">Terapkan</button></div>
        </form>
        <div class="table-responsive"><table class="table table-row-bordered align-middle">
            <thead><tr><th>Nomor</th><th>Waktu</th><th>Jenis</th><th>Produk</th><th>Status</th><th>Biaya</th><th>Pemohon</th></tr></thead>
            <tbody>
                @forelse($purchases as $purchase)
                    <tr><td><a href="{{ route('retail.emergency.show', $purchase) }}">{{ $purchase->number }}</a></td>
                        <td>{{ $purchase->created_at?->format('d/m/Y H:i') }}</td><td>{{ $purchase->purpose === 'proactive_restock' ? 'Restok toko' : 'Pelanggan' }}</td>
                        <td>{{ $purchase->items->pluck('product.name')->filter()->implode(', ') }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($purchase->status)) }}</td>
                        <td>{{ \App\Support\CurrencyFormatter::rupiah($purchase->total_cost) }}</td>
                        <td>{{ $purchase->requester?->name }}</td></tr>
                @empty<tr><td colspan="7" class="text-muted text-center py-6">Belum ada permintaan.</td></tr>@endforelse
            </tbody>
        </table></div>
        {{ $purchases->links() }}
    </x-metronic.card>
    @can('emergency_purchases.manage')
        @if($branchId)<x-metronic.card title="Aturan approval toko"><p class="text-muted">Tanpa aturan, permintaan otomatis disetujui. Jika aturan diisi, approval diperlukan bila estimasi biaya melampaui ambang.</p>
            <form method="POST" action="{{ route('retail.emergency.rule') }}" class="row g-3">@csrf @method('PUT')<input type="hidden" name="branch_id" value="{{ $branchId }}">
                <div class="col-md-5"><label class="form-label">Approval jika biaya melebihi</label><input type="number" step="0.01" min="0" name="approval_above_amount" value="{{ $rule->approval_above_amount ?? '' }}" class="form-control" placeholder="Kosong = semua perlu approval"></div>
                <div class="col-md-5"><label class="form-label">Role penyetuju</label><select name="required_role" class="form-select"><option value="">Siapa pun dengan permission approval</option>@foreach($roles as $role)<option value="{{ $role }}" @selected(($rule->required_role ?? null) === $role)>{{ $role }}</option>@endforeach</select></div>
                <div class="col-md-2 align-self-end"><button class="btn btn-primary w-100">Simpan aturan</button></div>
            </form>
            @if($rule)<form method="POST" action="{{ route('retail.emergency.rule.delete') }}" class="mt-3">@csrf @method('DELETE')<input type="hidden" name="branch_id" value="{{ $branchId }}"><button class="btn btn-sm btn-light-danger">Hapus aturan dan kembali ke persetujuan otomatis</button></form>@endif
        </x-metronic.card>@endif
    @endcan
@endsection
