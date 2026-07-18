@section('title', 'Stok Opname - ' . config('app.name'))
@section('page_title', 'Stok Opname')
@extends('layouts.metronic.app')

@section('content')
    <x-metronic.page-title title="Stok Opname" description="Jadwalkan, snapshot, hitung fisik, dan koreksi saldo stok melalui approval." />

    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Jadwalkan Opname">
                <form method="POST" action="{{ route('warehouse.stock-opnames.store') }}">
                    @csrf
                    <x-metronic.form-group name="work_location_id" label="Gudang/Cabang" required>
                        <select name="work_location_id" class="form-select form-select-solid" required>
                            <option value="">Pilih lokasi kerja</option>
                            @foreach($workLocations as $location)
                                <option value="{{ $location->id }}" @selected(old('work_location_id') == $location->id)>{{ $location->code }} — {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="warehouse_location_id" label="Zona/Rak/Bin">
                        <select name="warehouse_location_id" class="form-select form-select-solid">
                            <option value="">Semua lokasi detail</option>
                            @foreach($warehouseLocations as $location)
                                <option value="{{ $location->id }}" @selected(old('warehouse_location_id') == $location->id)>{{ $location->full_code }} — {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="category_id" label="Kategori Produk">
                        <select name="category_id" class="form-select form-select-solid">
                            <option value="">Semua kategori</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-6">
                            <x-metronic.form-group name="method" label="Metode" required>
                                <select name="method" class="form-select form-select-solid" required>
                                    <option value="manual" @selected(old('method') === 'manual')>Manual</option>
                                    <option value="scan" @selected(old('method') === 'scan')>Scan</option>
                                    <option value="import" @selected(old('method') === 'import')>Import</option>
                                </select>
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-metronic.form-group name="scheduled_at" label="Tanggal">
                                <input type="date" name="scheduled_at" value="{{ old('scheduled_at', now()->toDateString()) }}" class="form-control form-control-solid">
                            </x-metronic.form-group>
                        </div>
                    </div>
                    <x-metronic.form-group name="pic_user_id" label="PIC">
                        <select name="pic_user_id" class="form-select form-select-solid">
                            <option value="">Gunakan pembuat</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(old('pic_user_id') == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </x-metronic.form-group>
                    <div class="row">
                        <div class="col-md-6"><x-metronic.form-group name="threshold_qty" label="Threshold Qty"><input type="number" step="1" min="0" name="threshold_qty" value="{{ old('threshold_qty', '10') }}" class="form-control form-control-solid"></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="threshold_value" label="Threshold Nilai"><input type="number" step="0.01" min="0" name="threshold_value" value="{{ old('threshold_value', '1000000') }}" class="form-control form-control-solid"></x-metronic.form-group></div>
                    </div>
                    <label class="form-check form-switch form-check-custom form-check-solid mb-3">
                        <input type="hidden" name="blind_count" value="0">
                        <input class="form-check-input" type="checkbox" name="blind_count" value="1" @checked(old('blind_count'))>
                        <span class="form-check-label">Blind count: counter tidak melihat qty sistem</span>
                    </label>
                    <label class="form-check form-switch form-check-custom form-check-solid mb-5">
                        <input type="hidden" name="freeze_stock" value="0">
                        <input class="form-check-input" type="checkbox" name="freeze_stock" value="1" @checked(old('freeze_stock'))>
                        <span class="form-check-label">Tandai freeze stock saat counting</span>
                    </label>
                    <x-metronic.form-group name="notes" label="Catatan">
                        <textarea name="notes" rows="3" class="form-control form-control-solid">{{ old('notes') }}</textarea>
                    </x-metronic.form-group>
                    <div class="d-flex gap-2">
                        <button name="action" value="draft" class="btn btn-light-primary">Simpan Draft</button>
                        <button name="action" value="start" class="btn btn-primary">Buat Snapshot</button>
                    </div>
                </form>
            </x-metronic.card>
        </div>

        <div class="col-lg-8">
            <x-metronic.card title="Daftar Opname">
                <form class="row g-3 mb-5">
                    <div class="col-md-5">
                        <select name="work_location_id" class="form-select form-select-solid">
                            <option value="">Semua gudang/cabang</option>
                            @foreach($workLocations as $location)
                                <option value="{{ $location->id }}" @selected(($filters['work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select form-select-solid">
                            <option value="">Semua status</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><button class="btn btn-light w-100">Filter</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>No</th><th>Scope</th><th>Jadwal/PIC</th><th>Progress</th><th>Selisih</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                        @forelse($opnames as $opname)
                            <tr>
                                <td class="fw-bold">{{ $opname->number }}<div class="text-muted fs-8">{{ ucfirst($opname->method) }}</div></td>
                                <td>{{ $opname->workLocation?->name }}<div class="text-muted fs-8">{{ $opname->warehouseLocation?->full_code ?: 'Semua bin' }}</div></td>
                                <td>{{ $opname->scheduled_at?->format('d/m/Y') ?: '-' }}<div class="text-muted fs-8">{{ $opname->pic?->name ?: '-' }}</div></td>
                                <td>{{ $opname->countedProgress() }}<div class="text-muted fs-8">{{ $opname->items->whereNotNull('counted_qty')->count() }}/{{ $opname->items->count() }} item</div></td>
                                <td>{{ qty($opname->total_difference_qty) }}<div class="text-muted fs-8">{{ \App\Support\CurrencyFormatter::rupiah($opname->total_difference_value) }}</div></td>
                                <td><x-metronic.status-badge :status="$opname->status" /></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-light" href="{{ route('warehouse.stock-opnames.show', $opname) }}">Detail</a>
                                    @can('count', $opname)<a class="btn btn-sm btn-light-primary" href="{{ route('warehouse.stock-opnames.count', $opname) }}">Counting</a>@endcan
                                    <a class="btn btn-sm btn-light-info" href="{{ route('warehouse.stock-opnames.variance', $opname) }}">Variance</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-metronic.empty-state title="Belum ada stok opname" description="Buat jadwal opname pertama untuk mulai snapshot saldo." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $opnames->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection

