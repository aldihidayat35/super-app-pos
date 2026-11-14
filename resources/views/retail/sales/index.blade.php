@extends('layouts.metronic.app')

@section('title', 'Riwayat Transaksi POS')

@section('content')
@php
    use App\Support\Decimal;
@endphp

<!-- KPI Ringkasan Dinamis -->
<div class="row g-5 g-xl-8 mb-5 mb-xl-8">
    <div class="col-sm-6 col-xl-3">
        <div class="card bg-light-success card-xl-stretch mb-xl-8 border border-success border-opacity-20 shadow-xs">
            <div class="card-body py-4 px-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fs-6 fw-bold text-gray-700">Total Omzet</span>
                    <span class="badge badge-success p-2">
                        <i class="ki-outline ki-dollar fs-3 text-white"></i>
                    </span>
                </div>
                <div class="fs-2x fw-bolder text-success mb-1">
                    {{ \App\Support\CurrencyFormatter::rupiah($kpi['revenue']) }}
                </div>
                <div class="text-muted fw-semibold fs-8">
                    Transaksi Selesai Sesuai Filter
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card bg-light-primary card-xl-stretch mb-xl-8 border border-primary border-opacity-20 shadow-xs">
            <div class="card-body py-4 px-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fs-6 fw-bold text-gray-700">Transaksi Selesai</span>
                    <span class="badge badge-primary p-2">
                        <i class="ki-outline ki-basket fs-3 text-white"></i>
                    </span>
                </div>
                <div class="fs-2x fw-bolder text-primary mb-1">
                    {{ number_format((int) $kpi['transactions'], 0, ',', '.') }}
                </div>
                <div class="text-muted fw-semibold fs-8">
                    Nota Berhasil
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card bg-light-info card-xl-stretch mb-xl-8 border border-info border-opacity-20 shadow-xs">
            <div class="card-body py-4 px-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fs-6 fw-bold text-gray-700">Item Terjual</span>
                    <span class="badge badge-info p-2">
                        <i class="ki-outline ki-cube-2 fs-3 text-white"></i>
                    </span>
                </div>
                <div class="fs-2x fw-bolder text-info mb-1">
                    {{ qty($kpi['items_sold']) }}
                </div>
                <div class="text-muted fw-semibold fs-8">
                    Total Qty Produk Terjual
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card bg-light-danger card-xl-stretch mb-xl-8 border border-danger border-opacity-20 shadow-xs">
            <div class="card-body py-4 px-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fs-6 fw-bold text-gray-700">Void & Retur</span>
                    <span class="badge badge-danger p-2">
                        <i class="ki-outline ki-cross-circle fs-3 text-white"></i>
                    </span>
                </div>
                <div class="fs-2x fw-bolder text-danger mb-1">
                    {{ number_format((int) $kpi['void_retur_count'], 0, ',', '.') }}
                </div>
                <div class="text-danger fw-semibold fs-8">
                    {{ \App\Support\CurrencyFormatter::rupiah($kpi['void_retur_amount']) }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-5 g-xl-10">
    <div class="col-12">
        <x-metronic.card>
            <div class="card-header border-0 pt-6">
                <div class="card-title w-100 mb-3">
                    <form method="GET" action="{{ route('retail.sales.index') }}" class="d-flex flex-wrap align-items-center gap-2 w-100">
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4 text-gray-500"></i>
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-solid w-250px ps-12" placeholder="Cari No Nota / Pelanggan..." />
                        </div>
                        
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-outline ki-calendar fs-3 position-absolute ms-4 text-gray-500"></i>
                            <input class="form-control form-control-solid w-225px ps-12" placeholder="Pilih Rentang Tanggal" id="kt_daterangepicker" readonly />
                            <input type="hidden" name="start_date" id="start_date" value="{{ $startDate }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ $endDate }}">
                        </div>

                        <select name="status" class="form-select form-select-solid w-150px" data-control="select2" data-hide-search="true" data-placeholder="Semua Status">
                            <option value="">Semua Status</option>
                            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Selesai</option>
                            <option value="returned" {{ request('status') === 'returned' ? 'selected' : '' }}>Diretur</option>
                            <option value="void_approved" {{ request('status') === 'void_approved' ? 'selected' : '' }}>Dibatalkan</option>
                            <option value="void_pending" {{ request('status') === 'void_pending' ? 'selected' : '' }}>Menunggu Batal</option>
                        </select>

                        <select name="payment_method" class="form-select form-select-solid w-175px" data-control="select2" data-hide-search="true" data-placeholder="Semua Metode Bayar">
                            <option value="">Semua Metode Bayar</option>
                            @foreach($paymentMethods as $val => $label)
                                <option value="{{ $val }}" {{ request('payment_method') === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>

                        @if(auth()->user()->can('pos.void') || auth()->user()->hasRole('super_admin'))
                        <select name="cashier_id" class="form-select form-select-solid w-200px" data-control="select2" data-placeholder="Semua Kasir">
                            <option value="">Semua Kasir</option>
                            @foreach($cashiers as $c)
                                <option value="{{ $c->id }}" {{ request('cashier_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @endif

                        <button type="submit" class="btn btn-primary">Filter</button>
                        @if(request()->anyFilled(['search', 'status', 'cashier_id', 'payment_method']) || request('start_date') !== now()->toDateString() || request('end_date') !== now()->toDateString())
                            <a href="{{ route('retail.sales.index') }}" class="btn btn-light-danger" title="Kembali ke default transaksi hari ini">Reset</a>
                        @endif
                        <a href="{{ route('retail.sales.print-history', request()->query()) }}" target="_blank" class="btn btn-light-success" title="Cetak Riwayat Transaksi sesuai filter yang aktif">
                            <i class="ki-outline ki-printer fs-4 me-1"></i> Cetak Filter Ini
                        </a>
                    </form>
                </div>
                <div class="card-toolbar d-flex justify-content-end gap-2 w-100">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-print-history">
                        <i class="ki-outline ki-printer fs-2"></i> Cetak Transaksi (Dot Matrix)
                    </button>
                    @can('pos.create')
                    <a href="{{ route('retail.pos.index') }}" class="btn btn-primary">
                        <i class="ki-outline ki-plus fs-2"></i> Kasir POS Baru
                    </a>
                    @endcan
                </div>
            </div>

            <div class="card-body py-4">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-125px">No Nota / Waktu</th>
                                <th class="min-w-150px">Kasir & Lokasi</th>
                                <th class="min-w-140px">Pelanggan</th>
                                <th class="min-w-100px text-center">Item Terjual</th>
                                <th class="min-w-130px text-center">Pembayaran</th>
                                <th class="min-w-130px text-end">Total</th>
                                <th class="min-w-100px text-center">Status</th>
                                <th class="min-w-100px text-center">Stok</th>
                                <th class="text-end min-w-100px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            @forelse($sales as $sale)
                                <tr>
                                    <td>
                                        <a href="{{ route('retail.sales.show', $sale) }}" class="text-gray-800 text-hover-primary fw-bold fs-6">
                                            {{ $sale->number }}
                                        </a>
                                        <div class="text-muted fs-7">
                                            <i class="ki-outline ki-time fs-8 text-gray-500"></i>
                                            {{ ($sale->completed_at ?? $sale->created_at)->format('d/m/Y H:i') }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-35px symbol-circle me-3">
                                                @if($sale->cashier?->avatar_url)
                                                    <img src="{{ $sale->cashier->avatar_url }}" alt="{{ $sale->cashier->name }}">
                                                @else
                                                    <div class="symbol-label bg-light-primary text-primary fs-6 fw-bolder">
                                                        {{ substr($sale->cashier?->name ?? '?', 0, 1) }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800 fw-bold">{{ $sale->cashier?->name ?? 'Sistem' }}</span>
                                                <span class="text-muted fs-8">{{ $sale->branch?->name }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($sale->customer)
                                            <div class="fw-bold text-gray-800">{{ $sale->customer->business_name }}</div>
                                            <div class="text-muted fs-8">
                                                {{ $sale->customer->code }}
                                                @if($sale->customer->type)
                                                    · <span class="badge badge-light-secondary fs-9 py-0 px-1">{{ $sale->customer->type->label() }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-600">Pelanggan Umum</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold fs-6 text-gray-800">{{ qty($sale->items->sum('quantity')) }}</span>
                                        <div class="text-muted fs-8">{{ $sale->items->count() }} jenis produk</div>
                                    </td>
                                    <td class="text-center">
                                        @if($sale->payments->isEmpty())
                                            <span class="text-muted fs-7">-</span>
                                        @elseif($sale->payments->count() === 1)
                                            @php($p = $sale->payments->first())
                                            <span class="badge badge-light-primary fs-8">
                                                {{ $p->method?->label() ?? 'Bayar' }}
                                            </span>
                                        @else
                                            <span class="badge badge-light-info fs-8" title="{{ $sale->payments->map(fn($p) => ($p->method?->label() ?? '') . ': ' . \App\Support\CurrencyFormatter::rupiah($p->amount))->join(', ') }}">
                                                Split ({{ $sale->payments->count() }})
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bolder text-gray-800 fs-6">
                                            {{ \App\Support\CurrencyFormatter::rupiah($sale->grand_total_amount) }}
                                        </div>
                                        @if(Decimal::compare((string) $sale->discount_amount, '0', 2) > 0)
                                            <div class="text-danger fs-8 fw-semibold mt-1">
                                                Diskon: -{{ \App\Support\CurrencyFormatter::rupiah($sale->discount_amount) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <x-metronic.status-badge :status="$sale->status" />
                                    </td>
                                    <td class="text-center">
                                        @if($sale->status->value === 'completed')
                                            <span class="badge badge-light-success">
                                                <i class="ki-outline ki-check-circle text-success fs-7 me-1"></i>Terpotong
                                            </span>
                                        @elseif($sale->status->value === 'void_approved')
                                            <span class="badge badge-light-danger">
                                                <i class="ki-outline ki-cross-circle text-danger fs-7 me-1"></i>Dikembalikan
                                            </span>
                                        @elseif($sale->status->value === 'returned')
                                            <span class="badge badge-light-warning">
                                                <i class="ki-outline ki-arrows-loop text-warning fs-7 me-1"></i>Diretur
                                            </span>
                                        @else
                                            <span class="badge badge-light-secondary">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('retail.sales.show', $sale) }}" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" title="Lihat Detail Transaksi">
                                            <i class="ki-outline ki-eye fs-3"></i>
                                        </a>
                                        @if($sale->status->value === 'completed')
                                            <a href="{{ route('retail.sales.print', $sale) }}" target="_blank" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" title="Cetak Ulang Struk (Thermal 80mm)">
                                                <i class="ki-outline ki-receipt-square fs-3"></i>
                                            </a>
                                            <a href="{{ route('retail.sales.print', [$sale, 'format' => 'dotmatrix']) }}" target="_blank" class="btn btn-icon btn-bg-light btn-active-color-success btn-sm me-1" title="Cetak Faktur (Epson LX-310)">
                                                <i class="ki-outline ki-document fs-3"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-10">
                                        <x-metronic.empty-state 
                                            title="Belum ada transaksi ditemukan" 
                                            description="Tidak ada transaksi POS yang sesuai dengan filter atau rentang tanggal yang dipilih." 
                                            icon="ki-outline ki-basket" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $sales->links() }}
                </div>
            </div>
        </x-metronic.card>
    </div>
</div>

<!-- Modal Cetak Transaksi Riwayat -->
<div class="modal fade" id="modal-print-history" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-600px">
        <div class="modal-content">
            <form method="GET" action="{{ route('retail.sales.print-history') }}" target="_blank">
                <div class="modal-header">
                    <h2 class="fw-bold d-flex align-items-center gap-2">
                        <i class="ki-outline ki-printer text-success fs-1"></i>
                        Cetak Riwayat Transaksi (Dot Matrix)
                    </h2>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>

                <div class="modal-body py-lg-7 px-lg-9">
                    <div class="alert alert-dismissible bg-light-success border border-success border-dashed d-flex flex-column p-4 mb-6">
                        <div class="d-flex align-items-center">
                            <i class="ki-outline ki-information-5 fs-2hx text-success me-3"></i>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-gray-800">Format Printer Dot Matrix (Epson LX-310)</span>
                                <span class="text-muted fs-7">Rincian riwayat transaksi dan detail item barang yang terjual dengan format kompak, rapi, dan hemat kertas.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Preset Rentang Waktu Cepat -->
                    <div class="mb-5">
                        <label class="form-label fw-semibold fs-7 text-muted mb-2">Preset Rentang Waktu:</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-light-primary" id="btn-preset-today">Hari Ini</button>
                            <button type="button" class="btn btn-sm btn-light-primary" id="btn-preset-yesterday">Kemarin</button>
                            <button type="button" class="btn btn-sm btn-light-primary" id="btn-preset-7days">7 Hari Terakhir</button>
                            <button type="button" class="btn btn-sm btn-light-primary" id="btn-preset-month">Bulan Ini</button>
                        </div>
                    </div>

                    <!-- Input Rentang Waktu Detail -->
                    <div class="row g-4 mb-4">
                        <div class="col-sm-7">
                            <label class="form-label required fw-semibold">Tanggal Mulai:</label>
                            <input type="date" name="start_date" id="print_start_date" class="form-control form-control-solid" value="{{ $startDate ?: now()->toDateString() }}" required>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label required fw-semibold">Jam Mulai:</label>
                            <input type="time" name="start_time" id="print_start_time" class="form-control form-control-solid" value="00:00" required>
                        </div>
                    </div>

                    <div class="row g-4 mb-5">
                        <div class="col-sm-7">
                            <label class="form-label required fw-semibold">Tanggal Selesai:</label>
                            <input type="date" name="end_date" id="print_end_date" class="form-control form-control-solid" value="{{ $endDate ?: now()->toDateString() }}" required>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label required fw-semibold">Jam Selesai:</label>
                            <input type="time" name="end_time" id="print_end_time" class="form-control form-control-solid" value="23:59" required>
                        </div>
                    </div>

                    <div class="separator separator-dashed my-5"></div>

                    <!-- Filter Tambahan -->
                    <div class="row g-4">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Status Transaksi:</label>
                            <select name="status" class="form-select form-select-solid">
                                <option value="">Semua Status</option>
                                <option value="completed" selected>Selesai (Completed)</option>
                                <option value="returned">Diretur (Returned)</option>
                                <option value="void_approved">Dibatalkan (Void)</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Metode Pembayaran:</label>
                            <select name="payment_method" class="form-select form-select-solid">
                                <option value="">Semua Metode</option>
                                @foreach($paymentMethods as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if(auth()->user()->can('pos.void') || auth()->user()->hasRole('super_admin'))
                        <div class="col-12">
                            <label class="form-label fw-semibold">Kasir:</label>
                            <select name="cashier_id" class="form-select form-select-solid">
                                <option value="">Semua Kasir</option>
                                @foreach($cashiers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="ki-outline ki-printer fs-2 me-1"></i> Buka &amp; Cetak Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        var startInput = $('#start_date');
        var endInput = $('#end_date');
        var picker = $('#kt_daterangepicker');

        var startDate = startInput.val() ? moment(startInput.val()) : moment();
        var endDate = endInput.val() ? moment(endInput.val()) : moment();

        function updateRangeDisplay(start, end) {
            picker.val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
            startInput.val(start.format('YYYY-MM-DD'));
            endInput.val(end.format('YYYY-MM-DD'));
        }

        picker.daterangepicker({
            startDate: startDate,
            endDate: endDate,
            locale: {
                format: 'DD/MM/YYYY',
                cancelLabel: 'Bersihkan / Semua Tanggal'
            },
            ranges: {
               'Hari Ini': [moment(), moment()],
               'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
               '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
               'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
               'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end) {
            updateRangeDisplay(start, end);
        });

        if (startInput.val() && endInput.val()) {
            updateRangeDisplay(startDate, endDate);
        } else {
            picker.val('');
        }

        picker.on('cancel.daterangepicker', function(ev, pickerObj) {
            $(this).val('');
            startInput.val('');
            endInput.val('');
        });

        // Event handler Preset Rentang Waktu Modal Cetak
        $('#btn-preset-today').on('click', function() {
            var today = moment().format('YYYY-MM-DD');
            $('#print_start_date').val(today);
            $('#print_start_time').val('00:00');
            $('#print_end_date').val(today);
            $('#print_end_time').val('23:59');
        });

        $('#btn-preset-yesterday').on('click', function() {
            var yesterday = moment().subtract(1, 'days').format('YYYY-MM-DD');
            $('#print_start_date').val(yesterday);
            $('#print_start_time').val('00:00');
            $('#print_end_date').val(yesterday);
            $('#print_end_time').val('23:59');
        });

        $('#btn-preset-7days').on('click', function() {
            var start = moment().subtract(6, 'days').format('YYYY-MM-DD');
            var end = moment().format('YYYY-MM-DD');
            $('#print_start_date').val(start);
            $('#print_start_time').val('00:00');
            $('#print_end_date').val(end);
            $('#print_end_time').val('23:59');
        });

        $('#btn-preset-month').on('click', function() {
            var start = moment().startOf('month').format('YYYY-MM-DD');
            var end = moment().endOf('month').format('YYYY-MM-DD');
            $('#print_start_date').val(start);
            $('#print_start_time').val('00:00');
            $('#print_end_date').val(end);
            $('#print_end_time').val('23:59');
        });
    });
</script>
@endpush
