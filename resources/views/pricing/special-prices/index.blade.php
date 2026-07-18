@extends('layouts.metronic.app')

@section('title', 'Harga Khusus Pelanggan/Produk - ' . config('app.name'))
@section('page_title', 'Harga Khusus Pelanggan/Produk')

@section('content')
    <x-metronic.card title="Buat Harga Khusus">
        <form method="POST" action="{{ route('pricing.special-prices.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4"><x-metronic.form-group name="customer_id" label="Pelanggan" required><select name="customer_id" class="form-select"><option value="">Pilih pelanggan</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->business_name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="product_id" label="Produk" required><select name="product_id" class="form-select"><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="channel" label="Channel" required><select name="channel" class="form-select"><option value="b2b">B2B</option><option value="retail">Retail</option><option value="pos">POS</option><option value="all">Semua</option></select></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="price" label="Harga" required><input type="number" step="0.01" min="0" name="price" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="branch_id" label="Cabang"><select name="branch_id" class="form-select"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="minimum_qty" label="Min Qty"><input type="number" step="1" min="0" name="minimum_qty" value="1" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="discount_percent" label="Diskon (%)"><input type="number" step="0.01" min="0" max="100" name="discount_percent" value="0" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-2"><x-metronic.form-group name="priority" label="Prioritas"><input type="number" min="1" name="priority" value="50" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="starts_at" label="Mulai" required><input type="date" name="starts_at" value="{{ now()->toDateString() }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-3"><x-metronic.form-group name="ends_at" label="Selesai"><input type="date" name="ends_at" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-6"><x-metronic.form-group name="reason" label="Alasan" required><textarea name="reason" rows="2" class="form-control"></textarea></x-metronic.form-group></div>
            <div class="col-md-6"><x-metronic.form-group name="notes" label="Catatan"><textarea name="notes" rows="2" class="form-control"></textarea></x-metronic.form-group></div>
            <div class="col-md-12"><button class="btn btn-primary" @cannot('prices.update') disabled @endcannot>Simpan Harga Khusus</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Harga Khusus" class="mt-6">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Pelanggan</th><th>Produk</th><th>Scope</th><th>Harga/Diskon</th><th>Periode</th><th>Status</th><th>Alasan</th></tr></thead>
                <tbody>
                @forelse($overrides as $override)
                    <tr>
                        <td>{{ $override->customer?->business_name }}</td>
                        <td>{{ $override->product?->sku }}<div class="text-muted">{{ $override->product?->name }}</div></td>
                        <td>{{ strtoupper($override->channel ?? 'all') }}<div class="text-muted">{{ $override->branch?->name ?? 'Semua cabang' }} · Min qty {{ qty($override->minimum_qty) }}</div></td>
                        <td>Rp {{ number_format((float) $override->price, 0, ',', '.') }}<div class="text-muted">Diskon {{ $override->discount_percent ?? 0 }}%</div></td>
                        <td>{{ $override->starts_at?->format('d/m/Y') }} - {{ $override->ends_at?->format('d/m/Y') ?? 'Tanpa batas' }}</td>
                        <td><x-metronic.status-badge :status="$override->status ?? 'approved'" /></td>
                        <td>{{ $override->reason ?: $override->notes }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada harga khusus" description="Harga khusus customer akan tampil di sini dan tetap masuk resolusi harga deterministik." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $overrides->links() }}
    </x-metronic.card>
@endsection
