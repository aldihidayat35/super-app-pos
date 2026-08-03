@extends('layouts.metronic.app')

@section('title', 'Detail Cabang - ' . config('app.name'))
@section('page_title', 'Detail Cabang')

@section('page_guide')
    <x-metronic.page-guide id="admin-branch-show" title="Panduan Dashboard Cabang">
        <x-slot:function><p>Halaman ini menjadi pusat informasi dan pintasan operasional untuk satu cabang/toko.</p></x-slot:function>
        <x-slot:parts><ul><li><strong>Kartu ringkasan:</strong> menampilkan user aktif, saldo tersedia, shift berjalan, dan omzet.</li><li><strong>Tab operasional:</strong> mengelompokkan profil, tim, stok, shift, penjualan, dan histori.</li><li><strong>Tombol aksi:</strong> membuka halaman terkait dengan cabang ini sudah terpilih.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Data yang tampil mengikuti permission dan cakupan lokasi kerja pengguna. Nilai margin hanya terlihat oleh pengguna yang berhak.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa status dan KPI cabang.</li><li>Pilih tab sesuai pekerjaan.</li><li>Gunakan tombol aksi di kanan judul tab agar cabang tidak perlu dipilih ulang.</li></ol></x-slot:operation>
        <x-slot:warnings><p>Jika sebuah tab menampilkan pesan akses terbatas, minta administrator meninjau role dan penugasan lokasi kerja Anda.</p></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('admin.branches.index') }}" class="btn btn-light">
        <i class="ki-outline ki-arrow-left fs-5 me-1"></i>Daftar Cabang
    </a>
    @can('update', $branch)
        <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-primary">
            <i class="ki-outline ki-pencil fs-5 me-1"></i>Edit Cabang
        </a>
    @endcan
@endsection

@section('content')
    <div class="card mb-6 overflow-hidden">
        <div class="card-body p-5 p-lg-8">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-6">
                <div class="d-flex align-items-center gap-5">
                    <div class="symbol symbol-70px symbol-lg-90px flex-shrink-0">
                        <span class="symbol-label bg-light-primary text-primary fs-2x fw-bold">
                            {{ str($branch->name)->substr(0, 2)->upper() }}
                        </span>
                    </div>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h1 class="fs-2 fw-bold text-gray-900 mb-0">{{ $branch->name }}</h1>
                            <x-metronic.status-badge :status="$branch->is_active ? 'active' : 'inactive'" :label="$branch->is_active ? 'Aktif' : 'Nonaktif'" />
                        </div>
                        <div class="text-muted fw-semibold mb-3">{{ $branch->code }} · {{ $branch->workLocation?->code ?: 'Lokasi kerja belum terbentuk' }}</div>
                        <div class="d-flex flex-wrap gap-4 text-gray-700 fs-7">
                            <span><i class="ki-outline ki-geolocation fs-5 text-primary me-1"></i>{{ $branch->address ?: 'Alamat belum diisi' }}</span>
                            <span><i class="ki-outline ki-phone fs-5 text-primary me-1"></i>{{ $branch->phone_number ?: 'Telepon belum diisi' }}</span>
                            <span><i class="ki-outline ki-user fs-5 text-primary me-1"></i>{{ $branch->manager?->name ?: 'Kepala toko belum ditentukan' }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if($access['pos_create'])
                        <a href="{{ route('retail.pos.index', ['branch_id' => $branch->id]) }}" class="btn btn-sm btn-light-success">
                            <i class="ki-outline ki-basket fs-5 me-1"></i>Buka POS
                        </a>
                    @endif
                    @if($access['restock_create'])
                        <a href="{{ route('retail.restock-requests.index', ['branch_id' => $branch->id]) }}" class="btn btn-sm btn-light-primary">
                            <i class="ki-outline ki-delivery fs-5 me-1"></i>Buat Restock
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!$branch->work_location_id)
        <div class="alert alert-warning d-flex align-items-center mb-6">
            <i class="ki-outline ki-information-5 fs-2 me-3"></i>
            <div><strong>Lokasi kerja cabang belum tersinkron.</strong> Edit dan simpan cabang agar stok, user, serta transaksi dapat dicakup dengan benar.</div>
        </div>
    @endif

    <div class="row g-5 mb-6">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div><div class="text-muted fw-semibold mb-2">User Aktif</div><div class="fs-2x fw-bold text-gray-900">{{ $metrics['active_users'] ?? '—' }}</div><div class="text-muted fs-8">Ditugaskan ke cabang</div></div>
                <span class="symbol symbol-50px"><span class="symbol-label bg-light-primary"><i class="ki-outline ki-people fs-2x text-primary"></i></span></span>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div><div class="text-muted fw-semibold mb-2">Stok Tersedia</div><div class="fs-2x fw-bold text-gray-900">{{ $metrics['available_stock'] !== null ? qty($metrics['available_stock']) : '—' }}</div><div class="text-muted fs-8">{{ $metrics['stock_products'] !== null ? $metrics['stock_products'].' produk bersaldo' : 'Akses stok terbatas' }}</div></div>
                <span class="symbol symbol-50px"><span class="symbol-label bg-light-success"><i class="ki-outline ki-package fs-2x text-success"></i></span></span>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div><div class="text-muted fw-semibold mb-2">Shift Berjalan</div><div class="fs-2x fw-bold text-gray-900">{{ $metrics['open_shifts'] ?? '—' }}</div><div class="text-muted fs-8">Shift berstatus terbuka</div></div>
                <span class="symbol symbol-50px"><span class="symbol-label bg-light-warning"><i class="ki-outline ki-time fs-2x text-warning"></i></span></span>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div><div class="text-muted fw-semibold mb-2">Omzet Tercatat</div><div class="fs-4 fw-bold text-gray-900">{{ $metrics['total_revenue'] !== null ? App\Support\CurrencyFormatter::rupiah($metrics['total_revenue']) : '—' }}</div><div class="text-muted fs-8">{{ $metrics['total_sales'] !== null ? $metrics['total_sales'].' transaksi selesai' : 'Akses penjualan terbatas' }}</div></div>
                <span class="symbol symbol-50px"><span class="symbol-label bg-light-info"><i class="ki-outline ki-chart-line-up fs-2x text-info"></i></span></span>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-2 px-4 px-lg-7">
            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-semibold flex-nowrap overflow-auto w-100" role="tablist">
                @foreach([
                    ['branch-summary', 'branch-summary-pane', 'ki-profile-circle', 'Ringkasan'],
                    ['branch-users', 'branch-users-pane', 'ki-people', 'Tim & Akses'],
                    ['branch-stocks', 'branch-stocks-pane', 'ki-package', 'Stok & Restock'],
                    ['branch-shifts', 'branch-shifts-pane', 'ki-time', 'Shift & Kas'],
                    ['branch-performance', 'branch-performance-pane', 'ki-chart-line-up', 'Penjualan'],
                    ['branch-history', 'branch-history-pane', 'ki-time', 'Histori'],
                ] as [$tabId, $paneId, $icon, $label])
                    <li class="nav-item flex-shrink-0" role="presentation">
                        <button class="nav-link text-active-primary py-4 px-3 px-lg-4 {{ $loop->first ? 'active' : '' }}" id="{{ $tabId }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $paneId }}" type="button" role="tab" aria-controls="{{ $paneId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            <i class="ki-outline {{ $icon }} fs-4 me-2"></i>{{ $label }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body p-4 p-lg-7">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="branch-summary-pane" role="tabpanel" aria-labelledby="branch-summary-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6">
                        <div><h2 class="fs-3 fw-bold mb-1">Profil & Konfigurasi Cabang</h2><div class="text-muted">Informasi organisasi dan aturan operasional yang sedang berlaku.</div></div>
                        @can('update', $branch)<a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-pencil fs-5 me-1"></i>Ubah Profil</a>@endcan
                    </div>
                    <div class="row g-6">
                        <div class="col-lg-6">
                            <div class="border border-gray-300 rounded p-5 h-100">
                                <h3 class="fs-5 fw-bold mb-5"><i class="ki-outline ki-home-2 fs-3 text-primary me-2"></i>Identitas & Kontak</h3>
                                <div class="row g-4">
                                    @foreach([
                                        ['Kode Cabang', $branch->code],
                                        ['Nama Cabang', $branch->name],
                                        ['Kepala Toko', $branch->manager?->name],
                                        ['Telepon', $branch->phone_number],
                                        ['Kode Lokasi Kerja', $branch->workLocation?->code],
                                        ['Alamat', $branch->address],
                                    ] as [$label, $value])
                                        <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">{{ $label }}</div><div class="fw-semibold text-gray-800 text-break">{{ $value ?: '—' }}</div></div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="border border-gray-300 rounded p-5 h-100">
                                <h3 class="fs-5 fw-bold mb-5"><i class="ki-outline ki-setting-2 fs-3 text-success me-2"></i>Aturan Operasional</h3>
                                <div class="row g-4">
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Gudang Pemasok</div><div class="fw-semibold">{{ $branch->primaryWarehouse?->name ?: '—' }}</div></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Target Penjualan</div><div class="fw-semibold">{{ $branch->sales_target ? App\Support\CurrencyFormatter::rupiah($branch->sales_target) : '—' }}</div></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Konfigurasi Harga</div><div class="fw-semibold text-capitalize">{{ str($branch->price_configuration)->replace('_', ' ') }}</div></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Periode Closing</div><div class="fw-semibold text-capitalize">{{ str($branch->closing_configuration)->replace('_', ' ') }}</div></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Kewajiban Closing</div><span class="badge {{ $branch->is_closing_required ? 'badge-light-warning' : 'badge-light-secondary' }}">{{ $branch->is_closing_required ? 'Wajib' : 'Opsional' }}</span></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Status Cabang</div><x-metronic.status-badge :status="$branch->is_active ? 'active' : 'inactive'" :label="$branch->is_active ? 'Aktif' : 'Nonaktif'" /></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="branch-users-pane" role="tabpanel" aria-labelledby="branch-users-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Tim & Akses Lokasi</h2><div class="text-muted">Pengguna yang dapat bekerja dan melihat data cabang ini.</div></div>
                        <div class="d-flex flex-wrap gap-2">
                            @if($access['users'])<a href="{{ route('admin.users.index', ['location' => $branch->work_location_id]) }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-eye fs-5 me-1"></i>Lihat Semua User</a>@endif
                            @if($access['users_create'] && $branch->work_location_id)<a href="{{ route('admin.users.create', ['location' => $branch->work_location_id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Tambah User Cabang</a>@endif
                        </div>
                    </div>
                    @if(!$access['users'])
                        <x-metronic.empty-state title="Akses pengguna tidak tersedia" description="Akun Anda tidak memiliki permission untuk melihat penugasan pengguna cabang." icon="ki-outline ki-lock" />
                    @else
                        <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Pengguna</th><th>Role</th><th>Penugasan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
                        @forelse($assignedUsers as $assignedUser)
                            <tr><td><div class="fw-bold">{{ $assignedUser->name }}</div><div class="text-muted fs-8">{{ $assignedUser->email }}</div></td><td>{{ $assignedUser->roles->pluck('name')->map(fn ($role) => str($role)->replace('_', ' ')->title())->join(', ') ?: 'Belum ada role' }}</td><td>{{ $assignedUser->pivot?->is_default ? 'Lokasi utama' : 'Lokasi tambahan' }}</td><td><x-metronic.status-badge :status="$assignedUser->is_active && $assignedUser->pivot?->is_active ? 'active' : 'inactive'" :label="$assignedUser->is_active && $assignedUser->pivot?->is_active ? 'Aktif' : 'Nonaktif'" /></td><td class="text-end"><a href="{{ route('admin.users.show', $assignedUser) }}" class="btn btn-sm btn-light-primary">Detail</a></td></tr>
                        @empty
                            <tr><td colspan="5"><x-metronic.empty-state title="Belum ada user cabang" description="Tambahkan pengguna dan pilih cabang ini sebagai lokasi kerjanya." icon="ki-outline ki-people" /></td></tr>
                        @endforelse
                        </tbody></table></div>
                    @endif
                </div>

                <div class="tab-pane fade" id="branch-stocks-pane" role="tabpanel" aria-labelledby="branch-stocks-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Saldo Stok & Restock</h2><div class="text-muted">Delapan saldo produk terbesar di lokasi kerja cabang.</div></div>
                        <div class="d-flex flex-wrap gap-2">
                            @if($access['stock'] && $branch->work_location_id)<a href="{{ route('warehouse.stocks.index', ['work_location_id' => $branch->work_location_id]) }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-eye fs-5 me-1"></i>Lihat Semua Stok</a>@endif
                            @if($access['restock_create'])<a href="{{ route('retail.restock-requests.index', ['branch_id' => $branch->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Buat Restock</a>@endif
                        </div>
                    </div>
                    @if(!$access['stock'])
                        <x-metronic.empty-state title="Akses stok tidak tersedia" description="Akun Anda tidak memiliki permission untuk melihat saldo stok cabang." icon="ki-outline ki-lock" />
                    @else
                        <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Produk</th><th>On Hand</th><th>Reserved</th><th>Rusak</th><th>Tersedia</th></tr></thead><tbody>
                        @forelse($stocks as $stock)
                            <tr><td><div class="fw-bold">{{ $stock->product?->name ?: '—' }}</div><div class="text-muted fs-8">{{ $stock->product?->sku ?: '—' }}</div></td><td>{{ qty($stock->quantity_on_hand) }}</td><td>{{ qty($stock->quantity_reserved) }}</td><td>{{ qty($stock->quantity_damaged) }}</td><td class="fw-bold text-success">{{ qty($stock->available_quantity) }}</td></tr>
                        @empty
                            <tr><td colspan="5"><x-metronic.empty-state title="Belum ada saldo stok" description="Stok cabang akan tampil setelah transfer atau penerimaan diposting." icon="ki-outline ki-package" /></td></tr>
                        @endforelse
                        </tbody></table></div>
                    @endif
                </div>

                <div class="tab-pane fade" id="branch-shifts-pane" role="tabpanel" aria-labelledby="branch-shifts-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Shift & Kas</h2><div class="text-muted">Shift kasir terbaru beserta status closing-nya.</div></div>
                        <div class="d-flex flex-wrap gap-2">
                            @if($access['shifts'])<a href="{{ route('retail.shifts.index', ['branch_id' => $branch->id]) }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-eye fs-5 me-1"></i>Lihat Semua Shift</a>@endif
                            @if($access['shifts_create'])<a href="{{ route('retail.shifts.open', ['branch_id' => $branch->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Buka Shift</a>@endif
                        </div>
                    </div>
                    @if(!$access['shifts'])
                        <x-metronic.empty-state title="Akses shift tidak tersedia" description="Akun Anda tidak memiliki permission untuk melihat shift kas cabang." icon="ki-outline ki-lock" />
                    @else
                        @forelse($shifts as $shift)
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border border-gray-300 rounded p-4 mb-3"><div><div class="fw-bold">{{ $shift->number }}</div><div class="text-muted fs-7">{{ $shift->cashier?->name ?: 'Kasir belum tersedia' }} · {{ $shift->opened_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') ?: '—' }}</div></div><x-metronic.status-badge :status="$shift->status->value" :label="$shift->status->label()" /></div>
                        @empty
                            <x-metronic.empty-state title="Belum ada shift" description="Gunakan tombol Buka Shift untuk memulai operasional kasir di cabang ini." icon="ki-outline ki-time" />
                        @endforelse
                    @endif
                </div>

                <div class="tab-pane fade" id="branch-performance-pane" role="tabpanel" aria-labelledby="branch-performance-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Penjualan & Performa</h2><div class="text-muted">Ringkasan transaksi selesai dan aktivitas penjualan terbaru.</div></div>
                        <div class="d-flex flex-wrap gap-2">
                            @if($access['reports'] && $branch->work_location_id)<a href="{{ route('reports.retail.index', ['work_location_id' => $branch->work_location_id]) }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-chart fs-5 me-1"></i>Laporan Retail</a>@endif
                            @if($access['pos_create'])<a href="{{ route('retail.pos.index', ['branch_id' => $branch->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-basket fs-5 me-1"></i>Transaksi POS</a>@endif
                        </div>
                    </div>
                    @if(!$access['sales'])
                        <x-metronic.empty-state title="Akses penjualan tidak tersedia" description="Akun Anda tidak memiliki permission untuk melihat performa penjualan cabang." icon="ki-outline ki-lock" />
                    @else
                        <div class="row g-4 mb-6">
                            <div class="col-md-4"><div class="border border-gray-300 rounded p-4"><div class="text-muted fs-7">Total Transaksi</div><div class="fs-2 fw-bold">{{ (int) ($salesSummary['total_sales'] ?? 0) }}</div></div></div>
                            <div class="col-md-4"><div class="border border-gray-300 rounded p-4"><div class="text-muted fs-7">Omzet</div><div class="fs-4 fw-bold">{{ App\Support\CurrencyFormatter::rupiah((string) ($salesSummary['total_revenue'] ?? 0)) }}</div></div></div>
                            <div class="col-md-4"><div class="border border-gray-300 rounded p-4"><div class="text-muted fs-7">Margin</div>@if($access['margin'])<div class="fs-4 fw-bold">{{ App\Support\CurrencyFormatter::rupiah((string) ($salesSummary['total_margin'] ?? 0)) }}</div>@else<div class="text-muted fw-semibold"><i class="ki-outline ki-lock fs-5 me-1"></i>Data sensitif</div>@endif</div></div>
                        </div>
                        <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Transaksi</th><th>Kasir</th><th>Waktu</th><th>Total</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
                        @forelse($recentSales as $sale)
                            <tr><td class="fw-bold">{{ $sale->number }}</td><td>{{ $sale->cashier?->name ?: '—' }}</td><td>{{ $sale->completed_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') ?: '—' }}</td><td class="fw-semibold">{{ App\Support\CurrencyFormatter::rupiah($sale->grand_total_amount) }}</td><td><x-metronic.status-badge :status="$sale->status->value" :label="$sale->status->label()" /></td><td class="text-end">@if(auth()->user()?->can('pos.view'))<a href="{{ route('retail.sales.show', $sale) }}" class="btn btn-sm btn-light-primary">Detail</a>@else<span class="text-muted">—</span>@endif</td></tr>
                        @empty
                            <tr><td colspan="6"><x-metronic.empty-state title="Belum ada penjualan" description="Performa cabang akan terisi setelah transaksi POS selesai." icon="ki-outline ki-chart-line-up" /></td></tr>
                        @endforelse
                        </tbody></table></div>
                    @endif
                </div>

                <div class="tab-pane fade" id="branch-history-pane" role="tabpanel" aria-labelledby="branch-history-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Histori Perubahan Cabang</h2><div class="text-muted">Jejak perubahan master cabang, pelaku, dan waktunya.</div></div>
                        @can('update', $branch)<a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-pencil fs-5 me-1"></i>Ubah Cabang</a>@endcan
                    </div>
                    @if(!$access['audit'])
                        <x-metronic.empty-state title="Akses histori tidak tersedia" description="Akun Anda tidak memiliki permission audit untuk melihat riwayat perubahan." icon="ki-outline ki-lock" />
                    @else
                        <div class="timeline-label">
                            @forelse($histories as $history)
                                <div class="timeline-item"><div class="timeline-label fw-semibold text-gray-600 fs-7">{{ $history->created_at?->timezone('Asia/Jakarta')->format('H:i') }}</div><div class="timeline-badge"><i class="ki-outline ki-right fs-3 text-primary"></i></div><div class="timeline-content ps-3"><div class="fw-bold text-gray-800">{{ $history->description }}</div><div class="text-muted fs-7">{{ $history->causer?->name ?: 'Sistem' }} · {{ $history->created_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y') }}</div></div></div>
                            @empty
                                <x-metronic.empty-state title="Belum ada histori" description="Perubahan cabang yang diaudit akan tampil di bagian ini." icon="ki-outline ki-time" />
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
