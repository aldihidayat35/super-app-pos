@extends('layouts.metronic.app')

@section('title', 'Dashboard Gudang - ' . config('app.name'))
@section('page_title', 'Dashboard Gudang')

@section('toolbar_actions')
    <x-metronic.permission-button permission="stock.create" :href="route('warehouse.location-transfers.index')" icon="ki-outline ki-arrow-right-left">Transfer Lokasi</x-metronic.permission-button>
@endsection

@section('content')
    <div class="row g-5 mb-5">
        <div class="col-md-3"><x-metronic.card title="Stok Tersedia"><div class="fs-2 fw-bold">{{ number_format((float) (($totals->on_hand ?? 0) - ($totals->reserved ?? 0) - ($totals->damaged ?? 0)), 4, ',', '.') }}</div><div class="text-muted">On hand - reserved - rusak</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Reserved"><div class="fs-2 fw-bold">{{ number_format((float) ($totals->reserved ?? 0), 4, ',', '.') }}</div><div class="text-muted">Dialokasikan order</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Rusak"><div class="fs-2 fw-bold">{{ number_format((float) ($totals->damaged ?? 0), 4, ',', '.') }}</div><div class="text-muted">Tidak tersedia dijual</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Nilai Persediaan"><div class="fs-2 fw-bold">Rp {{ number_format((float) ($totals->value ?? 0), 0, ',', '.') }}</div><div class="text-muted">Berdasarkan cost_value</div></x-metronic.card></div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-lg-4"><x-metronic.card title="Stok Kritis/Kosong"><div class="d-flex justify-content-between"><span>Kritis</span><span class="fw-bold">{{ $criticalStocks }}</span></div><div class="d-flex justify-content-between"><span>Kosong</span><span class="fw-bold">{{ $emptyStocks }}</span></div><div class="d-flex justify-content-between"><span>Produk aktif</span><span class="fw-bold">{{ $activeProductCount }}</span></div></x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Barang Masuk/Keluar 30 Hari"><div class="d-flex justify-content-between"><span>Masuk</span><span class="fw-bold text-success">{{ $incomingCount }}</span></div><div class="d-flex justify-content-between"><span>Keluar</span><span class="fw-bold text-danger">{{ $outgoingCount }}</span></div><div class="text-muted mt-3">Grafik 30 hari disiapkan dari data mutasi harian.</div></x-metronic.card></div>
        <div class="col-lg-4"><x-metronic.card title="Order & Transfer Pending"><div class="d-flex justify-content-between"><span>Order pending</span><span class="badge badge-light">Modul berikutnya</span></div><div class="d-flex justify-content-between"><span>Transfer tertunda</span><span class="badge badge-light">Belum ada dokumen approval</span></div><div class="mt-3"><a href="{{ route('warehouse.stocks.index', ['status' => 'critical']) }}" class="btn btn-sm btn-light-primary">Lihat stok kritis</a></div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Mutasi Besar Terbaru">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Lokasi</th><th>Jenis</th><th>Perubahan</th><th>User</th><th></th></tr></thead>
                <tbody>
                @forelse ($largeMutations as $mutation)
                    <tr>
                        <td>{{ $mutation->occurred_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $mutation->product?->sku }} — {{ $mutation->product?->name }}</td>
                        <td>{{ $mutation->warehouseLocation?->full_code ?: $mutation->workLocation?->name }}</td>
                        <td>{{ $mutation->mutation_type->label() }}</td>
                        <td class="fw-bold">{{ $mutation->quantity_on_hand_change }}</td>
                        <td>{{ $mutation->actor?->name ?: '-' }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('warehouse.stock-mutations.show', $mutation) }}">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada mutasi besar" description="Mutasi besar akan tampil setelah stok bergerak." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
@endsection
