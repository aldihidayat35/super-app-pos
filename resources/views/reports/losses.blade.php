@extends('layouts.metronic.app')
@section('title', 'Laporan Loss - ' . config('app.name'))
@section('page_title', 'Laporan Loss Tracking')

@section('toolbar_actions')
    <a href="{{ route('warehouse.losses.index') }}" class="btn btn-primary">
        <i class="ki-outline ki-plus fs-5 me-2"></i>Kelola Loss
    </a>
    <button type="button" class="btn btn-light-success" onclick="exportLosses()">
        <i class="ki-outline ki-file-down fs-5 me-2"></i>Export
    </button>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="reports-losses" title="Panduan Laporan Loss Tracking">
        <x-slot:function><p>Halaman ini menampilkan laporan kerugian inventori (loss) per produk, lokasi, dan periode. Data loss digunakan untuk analisis efisiensi operasional dan pengendalian stok.</p></x-slot:function>
        <x-slot:parts>
            <ul>
                <li><strong>Filter Periode:</strong> rentang tanggal pelaporan loss.</li>
                <li><strong>Summary Cards:</strong> total loss value, quantity, dan rata-rata per transaksi.</li>
                <li><strong>Breakdown by Type:</strong> ringkasan loss berdasarkan jenis (spoilage, theft, damaged, dll).</li>
                <li><strong>Detail Table:</strong> daftar lengkap loss dengan nomor, produk, lokasi, nilai, dan status.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts><p>Loss tracking membantu identifikasi pola kerugian dan perbaikan kontrol inventori.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Pilih rentang tanggal filter.</li><li>Periksa summary cards untuk gambaran umum.</li><li>Lihat breakdown per jenis loss.</li><li>Scroll ke tabel detail untuk analisis per transaksi.</li></ol></x-slot:operation>
    </x-metronic.page-guide>
@endsection

@section('content')
    @php
        $totalLossValue = $losses->sum(fn($loss) => (float) $loss->loss_value);
        $totalLossQty = $losses->sum(fn($loss) => (float) $loss->quantity);
        $avgLossValue = $losses->total() > 0 ? $totalLossValue / $losses->total() : 0;
        $topReason = $byReason->first();
    @endphp

    {{-- Filter Section --}}
    <x-metronic.card class="mb-6">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted fs-7">Tanggal Mulai</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted fs-7">Tanggal Selesai</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ki-outline ki-search fs-5 me-2"></i>Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('reports.losses.index') }}" class="btn btn-light w-100">
                    <i class="ki-outline ki-refresh fs-5 me-2"></i>Reset
                </a>
            </div>
        </form>
    </x-metronic.card>

    {{-- Summary Cards --}}
    <div class="row g-4 mb-6">
        <div class="col-md-3">
            <x-metronic.card class="h-100">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-60px symbol-circle bg-light-danger text-danger me-4">
                        <i class="ki-outline ki-exclamation-triangle fs-2x"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted fs-7 mb-1">Total Nilai Loss</div>
                        <div class="fw-bold fs-2 text-danger">Rp {{ number_format($totalLossValue, 0, ',', '.') }}</div>
                        <div class="text-muted fs-7">{{ $losses->total() }} transaksi</div>
                    </div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-md-3">
            <x-metronic.card class="h-100">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-60px symbol-circle bg-light-warning text-warning me-4">
                        <i class="ki-outline ki-package fs-2x"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted fs-7 mb-1">Total Qty Loss</div>
                        <div class="fw-bold fs-2 text-warning">{{ qty($totalLossQty) }}</div>
                        <div class="text-muted fs-7">Unit</div>
                    </div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-md-3">
            <x-metronic.card class="h-100">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-60px symbol-circle bg-light-info text-info me-4">
                        <i class="ki-outline ki-chart-line fs-2x"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted fs-7 mb-1">Rata-rata per Transaksi</div>
                        <div class="fw-bold fs-2 text-info">Rp {{ number_format($avgLossValue, 0, ',', '.') }}</div>
                        <div class="text-muted fs-7">per loss</div>
                    </div>
                </div>
            </x-metronic.card>
        </div>
        <div class="col-md-3">
            <x-metronic.card class="h-100">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-60px symbol-circle bg-light-primary text-primary me-4">
                        <i class="ki-outline ki-flag fs-2x"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted fs-7 mb-1">Top Penyebab</div>
                        <div class="fw-bold fs-5 text-primary">{{ $topReason?->loss_type ?? '-' }}</div>
                        <div class="text-muted fs-7">
                            @if($topReason)
                                Rp {{ number_format((float) $topReason->total_value, 0, ',', '.') }}
                            @endif
                        </div>
                    </div>
                </div>
            </x-metronic.card>
        </div>
    </div>

    {{-- Breakdown by Type --}}
    <div class="row g-6 mb-6">
        <div class="col-lg-5">
            <x-metronic.card title="Breakdown per Jenis Loss">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7">
                        <thead>
                            <tr class="text-muted fw-bold">
                                <th>Jenis Loss</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Nilai</th>
                                <th class="text-end">% dari Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($byReason as $row)
                                @php
                                    $percent = $totalLossValue > 0 ? ($row->total_value / $totalLossValue) * 100 : 0;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $row->loss_type }}</td>
                                    <td class="text-end">{{ qty($row->total_qty) }}</td>
                                    <td class="text-end fw-bold">Rp {{ number_format((float) $row->total_value, 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <span class="text-muted">{{ number_format($percent, 1) }}%</span>
                                            <div class="progress" style="width: 60px; height: 6px;">
                                                <div class="progress-bar {{ $percent >= 50 ? 'bg-danger' : ($percent >= 25 ? 'bg-warning' : 'bg-success') }}" style="width: {{ min(100, $percent) }}%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($byReason->isEmpty())
                    <x-metronic.empty-state title="Belum ada data loss" description="Loss akan tercatat saat ada pelaporan kerugian inventori." icon="ki-outline ki-chart-line" />
                @endif
            </x-metronic.card>
        </div>

        <div class="col-lg-7">
            <x-metronic.card title="Visualisasi Loss">
                <div class="d-flex flex-column gap-3">
                    @foreach($byReason as $index => $row)
                        @php
                            $percent = $totalLossValue > 0 ? ($row->total_value / $totalLossValue) * 100 : 0;
                            $colors = ['danger', 'warning', 'primary', 'success', 'info'];
                            $color = $colors[$index % count($colors)];
                        @endphp
                        <div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-semibold">{{ $row->loss_type }}</span>
                                <span class="text-muted">Rp {{ number_format((float) $row->total_value, 0, ',', '.') }} ({{ number_format($percent, 1) }}%)</span>
                            </div>
                            <div class="progress" style="height: 12px;">
                                <div class="progress-bar bg-{{ $color }}" role="progressbar" style="width: {{ min(100, $percent) }}%;">{{ qty($row->total_qty) }} unit</div>
                            </div>
                        </div>
                    @endforeach
                    @if($byReason->isEmpty())
                        <div class="text-center py-5">
                            <i class="ki-outline ki-chart-line fs-1x text-muted mb-3"></i>
                            <div class="text-muted">Belum ada data loss untuk ditampilkan</div>
                        </div>
                    @endif
                </div>
            </x-metronic.card>
        </div>
    </div>

    {{-- Detail Table --}}
    <x-metronic.card title="Detail Loss Transactions">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle fs-7">
                <thead>
                    <tr class="text-muted fw-bold">
                        <th class="min-w-120px">Nomor</th>
                        <th class="min-w-150px">Produk</th>
                        <th class="min-w-120px">Lokasi</th>
                        <th class="min-w-100px">Jenis</th>
                        <th class="min-w-100px text-end">Qty</th>
                        <th class="min-w-100px text-end">Nilai</th>
                        <th class="min-w-120px">Tanggal</th>
                        <th class="min-w-100px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($losses as $loss)
                        <tr>
                            <td><span class="fw-semibold font-monospace">{{ $loss->number }}</span></td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-900">{{ $loss->product?->name ?? '-' }}</span>
                                    <span class="text-muted font-monospace">{{ $loss->product?->sku ?? '-' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold">{{ $loss->workLocation?->name ?? '-' }}</span>
                                    @if($loss->warehouseLocation)
                                        <span class="text-muted fs-7">{{ $loss->warehouseLocation->full_code }}</span>
                                    @endif
                                </div>
                            </td>
                            <td><span class="badge badge-light-dark">{{ $loss->loss_type ?? '-' }}</span></td>
                            <td class="text-end fw-bold">{{ qty($loss->quantity) }}</td>
                            <td class="text-end"><span class="fw-bold text-danger">Rp {{ number_format((float) $loss->loss_value, 0, ',', '.') }}</span></td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="text-gray-900">{{ $loss->reported_at?->format('d M Y') ?? '-' }}</span>
                                    <span class="text-muted fs-7">{{ $loss->reported_at?->format('H:i') ?? '-' }}</span>
                                </div>
                            </td>
                            <td><x-metronic.status-badge :status="$loss->status?->value ?? 'draft'" :label="$loss->status?->label() ?? 'Draft'" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-10"><x-metronic.empty-state title="Belum ada data loss" description="Loss akan tercatat saat ada pelaporan kerugian inventori." icon="ki-outline ki-exclamation-triangle" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-4">{{ $losses->links() }}</div>
    </x-metronic.card>

    <script>
        function exportLosses() {
            const url = new URL('{{ route('reports.losses.index') }}');
            @if($filters['date_from'] ?? false)
                url.searchParams.set('date_from', '{{ $filters['date_from'] }}');
            @endif
            @if($filters['date_to'] ?? false)
                url.searchParams.set('date_to', '{{ $filters['date_to'] }}');
            @endif
            window.open(url.toString() + '&export=1', '_blank');
        }
    </script>
@endsection
