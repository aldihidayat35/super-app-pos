@extends('layouts.metronic.app')

@section('title', 'Shift Aktif - '.config('app.name'))
@section('page_title', 'Shift Aktif')

@push('styles')
<style>
    .shift-header-card { border-left: 4px solid var(--bs-primary); }
    .shift-stat-card { transition: transform 0.15s ease, box-shadow 0.15s ease; }
    .shift-stat-card:hover { transform: translateY(-2px); box-shadow: var(--bs-box-shadow-lg); }
    .shift-stat-card .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .section-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--bs-gray-500); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--bs-gray-200); }
    .table-shift th { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--bs-gray-500); border-bottom: 1px solid var(--bs-gray-200); padding: 0.75rem 1rem; }
    .table-shift td { padding: 0.85rem 1rem; vertical-align: middle; font-size: 0.875rem; }
    .table-shift tbody tr:hover { background-color: var(--bs-gray-100); }
    .payment-pill { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .payment-cash { background: #d1fae5; color: #065f46; }
    .payment-bank_transfer { background: #dbeafe; color: #1e40af; }
    .payment-qris { background: #ede9fe; color: #5b21b6; }
    .payment-manual { background: #fef3c7; color: #92400e; }
    .payment-credit { background: #fce7f3; color: #9d174d; }
    .expense-proof-thumb { width: 36px; height: 36px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 1px solid var(--bs-gray-200); }
    @media (max-width: 767px) {
        .shift-stat-card { margin-bottom: 0.75rem; }
        .table-shift { font-size: 0.8rem; }
        .table-shift th, .table-shift td { padding: 0.5rem 0.6rem; }
    }
</style>
@endpush

@section('toolbar_actions')
    @if($shift)
        <a href="{{ route('retail.pos.index') }}" class="btn btn-primary"><i class="ki-outline ki-shop fs-5 me-1"></i>Lanjut ke POS</a>
        <a href="{{ route('retail.shifts.export.pdf', $shift) }}" class="btn btn-light" target="_blank"><i class="ki-outline ki-printer fs-5 me-1"></i>Download PDF</a>
    @endif
@endsection

@section('content')
    @if(!$shift)
        <x-metronic.card>
            <x-metronic.empty-state title="Belum Ada Shift Aktif" description="Buka shift sebelum memulai transaksi POS." icon="ki-outline ki-time">
                <a href="{{ route('retail.shifts.open') }}" class="btn btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Buka Shift</a>
            </x-metronic.empty-state>
        </x-metronic.card>
    @else
        {{-- Header Card --}}
        <div class="card mb-6 shift-header-card">
            <div class="card-body p-5">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-4">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <span class="text-muted fs-7 fw-semibold text-uppercase ls-1">SHIFT AKTIF</span>
                            <x-metronic.status-badge :status="$shift->status" />
                        </div>
                        <h1 class="fw-bold mb-1">{{ $shift->number }}</h1>
                        <div class="text-muted fs-8">
                            Dimulai {{ $shift->opened_at?->format('d/m/Y H:i') }}
                            @if($shift->status->isLocked())
                                &bull; Selesai {{ $shift->closed_at?->format('d/m/Y H:i') ?? $shift->approved_at?->format('d/m/Y H:i') ?? $shift->rejected_at?->format('d/m/Y H:i') }}
                            @endif
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @can('close', $shift)
                            @if($shift->status->value === 'open' || $shift->status->value === 'rejected')
                                <a href="{{ route('retail.shifts.close', $shift) }}" class="btn btn-danger btn-sm"><i class="ki-outline ki-cross fs-5 me-1"></i>Tutup Shift</a>
                            @endif
                        @endcan
                        <a href="{{ route('retail.shifts.expenses', $shift) }}" class="btn btn-warning btn-sm"><i class="ki-outline ki-basket fs-5 me-1"></i>Pengeluaran</a>
                        <a href="{{ route('retail.pos.index') }}" class="btn btn-primary btn-sm"><i class="ki-outline ki-shop fs-5 me-1"></i>POS</a>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bg-primary-subtle text-primary"><i class="ki-outline ki-user fs-5"></i></div>
                            <div>
                                <div class="text-muted fs-8">Kasir</div>
                                <div class="fw-bold">{{ $shift->cashier?->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bg-success-subtle text-success"><i class="ki-outline ki-building fs-5"></i></div>
                            <div>
                                <div class="text-muted fs-8">Cabang</div>
                                <div class="fw-bold">{{ $shift->branch?->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bg-info-subtle text-info"><i class="ki-outline ki-coin fs-5"></i></div>
                            <div>
                                <div class="text-muted fs-8">Modal Awal</div>
                                <div class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($shift->opening_cash_amount) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bg-{{ $shift->attendance ? 'success' : 'warning' }}-subtle text-{{ $shift->attendance ? 'success' : 'warning' }}">
                                <i class="ki-outline ki-{{ $shift->attendance ? 'checkmark-circle' : 'alert' }} fs-5"></i>
                            </div>
                            <div>
                                <div class="text-muted fs-8">Kehadiran</div>
                                <div class="fw-bold">{{ $shift->attendance ? 'Terverifikasi' : 'Override' }}</div>
                                @if($shift->attendance_override_reason)
                                    <div class="text-muted fs-9">{{ $shift->attendance_override_reason }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary Stats --}}
        <div class="row g-4 mb-6">
            <div class="col-sm-6 col-xl-3">
                <div class="card shift-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fs-8 fw-semibold text-uppercase">Transaksi</span>
                            <div class="stat-icon bg-primary-subtle text-primary"><i class="ki-outline ki-basket fs-5"></i></div>
                        </div>
                        <div class="fs-2 fw-bold">{{ number_format((int) ($summary['sales_count'] ?? 0), 0, ',', '.') }}</div>
                        <div class="text-muted fs-9">penjualan selesai &amp; retur</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card shift-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fs-8 fw-semibold text-uppercase">Tunai</span>
                            <div class="stat-icon bg-success-subtle text-success"><i class="ki-outline ki-coin fs-5"></i></div>
                        </div>
                        <div class="fs-2 fw-bold text-success">{{ \App\Support\CurrencyFormatter::rupiah($summary['cash_sales'] ?? 0) }}</div>
                        <div class="text-muted fs-9">pembayaran tunai</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card shift-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fs-8 fw-semibold text-uppercase">Non Tunai</span>
                            <div class="stat-icon bg-info-subtle text-info"><i class="ki-outline ki-credit-card fs-5"></i></div>
                        </div>
                        <div class="fs-2 fw-bold text-info">{{ \App\Support\CurrencyFormatter::rupiah($summary['non_cash_sales'] ?? 0) }}</div>
                        <div class="text-muted fs-9">transfer, QRIS, manual</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card shift-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted fs-8 fw-semibold text-uppercase">Expected Cash</span>
                            <div class="stat-icon bg-warning-subtle text-warning"><i class="ki-outline ki-calculator fs-5"></i></div>
                        </div>
                        <div class="fs-2 fw-bold text-warning">{{ \App\Support\CurrencyFormatter::rupiah($summary['expected_cash'] ?? 0) }}</div>
                        <div class="text-muted fs-9">perkiraan kas fisik</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Breakdown Row --}}
        <div class="row g-4 mb-6">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header border-0 pt-4 pb-0">
                        <div class="section-title">Rincian Pembayaran</div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="ki-outline ki-coin me-2"></i>Penjualan Tunai</span>
                                <span class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($summary['cash_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="ki-outline ki-credit-card me-2"></i>Transfer Bank</span>
                                <span class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($summary['transfer_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="ki-outline ki-qr-code me-2"></i>QRIS</span>
                                <span class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($summary['qris_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="ki-outline ki-note-2 me-2"></i>Manual</span>
                                <span class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($summary['manual_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="ki-outline ki-wallet me-2"></i>Piutang</span>
                                <span class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($summary['receivable_sales'] ?? 0) }}</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="ki-outline ki-arrow-back me-2"></i>Refund Pelanggan</span>
                                <span class="fw-bold text-danger">-{{ \App\Support\CurrencyFormatter::rupiah($summary['refunds'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="ki-outline ki-box fs-5 me-2"></i>Refund Pemasok</span>
                                <span class="fw-bold text-danger">-{{ \App\Support\CurrencyFormatter::rupiah($summary['supplier_refunds'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="ki-outline ki-basket fs-5 me-2"></i>Pengeluaran Kas</span>
                                <span class="fw-bold text-danger">-{{ \App\Support\CurrencyFormatter::rupiah($summary['expenses'] ?? 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header border-0 pt-4 pb-0">
                        <div class="section-title">Rekonsiliasi Kas</div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Modal Awal</span>
                                <span class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($summary['opening_cash'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">+ Penjualan Tunai</span>
                                <span class="fw-bold text-success">{{ \App\Support\CurrencyFormatter::rupiah($summary['cash_sales'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">+ Refund Pemasok</span>
                                <span class="fw-bold text-success">{{ \App\Support\CurrencyFormatter::rupiah($summary['supplier_refunds'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">- Refund Pelanggan</span>
                                <span class="fw-bold text-danger">-{{ \App\Support\CurrencyFormatter::rupiah($summary['refunds'] ?? 0) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">- Pengeluaran Kas</span>
                                <span class="fw-bold text-danger">-{{ \App\Support\CurrencyFormatter::rupiah($summary['expenses'] ?? 0) }}</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold">Expected Cash</span>
                                <span class="fw-bold fs-5 text-warning">{{ \App\Support\CurrencyFormatter::rupiah($summary['expected_cash'] ?? 0) }}</span>
                            </div>
                            @if($shift->status->isLocked())
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Actual Cash</span>
                                    <span class="fw-bold fs-5">{{ \App\Support\CurrencyFormatter::rupiah($summary['actual_cash'] ?? 0) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold">Selisih</span>
                                    @php $diff = floatval($summary['difference'] ?? 0); @endphp
                                    <span class="fw-bold fs-5 {{ $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted') }}">
                                        {{ \App\Support\CurrencyFormatter::rupiah(abs($diff)) }}
                                        {{ $diff > 0 ? '(Lebih)' : ($diff < 0 ? '(Kurang)' : '') }}
                                    </span>
                                </div>
                                @if($shift->discrepancy_reason)
                                    <div class="alert alert-warning py-2 px-3 mt-2">
                                        <small><strong>Alasan Selisih:</strong> {{ $shift->discrepancy_reason }}</small>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Transactions Table --}}
        <div class="card mb-6">
            <div class="card-header border-0 pt-4 pb-0">
                <div class="section-title mb-0">Riwayat Transaksi POS</div>
                <div class="text-muted fs-9 mt-1">{{ count($shift->sales ?? []) }} transaksi</div>
            </div>
            <div class="card-body p-0">
                @if($shift->sales && $shift->sales->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7">
                            <th>No. Faktur</th>
                            <th>Waktu</th>
                            <th>Status</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Diskon</th>
                            <th class="text-end">Total</th>
                            <th>Metode Bayar</th>
                        </tr></thead>
                        <tbody>
                            @foreach($shift->sales as $sale)
                            <tr>
                                <td class="fw-bold">{{ $sale->number }}</td>
                                <td>{{ $sale->completed_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>
                                    @php
                                        $statusColor = match($sale->status?->value ?? '') {
                                            'completed' => 'success',
                                            'returned' => 'warning',
                                            'voided' => 'danger',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }}">{{ $sale->status?->label() ?? $sale->status?->value ?? 'Unknown' }}</span>
                                </td>
                                <td class="text-end">{{ \App\Support\CurrencyFormatter::rupiah($sale->subtotal_amount) }}</td>
                                <td class="text-end text-muted">{{ \App\Support\CurrencyFormatter::rupiah($sale->discount_amount) }}</td>
                                <td class="text-end fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($sale->grand_total_amount) }}</td>
                                <td>
                                    @foreach($sale->payments as $payment)
                                        <span class="payment-pill payment-{{ $payment->method?->value ?? 'cash' }} me-1">{{ $payment->method?->label() ?? $payment->method?->value ?? 'Cash' }}</span>
                                    @endforeach
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-6 text-muted">
                    <i class="ki-outline ki-basket fs-1 d-block mb-3 opacity-25"></i>
                    Belum ada transaksi pada shift ini.
                </div>
                @endif
            </div>
        </div>

        {{-- Expenses Table --}}
        <div class="card mb-6">
            <div class="card-header border-0 pt-4 pb-0">
                <div class="section-title mb-0">Pengeluaran Kecil</div>
                <div class="text-muted fs-9 mt-1">Total: {{ \App\Support\CurrencyFormatter::rupiah($summary['expenses'] ?? 0) }}</div>
            </div>
            <div class="card-body p-0">
                @if($shift->expenses && $shift->expenses->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7">
                            <th>Waktu</th>
                            <th>Kategori</th>
                            <th>Metode</th>
                            <th class="text-end">Nominal</th>
                            <th>Catatan</th>
                            <th>Bukti</th>
                            <th>Oleh</th>
                        </tr></thead>
                        <tbody>
                            @foreach($shift->expenses as $expense)
                            <tr>
                                <td>{{ $expense->spent_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td><span class="badge bg-light text-dark">{{ ucfirst($expense->category) }}</span></td>
                                <td><span class="payment-pill payment-{{ $expense->payment_method ?? 'cash' }}">{{ ucfirst(str_replace('_', ' ', $expense->payment_method ?? 'cash')) }}</span></td>
                                <td class="text-end fw-bold text-danger">-{{ \App\Support\CurrencyFormatter::rupiah($expense->amount) }}</td>
                                <td class="text-muted">{{ $expense->notes ?? '-' }}</td>
                                <td>
                                    @if($expense->proof_path)
                                        <a href="{{ Storage::url($expense->proof_path) }}" target="_blank">
                                            <img src="{{ Storage::url($expense->proof_path) }}" alt="Bukti" class="expense-proof-thumb">
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $expense->creator?->name ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-6 text-muted">
                    <i class="ki-outline ki-basket fs-1 d-block mb-3 opacity-25"></i>
                    Belum ada pengeluaran.
                </div>
                @endif
            </div>
        </div>

        {{-- Cash Count (if closing submitted or locked) --}}
        @if($shift->status->isLocked() && $shift->cashCounts && $shift->cashCounts->isNotEmpty())
        <div class="card mb-6">
            <div class="card-header border-0 pt-4 pb-0">
                <div class="section-title mb-0">Perhitungan Uang Tunai</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7">
                            <th>Denominasi</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Subtotal</th>
                        </tr></thead>
                        <tbody>
                            @foreach($shift->cashCounts as $cc)
                            <tr>
                                <td><strong>Rp {{ number_format($cc->denomination, 0, ',', '.') }}</strong></td>
                                <td class="text-end">{{ number_format($cc->quantity, 0, ',', '.') }} lembar</td>
                                <td class="text-end fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($cc->amount) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Approval Info --}}
        @if($shift->status->isLocked())
        <div class="card mb-6">
            <div class="card-header border-0 pt-4 pb-0">
                <div class="section-title mb-0">Informasi Penutupan</div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="text-muted fs-8 mb-1">Status</div>
                        <div class="fw-bold">{{ $shift->status->label() }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-8 mb-1">Ditutup Pada</div>
                        <div class="fw-bold">{{ $shift->closed_at?->format('d/m/Y H:i') ?? '-' }}</div>
                    </div>
                    @if($shift->approved_by)
                    <div class="col-md-6">
                        <div class="text-muted fs-8 mb-1">Disetujui Oleh</div>
                        <div class="fw-bold">{{ $shift->approvals->firstWhere('approved_by', $shift->approved_by)?->actor?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-8 mb-1">Catatan Approval</div>
                        <div>{{ $shift->approval_notes ?? '-' }}</div>
                    </div>
                    @endif
                    @if($shift->rejected_at)
                    <div class="col-md-6">
                        <div class="text-muted fs-8 mb-1">Ditolak Oleh</div>
                        <div class="fw-bold">{{ $shift->approvals->firstWhere('rejected_by', $shift->rejected_at)?->actor?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted fs-8 mb-1">Alasan Penolakan</div>
                        <div class="text-danger">{{ $shift->approval_notes ?? '-' }}</div>
                    </div>
                    @endif
                    @if($shift->handover_notes)
                    <div class="col-12">
                        <div class="text-muted fs-8 mb-1">Catatan Serah Terima</div>
                        <div>{{ $shift->handover_notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

    @endif
@endsection
