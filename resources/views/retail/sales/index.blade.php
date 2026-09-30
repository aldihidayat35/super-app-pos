@extends('layouts.metronic.app')

@section('title', 'Riwayat Transaksi POS')

@section('content')
<div class="row g-5 g-xl-10">
    <div class="col-12">
        <x-metronic.card>
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <form method="GET" action="{{ route('retail.sales.index') }}" class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center position-relative my-1">
                            <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-solid w-250px ps-12" placeholder="Cari No Nota / Pelanggan..." />
                        </div>
                        
                        <div class="d-flex align-items-center position-relative my-1">
                            <input class="form-control form-control-solid w-200px" placeholder="Pilih Rentang Tanggal" id="kt_daterangepicker" />
                            <input type="hidden" name="start_date" id="start_date" value="{{ request('start_date') }}">
                            <input type="hidden" name="end_date" id="end_date" value="{{ request('end_date') }}">
                        </div>

                        <select name="status" class="form-select form-select-solid w-150px" data-control="select2" data-hide-search="true" data-placeholder="Semua Status">
                            <option value=""></option>
                            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Selesai</option>
                            <option value="held" {{ request('status') === 'held' ? 'selected' : '' }}>Ditahan</option>
                            <option value="void_approved" {{ request('status') === 'void_approved' ? 'selected' : '' }}>Dibatalkan</option>
                        </select>

                        @if(auth()->user()->can('pos.void') || auth()->user()->hasRole('super_admin'))
                        <select name="cashier_id" class="form-select form-select-solid w-200px" data-control="select2" data-placeholder="Semua Kasir">
                            <option value=""></option>
                            @foreach($cashiers as $c)
                                <option value="{{ $c->id }}" {{ request('cashier_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @endif

                        <button type="submit" class="btn btn-primary">Filter</button>
                        @if(request()->anyFilled(['search', 'start_date', 'status', 'cashier_id']))
                            <a href="{{ route('retail.sales.index') }}" class="btn btn-light-danger">Reset</a>
                        @endif
                    </form>
                </div>
                <div class="card-toolbar">
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
                                <th class="min-w-150px">Kasir</th>
                                <th class="min-w-125px">Pelanggan</th>
                                <th class="min-w-100px text-center">Item Terjual</th>
                                <th class="min-w-125px text-end">Total</th>
                                <th class="min-w-100px text-center">Status</th>
                                <th class="min-w-100px text-center">Stok</th>
                                <th class="text-end min-w-100px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            @forelse($sales as $sale)
                                <tr>
                                    <td>
                                        <div class="text-gray-800 text-hover-primary fw-bold">{{ $sale->number }}</div>
                                        <div class="text-muted fs-7">{{ $sale->created_at->format('d/m/Y H:i') }}</div>
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
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $sale->customer?->name ?? 'Pelanggan Umum' }}</td>
                                    <td class="text-center">{{ qty($sale->items->sum('qty')) }}</td>
                                    <td class="text-end fw-bold text-gray-800">{{ \App\Support\CurrencyFormatter::rupiah($sale->grand_total_amount) }}</td>
                                    <td class="text-center">
                                        <x-metronic.status-badge :status="$sale->status" />
                                    </td>
                                    <td class="text-center">
                                        @if($sale->status->value === 'completed')
                                            <span class="badge badge-light-success">Terpotong</span>
                                        @elseif($sale->status->value === 'void_approved')
                                            <span class="badge badge-light-danger">Dikembalikan</span>
                                        @else
                                            <span class="badge badge-light-secondary">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('retail.sales.show', $sale) }}" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1">
                                            <i class="ki-outline ki-eye fs-3"></i>
                                        </a>
                                        @if($sale->status->value === 'completed')
                                            <a href="{{ route('retail.sales.print', $sale) }}" target="_blank" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" title="Cetak Struk">
                                                <i class="ki-outline ki-printer fs-3"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Belum ada transaksi ditemukan.</td>
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
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        var start = moment().subtract(29, 'days');
        var end = moment();
        var startInput = $('#start_date');
        var endInput = $('#end_date');

        if (startInput.val() && endInput.val()) {
            start = moment(startInput.val());
            end = moment(endInput.val());
        }

        function cb(start, end) {
            $('#kt_daterangepicker').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
            startInput.val(start.format('YYYY-MM-DD'));
            endInput.val(end.format('YYYY-MM-DD'));
        }

        $('#kt_daterangepicker').daterangepicker({
            startDate: start,
            endDate: end,
            ranges: {
               'Hari Ini': [moment(), moment()],
               'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
               '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
               'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
               'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, cb);

        if (!startInput.val() && !endInput.val()) {
            $('#kt_daterangepicker').val('');
        } else {
            cb(start, end);
        }
        
        $('#kt_daterangepicker').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            startInput.val('');
            endInput.val('');
        });
    });
</script>
@endpush
