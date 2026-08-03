@extends('layouts.metronic.app')

@section('title', 'Detail Gudang - ' . config('app.name'))
@section('page_title', 'Detail Gudang')

@section('page_guide')
    <x-metronic.page-guide id="admin-warehouse-show" title="Panduan Detail Gudang">
        <x-slot:function><p>Halaman menampilkan rincian profil gudang, alamat, dan tab operasional yang berisi histori transaksi dan statistik stok.</p></x-slot:function>
        <x-slot:parts><ul><li><strong>Kode/Nama/Kota:</strong> info profil gudang.</li><li><strong>Telepon/Kepala Gudang/Kapasitas:</strong> kontak dan kapasitas.</li><li><strong>Area Layanan/Status:</strong> wilayah dan aktif/nonaktif.</li><li><strong>Alamat:</strong> alamat lengkap gudang.</li><li><strong>Tab Operasional:</strong> statistik dan histori transaksi gudang.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Halaman ini read-only. Aksi Edit tersedia untuk mengubah data gudang.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa info profil gudang.</li><li>Buka tab operasional untuk statistik.</li><li>Klik Edit jika perlu mengubah data.</li></ol></x-slot:operation>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    @can('update', $warehouse)
        <a href="{{ route('admin.warehouses.edit', $warehouse) }}" class="btn btn-primary"><i class="ki-outline ki-pencil"></i> Edit</a>
    @endcan
@endsection

@section('content')
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Profil Gudang">
                <div class="mb-4"><div class="text-muted fs-7">Kode</div><div class="fw-bold fs-4">{{ $warehouse->code }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Nama</div><div class="fw-semibold">{{ $warehouse->name }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Kota</div><div class="fw-semibold">{{ $warehouse->city ?: '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Telepon</div><div class="fw-semibold">{{ $warehouse->phone_number ?: '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Kepala Gudang</div><div class="fw-semibold">{{ $warehouse->manager?->name ?: '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Kapasitas</div><div class="fw-semibold">{{ $warehouse->capacity ? qty($warehouse->capacity) : '-' }}</div></div>
                <div class="mb-4"><div class="text-muted fs-7">Area Layanan</div><div class="fw-semibold">{{ $warehouse->service_area ?: '-' }}</div></div>
                <x-metronic.status-badge :status="$warehouse->is_active ? 'active' : 'inactive'" :label="$warehouse->is_active ? 'Aktif' : 'Nonaktif'" />
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Alamat">
                <p class="mb-0">{{ $warehouse->address ?: 'Belum ada alamat.' }}</p>
            </x-metronic.card>
            <x-metronic.card title="Tab Operasional" class="mt-6">
                <ul class="nav nav-tabs nav-line-tabs mb-5" id="operationalTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="locations-tab" data-bs-toggle="tab" data-bs-target="#locations" type="button" role="tab">Lokasi Rak</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">User</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="branches-tab" data-bs-toggle="tab" data-bs-target="#branches" type="button" role="tab">Cabang Dilayani</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab">Stok Ringkas</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">Histori</button>
                    </li>
                </ul>
                <div class="tab-content" id="operationalTabContent">
                    {{-- Tab: Lokasi Rak --}}
                    <div class="tab-pane fade show active" id="locations" role="tabpanel">
                        @php
                            $zoneCount = $locationStats->where('type', 'zone')->sum('count');
                            $rackCount = $locationStats->where('type', 'rack')->sum('count');
                            $binCount = $locationStats->where('type', 'bin')->sum('count');
                        @endphp
                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <div class="border rounded p-4 text-center">
                                    <div class="text-muted fs-7 mb-2">Zona</div>
                                    <div class="fw-bold fs-3">{{ $zoneCount }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-4 text-center">
                                    <div class="text-muted fs-7 mb-2">Rak</div>
                                    <div class="fw-bold fs-3">{{ $rackCount }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-4 text-center">
                                    <div class="text-muted fs-7 mb-2">Bin</div>
                                    <div class="fw-bold fs-3">{{ $binCount }}</div>
                                </div>
                            </div>
                        </div>
                        @if($warehouse->warehouseLocations->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle">
                                    <thead>
                                        <tr class="text-muted fw-bold text-uppercase fs-7">
                                            <th>Kode Penuh</th>
                                            <th>Nama</th>
                                            <th>Tipe</th>
                                            <th>Parent</th>
                                            <th>Kapasitas</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($warehouse->warehouseLocations as $location)
                                            <tr>
                                                <td class="fw-bold">{{ $location->full_code }}</td>
                                                <td>{{ $location->name ?: '-' }}</td>
                                                <td>{{ $location->type?->label() ?: '-' }}</td>
                                                <td>{{ $location->parent?->full_code ?: '-' }}</td>
                                                <td>{{ $location->capacity ? qty($location->capacity) : '-' }}</td>
                                                <td>
                                                    <x-metronic.status-badge :status="$location->is_active ? 'active' : 'inactive'" :label="$location->is_active ? 'Aktif' : 'Nonaktif'" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <x-metronic.empty-state title="Belum ada lokasi rak" description="Belum ada zona, rak, atau bin yang dibuat untuk gudang ini." />
                        @endif
                    </div>

                    {{-- Tab: User --}}
                    <div class="tab-pane fade" id="users" role="tabpanel">
                        @if($warehouseUsers->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle">
                                    <thead>
                                        <tr class="text-muted fw-bold text-uppercase fs-7">
                                            <th>Nama</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($warehouseUsers as $user)
                                            <tr>
                                                <td class="fw-bold">{{ $user->name }}</td>
                                                <td>{{ $user->email }}</td>
                                                <td>
                                                    @foreach($user->roles as $role)
                                                        <span class="badge bg-light text-dark me-1">{{ $role->name }}</span>
                                                    @endforeach
                                                </td>
                                                <td>
                                                    <x-metronic.status-badge :status="$user->is_active ? 'active' : 'inactive'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <x-metronic.empty-state title="Belum ada user" description="Belum ada user yang ditugaskan di gudang ini." />
                        @endif
                    </div>

                    {{-- Tab: Cabang Dilayani --}}
                    <div class="tab-pane fade" id="branches" role="tabpanel">
                        @if($warehouse->branches->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle">
                                    <thead>
                                        <tr class="text-muted fw-bold text-uppercase fs-7">
                                            <th>Kode</th>
                                            <th>Nama</th>
                                            <th>Kota</th>
                                            <th>Kepala Toko</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($warehouse->branches as $branch)
                                            <tr>
                                                <td class="fw-bold">{{ $branch->code }}</td>
                                                <td>{{ $branch->name }}</td>
                                                <td>{{ $branch->workLocation?->name ?: '-' }}</td>
                                                <td>{{ $branch->manager?->name ?: '-' }}</td>
                                                <td>
                                                    <x-metronic.status-badge :status="$branch->is_active ? 'active' : 'inactive'" :label="$branch->is_active ? 'Aktif' : 'Nonaktif'" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <x-metronic.empty-state title="Belum ada cabang" description="Gudang ini belum melayani cabang mana pun." />
                        @endif
                    </div>

                    {{-- Tab: Stok Ringkas --}}
                    <div class="tab-pane fade" id="stock" role="tabpanel">
                        @if($stockSummary && $stockSummary->total_products > 0)
                            <div class="row g-4 mb-4">
                                <div class="col-md-3">
                                    <div class="border rounded p-4 text-center">
                                        <div class="text-muted fs-7 mb-2">Total Produk</div>
                                        <div class="fw-bold fs-3">{{ $stockSummary->total_products }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-4 text-center">
                                        <div class="text-muted fs-7 mb-2">On Hand</div>
                                        <div class="fw-bold fs-3">{{ qty($stockSummary->total_on_hand) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-4 text-center">
                                        <div class="text-muted fs-7 mb-2">Reserved</div>
                                        <div class="fw-bold fs-3">{{ qty($stockSummary->total_reserved) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-4 text-center">
                                        <div class="text-muted fs-7 mb-2">Nilai HPP</div>
                                        <div class="fw-bold fs-5">Rp {{ number_format((float) $stockSummary->total_value, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <i class="ki-outline ki-information ki-outline fs-2 me-2"></i>
                                Data stok mencakup semua produk di lokasi rak gudang ini.
                                <a href="{{ route('warehouse.stocks.index', ['warehouse_location_id' => $warehouse->warehouseLocations->pluck('id')->join(',') ]) }}" class="alert-link">Lihat detail stok →</a>
                            </div>
                        @else
                            <x-metronic.empty-state title="Belum ada stok" description="Gudang ini belum memiliki data stok produk." />
                        @endif
                    </div>

                    {{-- Tab: Histori --}}
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        @if($recentMutations->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle">
                                    <thead>
                                        <tr class="text-muted fw-bold text-uppercase fs-7">
                                            <th>Waktu</th>
                                            <th>Produk</th>
                                            <th>Jenis</th>
                                            <th>Lokasi</th>
                                            <th>Perubahan</th>
                                            <th>Referensi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentMutations as $mutation)
                                            <tr>
                                                <td>{{ $mutation->occurred_at?->format('d/m/Y H:i') }}</td>
                                                <td>
                                                    <div class="fw-bold">{{ $mutation->product?->sku }}</div>
                                                    <div class="text-muted fs-7">{{ $mutation->product?->name }}</div>
                                                </td>
                                                <td>{{ $mutation->mutation_type?->label() }}</td>
                                                <td>{{ $mutation->warehouseLocation?->full_code ?: '-' }}</td>
                                                <td class="{{ (float)$mutation->quantity_on_hand_change > 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $mutation->quantity_on_hand_change > 0 ? '+' : '' }}{{ qty($mutation->quantity_on_hand_change) }}
                                                </td>
                                                <td>{{ $mutation->reference_no ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="{{ route('warehouse.stock-card.index', ['work_location_id' => $warehouse->work_location_id]) }}" class="btn btn-light">
                                    Lihat Semua Histori
                                </a>
                            </div>
                        @else
                            <x-metronic.empty-state title="Belum ada histori" description="Belum ada mutasi stok untuk gudang ini." />
                        @endif
                    </div>
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
