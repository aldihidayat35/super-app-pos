@extends('layouts.metronic.app')

@section('title', 'Terima Transfer - '.config('app.name'))
@section('page_title', 'Penerimaan Transfer')

@section('toolbar_actions')
    <a href="{{ route('retail.stock-transfers.receiving') }}" class="btn btn-light">
        <i class="ki-outline ki-arrow-left fs-5 me-1"></i>Daftar Penerimaan
    </a>
@endsection

@section('content')
    <x-metronic.card title="Transfer {{ $transfer->number }}" class="mb-6">
        <div class="row g-4">
            <div class="col-md-2">
                <div class="text-muted fs-8">STATUS</div>
                <x-metronic.status-badge :status="$transfer->status" />
            </div>
            <div class="col-md-2">
                <div class="text-muted fs-8">SUMBER</div>
                <strong>{{ $transfer->sourceWorkLocation?->name }}</strong>
            </div>
            <div class="col-md-2">
                <div class="text-muted fs-8">TUJUAN</div>
                <strong>{{ $transfer->destinationWorkLocation?->name }}</strong>
            </div>
            <div class="col-md-2">
                <div class="text-muted fs-8">TANGGAL KIRIM</div>
                <strong>{{ $transfer->shipped_at?->format('d/m/Y H:i') ?: '-' }}</strong>
            </div>
            <div class="col-md-2">
                <div class="text-muted fs-8">PENGIRIM</div>
                <strong>{{ $transfer->shipper?->name ?: '-' }}</strong>
            </div>
            <div class="col-md-2">
                <div class="text-muted fs-8">NOMOR</div>
                <strong>{{ $transfer->number }}</strong>
            </div>
        </div>
    </x-metronic.card>

    <x-metronic.card title="Item yang Diterima">
        <x-slot:toolbar>
            <button type="button" class="btn btn-light-success btn-sm fw-bold btn-receive-all-action" id="btn-receive-all">
                <i class="ki-outline ki-check-circle fs-4 me-1"></i>1-Klik: Terima Semua Sesuai Pengiriman
            </button>
        </x-slot:toolbar>

        <!-- Dynamic Summary Card & Status Alerts -->
        <div class="card border border-dashed border-gray-300 bg-light-primary p-4 mb-6 rounded">
            <div class="row g-3 text-center text-md-start">
                <div class="col-6 col-md-3">
                    <div class="text-muted fs-8 fw-bolder text-uppercase">Total Dikirim</div>
                    <div class="fs-2 fw-bolder text-gray-800" id="summary-total-in-transit">0</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted fs-8 fw-bolder text-uppercase">Total Diterima</div>
                    <div class="fs-2 fw-bolder text-success" id="summary-total-received">0</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted fs-8 fw-bolder text-uppercase">Total Rusak</div>
                    <div class="fs-2 fw-bolder text-warning" id="summary-total-damaged">0</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted fs-8 fw-bolder text-uppercase">Total Selisih</div>
                    <div class="fs-2 fw-bolder text-danger" id="summary-total-discrepancy">0</div>
                </div>
            </div>
            <div class="mt-4 pt-3 border-top border-gray-200" id="summary-status-alert">
                <div class="alert alert-light-success d-flex align-items-center p-3 mb-0" id="alert-state-green">
                    <i class="ki-outline ki-check-circle text-success fs-2 me-3"></i>
                    <div>
                        <strong class="text-success d-block">Penerimaan Sempurna</strong>
                        <span class="text-gray-700 fs-7">Semua barang tiba sesuai pengiriman dalam kondisi sempurna</span>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('retail.stock-transfers.receive', $transfer) }}" enctype="multipart/form-data" id="form-stock-transfer-receive">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) str()->uuid() }}">

            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <x-metronic.form-group name="received_at" label="Tanggal Terima">
                        <input type="datetime-local" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}" class="form-control form-control-solid">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="proof" label="Foto/Bukti">
                        <input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    </x-metronic.form-group>
                </div>
                <div class="col-md-4">
                    <x-metronic.form-group name="notes" label="Catatan Penerimaan">
                        <input name="notes" value="{{ old('notes') }}" class="form-control form-control-solid" placeholder="Catatan tambahan (opsional)">
                    </x-metronic.form-group>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 p-3 bg-light rounded border border-gray-200">
                <div class="d-flex align-items-center">
                    <i class="ki-outline ki-information-4 text-primary fs-3 me-2"></i>
                    <span class="fs-7 text-gray-700">Periksa kuantitas fisik. Tombol 1-klik akan mengisi kuantitas terima sesuai sisa pengiriman.</span>
                </div>
                <button type="button" class="btn btn-success btn-sm fw-bold btn-receive-all-action" id="btn-receive-all-inline">
                    <i class="ki-outline ki-check-circle fs-4 me-1"></i>1-Klik: Terima Semua Sesuai Pengiriman
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-row-dashed align-middle gs-3 gy-4" id="table-receive-items">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7 bg-light">
                            <th class="ps-3 min-w-200px">Produk</th>
                            <th class="text-end min-w-90px">Qty Dikirim</th>
                            <th class="text-end min-w-90px">Sudah Diterima</th>
                            <th class="text-end min-w-90px">Sisa Kirim</th>
                            <th class="min-w-130px">Qty Diterima Sekarang</th>
                            <th class="min-w-110px">Qty Rusak</th>
                            <th class="min-w-110px">Selisih</th>
                            <th class="min-w-150px pe-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($transfer->items as $item)
                        @php
                            $inTransit = (string) $item->inTransitQuantity();
                        @endphp
                        <tr class="stock-transfer-item-row" data-item-id="{{ $item->id }}" data-in-transit="{{ $inTransit }}">
                            <td class="ps-3">
                                <strong>{{ $item->product_sku_snapshot }}</strong>
                                <div class="text-muted fs-8">{{ $item->product_name_snapshot }}</div>
                            </td>
                            <td class="text-end">{{ qty($item->quantity_shipped) }}</td>
                            <td class="text-end text-muted">{{ qty(\App\Support\Decimal::add((string) $item->quantity_received, \App\Support\Decimal::add((string) $item->quantity_damaged, (string) $item->quantity_discrepancy))) }}</td>
                            <td class="text-end fw-bold text-primary">{{ qty($item->inTransitQuantity()) }}</td>
                            <td>
                                <input name="items[{{ $item->id }}][quantity_received]"
                                       type="number"
                                       min="0"
                                       step="any"
                                       value="{{ old("items.{$item->id}.quantity_received", qty_input($item->inTransitQuantity())) }}"
                                       class="form-control form-control-sm receive-qty-input quantity-received"
                                       required>
                                <div class="text-danger fs-8 fw-bold mt-1 d-none row-exceeded-msg">
                                    <i class="ki-outline ki-information-2 text-danger fs-7 me-1"></i>Melebihi sisa kirim ({{ qty($item->inTransitQuantity()) }})
                                </div>
                            </td>
                            <td>
                                <input name="items[{{ $item->id }}][quantity_damaged]"
                                       type="number"
                                       min="0"
                                       step="any"
                                       value="{{ old("items.{$item->id}.quantity_damaged", 0) }}"
                                       class="form-control form-control-sm receive-qty-input quantity-damaged">
                            </td>
                            <td>
                                <input name="items[{{ $item->id }}][quantity_discrepancy]"
                                       type="number"
                                       min="0"
                                       step="any"
                                       value="{{ old("items.{$item->id}.quantity_discrepancy", 0) }}"
                                       class="form-control form-control-sm receive-qty-input quantity-discrepancy">
                            </td>
                            <td class="pe-3">
                                <input name="items[{{ $item->id }}][notes]"
                                       value="{{ old("items.{$item->id}.notes") }}"
                                       class="form-control form-control-sm"
                                       placeholder="Catatan item (opsional)">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="alert alert-light-warning fs-7 mt-4">
                Qty baik, rusak, dan selisih tidak boleh melebihi sisa barang dalam perjalanan. Penerimaan sebagian tetap dapat dilanjutkan pada penerimaan berikutnya.
            </div>

            <div class="d-flex justify-content-end gap-3 mt-4">
                <a href="{{ route('retail.stock-transfers.receiving') }}" class="btn btn-light">Batal</a>
                <button type="submit" class="btn btn-primary" id="btn-submit-receive" data-confirm="Simpan penerimaan transfer ini?">
                    <i class="ki-outline ki-check fs-4 me-1"></i>Konfirmasi Terima
                </button>
            </div>
        </form>
    </x-metronic.card>
@endsection

@push('scripts')
<script>
$(function () {
    const EPSILON = 0.00001;

    function parseNum(val) {
        const n = parseFloat(val);
        return isNaN(n) ? 0 : n;
    }

    function formatNumber(val) {
        if (isNaN(val)) return '0';
        return parseFloat(val.toFixed(4)).toString();
    }

    function updateRowValidation(row) {
        const inTransit = parseNum(row.getAttribute('data-in-transit'));
        const received = parseNum(row.querySelector('.quantity-received')?.value);
        const damaged = parseNum(row.querySelector('.quantity-damaged')?.value);
        const discrepancy = parseNum(row.querySelector('.quantity-discrepancy')?.value);
        const sum = received + damaged + discrepancy;
        const diff = sum - inTransit;

        const isExceeded = diff > EPSILON;
        const msgEl = row.querySelector('.row-exceeded-msg');
        const receivedInput = row.querySelector('.quantity-received');

        if (isExceeded) {
            row.classList.add('table-danger');
            row.style.outline = '2px solid var(--bs-danger, #f1416c)';
            receivedInput?.classList.add('is-invalid');
            if (msgEl) msgEl.classList.remove('d-none');
        } else {
            row.classList.remove('table-danger');
            row.style.outline = '';
            receivedInput?.classList.remove('is-invalid');
            if (msgEl) msgEl.classList.add('d-none');
        }

        return { inTransit, received, damaged, discrepancy, isExceeded };
    }

    function updateSummary() {
        let totalInTransit = 0;
        let totalReceived = 0;
        let totalDamaged = 0;
        let totalDiscrepancy = 0;
        let hasExceeded = false;
        let allPerfect = true;

        const rows = document.querySelectorAll('.stock-transfer-item-row');
        rows.forEach(function (row) {
            const data = updateRowValidation(row);
            totalInTransit += data.inTransit;
            totalReceived += data.received;
            totalDamaged += data.damaged;
            totalDiscrepancy += data.discrepancy;

            if (data.isExceeded) {
                hasExceeded = true;
            }

            // Perfect condition: received == inTransit, damaged == 0, discrepancy == 0
            const diffPerfect = Math.abs(data.received - data.inTransit);
            if (diffPerfect > EPSILON || data.damaged > EPSILON || data.discrepancy > EPSILON) {
                allPerfect = false;
            }
        });

        // Update summary values
        $('#summary-total-in-transit').text(formatNumber(totalInTransit));
        $('#summary-total-received').text(formatNumber(totalReceived));
        $('#summary-total-damaged').text(formatNumber(totalDamaged));
        $('#summary-total-discrepancy').text(formatNumber(totalDiscrepancy));

        // Update alert indicators and submit button
        const alertContainer = $('#summary-status-alert');
        const submitBtn = $('#btn-submit-receive');

        if (hasExceeded) {
            alertContainer.html(`
                <div class="alert alert-light-danger d-flex align-items-center p-3 mb-0">
                    <i class="ki-outline ki-cross-circle text-danger fs-2 me-3"></i>
                    <div>
                        <strong class="text-danger d-block">Kuantitas Tidak Valid</strong>
                        <span class="text-gray-700 fs-7">Kuantitas melebihi sisa pengiriman</span>
                    </div>
                </div>
            `);
            submitBtn.prop('disabled', true);
        } else if (allPerfect && rows.length > 0) {
            alertContainer.html(`
                <div class="alert alert-light-success d-flex align-items-center p-3 mb-0">
                    <i class="ki-outline ki-check-circle text-success fs-2 me-3"></i>
                    <div>
                        <strong class="text-success d-block">Penerimaan Sempurna</strong>
                        <span class="text-gray-700 fs-7">Semua barang tiba sesuai pengiriman dalam kondisi sempurna</span>
                    </div>
                </div>
            `);
            submitBtn.prop('disabled', false);
        } else {
            alertContainer.html(`
                <div class="alert alert-light-warning d-flex align-items-center p-3 mb-0">
                    <i class="ki-outline ki-information text-warning fs-2 me-3"></i>
                    <div>
                        <strong class="text-warning d-block">Catatan Pengiriman</strong>
                        <span class="text-gray-700 fs-7">Terdapat catatan kerusakan atau selisih pengiriman</span>
                    </div>
                </div>
            `);
            submitBtn.prop('disabled', false);
        }
    }

    // 1-Click: Receive All Shipment
    $(document).on('click', '.btn-receive-all-action', function (e) {
        e.preventDefault();
        document.querySelectorAll('.stock-transfer-item-row').forEach(function (row) {
            const inTransit = parseNum(row.getAttribute('data-in-transit'));
            const receivedInput = row.querySelector('.quantity-received');
            const damagedInput = row.querySelector('.quantity-damaged');
            const discrepancyInput = row.querySelector('.quantity-discrepancy');

            if (receivedInput) receivedInput.value = formatNumber(inTransit);
            if (damagedInput) damagedInput.value = '0';
            if (discrepancyInput) discrepancyInput.value = '0';
        });
        updateSummary();
    });

    // Automatic calculation when quantity_damaged or quantity_discrepancy changes
    $(document).on('input change', '.quantity-damaged', function () {
        const row = this.closest('.stock-transfer-item-row');
        if (!row) return;

        const inTransit = parseNum(row.getAttribute('data-in-transit'));
        const damaged = parseNum(this.value);
        const discrepancy = parseNum(row.querySelector('.quantity-discrepancy')?.value);

        // quantity_received = Math.max(0, inTransit - X - discrepancy)
        const newReceived = Math.max(0, inTransit - damaged - discrepancy);
        const receivedInput = row.querySelector('.quantity-received');
        if (receivedInput) {
            receivedInput.value = formatNumber(newReceived);
        }

        updateSummary();
    });

    $(document).on('input change', '.quantity-discrepancy', function () {
        const row = this.closest('.stock-transfer-item-row');
        if (!row) return;

        const inTransit = parseNum(row.getAttribute('data-in-transit'));
        const discrepancy = parseNum(this.value);
        const damaged = parseNum(row.querySelector('.quantity-damaged')?.value);

        // quantity_received = Math.max(0, inTransit - damaged - Y)
        const newReceived = Math.max(0, inTransit - damaged - discrepancy);
        const receivedInput = row.querySelector('.quantity-received');
        if (receivedInput) {
            receivedInput.value = formatNumber(newReceived);
        }

        updateSummary();
    });

    // When quantity_received is manually changed
    $(document).on('input change', '.quantity-received', function () {
        updateSummary();
    });

    // Initial calculation on load
    updateSummary();
});
</script>
@endpush
