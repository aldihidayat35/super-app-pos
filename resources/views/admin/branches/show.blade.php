@extends('layouts.metronic.app')

@section('title', 'Detail Cabang - ' . config('app.name'))
@section('page_title', 'Detail Cabang')

@section('toolbar_actions')
    @can('update', $branch)
        <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-primary"><i class="ki-outline ki-pencil"></i> Edit</a>
    @endcan
@endsection

@section('content')
    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Profil Cabang">
                <div class="mb-4">
                    <div class="text-muted fs-7">Kode</div>
                    <div class="fw-bold fs-4">{{ $branch->code }}</div>
                </div>
                <div class="mb-4">
                    <div class="text-muted fs-7">Nama Toko</div>
                    <div class="fw-semibold">{{ $branch->name }}</div>
                </div>
                <div class="mb-4">
                    <div class="text-muted fs-7">Gudang Pemasok</div>
                    <div class="fw-semibold">{{ $branch->primaryWarehouse?->name ?: '-' }}</div>
                </div>
                <div class="mb-4">
                    <div class="text-muted fs-7">Kepala Toko</div>
                    <div class="fw-semibold">{{ $branch->manager?->name ?: '-' }}</div>
                </div>
                <div class="mb-4">
                    <div class="text-muted fs-7">Telepon</div>
                    <div class="fw-semibold">{{ $branch->phone_number ?: '-' }}</div>
                </div>
                <div class="mb-4">
                    <div class="text-muted fs-7">Target Penjualan</div>
                    <div class="fw-semibold">{{ $branch->sales_target ? \App\Support\CurrencyFormatter::rupiah($branch->sales_target) : '-' }}</div>
                </div>
                <div class="mb-4">
                    <div class="text-muted fs-7">Harga/Closing</div>
                    <div class="fw-semibold">{{ $branch->price_configuration }} · {{ $branch->closing_configuration }} · {{ $branch->is_closing_required ? 'Closing wajib' : 'Closing opsional' }}</div>
                </div>
                <x-metronic.status-badge :status="$branch->is_active ? 'active' : 'inactive'" :label="$branch->is_active ? 'Aktif' : 'Nonaktif'" />
            </x-metronic.card>
        </div>

        <div class="col-lg-8">
            <x-metronic.card title="Alamat">
                <p class="mb-0">{{ $branch->address ?: 'Belum ada alamat.' }}</p>
            </x-metronic.card>

            <x-metronic.card title="Tab Operasional" class="mt-6">
                <ul class="nav nav-tabs nav-line-tabs mb-5" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="branch-users-tab" data-bs-toggle="tab" data-bs-target="#branch-users-pane" type="button" role="tab" aria-controls="branch-users-pane" aria-selected="true">User</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="branch-stocks-tab" data-bs-toggle="tab" data-bs-target="#branch-stocks-pane" type="button" role="tab" aria-controls="branch-stocks-pane" aria-selected="false">Stok</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="branch-shifts-tab" data-bs-toggle="tab" data-bs-target="#branch-shifts-pane" type="button" role="tab" aria-controls="branch-shifts-pane" aria-selected="false">Shift</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="branch-performance-tab" data-bs-toggle="tab" data-bs-target="#branch-performance-pane" type="button" role="tab" aria-controls="branch-performance-pane" aria-selected="false">Performa</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="branch-history-tab" data-bs-toggle="tab" data-bs-target="#branch-history-pane" type="button" role="tab" aria-controls="branch-history-pane" aria-selected="false">Histori</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="branch-users-pane" role="tabpanel" aria-labelledby="branch-users-tab" tabindex="0">
                        <div class="row g-4 mb-5">
                            <div class="col-md-6">
                                <div class="border rounded p-4">
                                    <div class="text-muted fs-7">User Lokasi</div>
                                    <div class="fw-bold">{{ $branch->workLocation?->users->count() ?? 0 }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-4">
                                    <div class="text-muted fs-7">Gudang Pemasok</div>
                                    <div class="fw-bold">{{ $branch->primaryWarehouse?->code ?: '-' }}</div>
                                </div>
                            </div>
                        </div>

                        @forelse ($branch->workLocation?->users ?? collect() as $user)
                            <div class="d-flex justify-content-between align-items-center border rounded p-3 mb-3">
                                <div>
                                    <div class="fw-bold">{{ $user->name }}</div>
                                    <div class="text-muted fs-7">{{ $user->email }} @if ($user->pivot?->is_default) · Default @endif</div>
                                </div>
                                <x-metronic.status-badge :status="$user->pivot?->is_active ? 'active' : 'inactive'" :label="$user->pivot?->is_active ? 'Aktif' : 'Nonaktif'" />
                            </div>
                        @empty
                            <x-metronic.empty-state title="Belum ada user" description="Belum ada pengguna yang ditugaskan ke cabang ini." />
                        @endforelse
                    </div>

                    <div class="tab-pane fade" id="branch-stocks-pane" role="tabpanel" aria-labelledby="branch-stocks-tab" tabindex="0">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle">
                                <thead>
                                    <tr class="text-muted fw-bold text-uppercase fs-7">
                                        <th>Produk</th>
                                        <th>On Hand</th>
                                        <th>Reserved</th>
                                        <th>Rusak</th>
                                        <th>Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($stocks as $stock)
                                        <tr>
                                            <td>
                                                <div class="fw-bold">{{ $stock->product?->name ?: '-' }}</div>
                                                <div class="text-muted fs-8">{{ $stock->product?->sku ?: '-' }}</div>
                                            </td>
                                            <td>{{ qty($stock->quantity_on_hand) }}</td>
                                            <td>{{ qty($stock->quantity_reserved) }}</td>
                                            <td>{{ qty($stock->quantity_damaged) }}</td>
                                            <td class="fw-bold">{{ qty($stock->available_quantity) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5">
                                                <x-metronic.empty-state title="Belum ada stok" description="Saldo stok cabang akan tampil setelah ada penerimaan/transfer." />
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="branch-shifts-pane" role="tabpanel" aria-labelledby="branch-shifts-tab" tabindex="0">
                        @forelse ($shifts as $shift)
                            <div class="d-flex justify-content-between align-items-center border rounded p-3 mb-3">
                                <div>
                                    <div class="fw-bold">{{ $shift->number }}</div>
                                    <div class="text-muted fs-7">{{ $shift->cashier?->name ?: '-' }} · {{ $shift->opened_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?: '-' }}</div>
                                </div>
                                <x-metronic.status-badge :status="$shift->status->value" :label="$shift->status->label()" />
                            </div>
                        @empty
                            <x-metronic.empty-state title="Belum ada shift" description="Shift kasir cabang ini belum tercatat." />
                        @endforelse
                    </div>

                    <div class="tab-pane fade" id="branch-performance-pane" role="tabpanel" aria-labelledby="branch-performance-tab" tabindex="0">
                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <div class="border rounded p-4">
                                    <div class="text-muted fs-7">Total Transaksi</div>
                                    <div class="fw-bold">{{ (int) ($salesSummary?->total_sales ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-4">
                                    <div class="text-muted fs-7">Omzet</div>
                                    <div class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah((string) ($salesSummary?->total_revenue ?? 0)) }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-4">
                                    <div class="text-muted fs-7">Margin</div>
                                    <div class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah((string) ($salesSummary?->total_margin ?? 0)) }}</div>
                                </div>
                            </div>
                        </div>

                        @forelse ($recentSales as $sale)
                            <div class="d-flex justify-content-between align-items-center border rounded p-3 mb-3">
                                <div>
                                    <div class="fw-bold">{{ $sale->number }}</div>
                                    <div class="text-muted fs-7">{{ $sale->cashier?->name ?: '-' }} · {{ $sale->completed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?: '-' }}</div>
                                </div>
                                <div class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($sale->grand_total_amount) }}</div>
                            </div>
                        @empty
                            <x-metronic.empty-state title="Belum ada penjualan" description="Performa akan terisi setelah ada transaksi POS." />
                        @endforelse
                    </div>

                    <div class="tab-pane fade" id="branch-history-pane" role="tabpanel" aria-labelledby="branch-history-tab" tabindex="0">
                        @forelse ($histories as $history)
                            <div class="border rounded p-3 mb-3">
                                <div class="fw-bold">{{ $history->description }}</div>
                                <div class="text-muted fs-7">{{ $history->causer?->name ?: 'Sistem' }} · {{ $history->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div>
                            </div>
                        @empty
                            <x-metronic.empty-state title="Belum ada histori" description="Riwayat perubahan cabang akan tampil setelah ada aktivitas." />
                        @endforelse
                    </div>
                </div>
            </x-metronic.card>
        </div>
    </div>
@endsection
