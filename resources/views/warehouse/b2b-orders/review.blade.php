@extends('layouts.metronic.app')

@section('title', 'Review Pesanan ' . $order->number)
@section('page_title', 'Review dan Reserve')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-b2b-orders-review" title="Panduan Halaman Review Pesanan B2B">
        <x-slot:function>
            <p>Halaman ini menampilkan detail pesanan B2B yang masuk dari pelanggan. Gudang melakukan validasi stok, limit kredit, payment preference, dan prioritas fulfillment sebelum melakukan reserve, reject, pack, atau ship.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Buka halaman review dari antrian pesanan B2B.</li><li>Periksa identitas pelanggan, alamat, dan ringkasan nilai.</li><li>Review item: stok tersedia vs qty diminta dan harga.</li><li>Pilih aksi: Reserve (stok dialokasikan), Reject (pesanan ditolak), Pack (siap kirim), atau Ship (kirim).</li><li>Setelah reserve, lanjut ke packing lalu shipping.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Identitas Pesanan:</strong> nomor, status, tanggal submit, dan approval.</li><li><strong>Pelanggan:</strong> nama bisnis, PIC, ring harga, limit & piutang.</li><li><strong>Alamat Kirim:</strong> alamat pengiriman customer.</li><li><strong>Item Pesanan:</strong> SKU, nama, qty diminta, qty disetujui, harga, line total, dan stok tersedia.</li><li><strong>Reservation Aktif:</strong> alokasi stok per lokasi yang sudah di-reserve.</li><li><strong>Riwayat Status & Pesan:</strong> jejak perubahan status dan komunikasi.</li><li><strong>Invoice & Shipment:</strong> daftar invoice dan shipment terkait.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Reserve akan membuat stock reservation aktif dan mengunci stok. Reject akan melepas reservation aktif. Pack hanya berubah status. Ship akan mengonversi reserved stock menjadi issue stock dan mengurangi stok fisik. Invoice diterbitkan setelah status invoice-ready.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Periksa identitas pelanggan dan alamat.</li><li>Review item dan stok tersedia.</li><li>Isi qty approved jika perlu menyesuaikan.</li><li>Klik <strong>Reserve</strong> untuk alokasi stok, atau <strong>Reject</strong> dengan alasan.</li><li>Setelah reserved, gunakan <strong>Pack</strong> lalu <strong>Ship</strong> sesuai alur.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Periksa limit kredit customer sebelum reserve.</li><li>Allow partial akan tetap melanjutkan meskipun ada shortage stok.</li><li>Reservation expiry default 24 jam sejak aksi.</li><li>Ship hanya bisa dilakukan setelah status packing.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Pesanan B2B-DEMO-001 dari PT Maju Jaya, total Rp 5.000.000. Stok semua item tersedia. Reserve dengan qty default, expiry 24 jam, tanpa allow partial. Setelah reserved, lakukan Pack lalu Ship dengan kurir JNE.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    @php
        $canApprove = auth()->user()?->can('b2b_orders.approve');
        $statusValue = $order->status->value;
        $isReserveable = in_array($statusValue, ['pending_confirmation', 'warehouse_validation'], true);
        $isPackable = in_array($statusValue, ['reserved', 'invoice_ready', 'awaiting_payment', 'approved_credit'], true);
        $isShipable = $statusValue === 'packing';
        $isRejectable = ! in_array($statusValue, ['shipped', 'received', 'completed', 'cancelled', 'rejected', 'returned'], true);

        $statusColors = [
            'pending_confirmation' => 'warning',
            'warehouse_validation' => 'warning',
            'reserved' => 'primary',
            'packing' => 'info',
            'invoice_ready' => 'info',
            'awaiting_payment' => 'info',
            'approved_credit' => 'success',
            'shipped' => 'success',
            'cancelled' => 'danger',
            'rejected' => 'danger',
        ];
        $statusColor = $statusColors[$statusValue] ?? 'secondary';

        // Progress steps for visual workflow
        $steps = [
            ['label' => 'Submitted', 'status' => 'pending_confirmation', 'icon' => 'ki-document'],
            ['label' => 'Validated', 'status' => 'warehouse_validation', 'icon' => 'ki-check'],
            ['label' => 'Reserved', 'status' => 'reserved', 'icon' => 'ki-box'],
            ['label' => 'Packed', 'status' => 'packing', 'icon' => 'ki-package'],
            ['label' => 'Shipped', 'status' => 'shipped', 'icon' => 'ki-delivery'],
            ['label' => 'Completed', 'status' => 'completed', 'icon' => 'ki-checked'],
        ];
        $currentStepIndex = match ($statusValue) {
            'warehouse_validation' => 1,
            'reserved', 'invoice_ready', 'awaiting_payment', 'approved_credit' => 2,
            'packing' => 3,
            'shipped' => 4,
            'received', 'completed' => 5,
            default => 0,
        };

        // Quick stats
        $totalItems = $order->items->count();
        $totalQty = $order->items->sum(fn($i) => $i->quantity);
        $totalReserved = $order->items->sum(fn($i) => $i->reserved_quantity ?? 0);
        $itemsShortage = $order->items->where(fn($i) => ($i->available_stock_snapshot ?? 0) < ($i->quantity ?? 0))->count();
    @endphp

    @include('layouts.metronic.partials.flash-toast')

    <div class="d-flex flex-column gap-5">
        <!-- ACTION BAR -->
        <div class="card card-flush border shadow-sm" id="action-bar">
            <div class="card-header border-bottom py-4 gap-4 flex-column flex-lg-row align-items-stretch align-items-lg-center">
                <div class="d-flex align-items-center gap-3 flex-grow-1" style="min-width: 0;">
                    <a href="{{ route('warehouse.b2b-orders.index') }}" class="btn btn-icon btn-light flex-shrink-0" aria-label="Kembali ke antrian pesanan">
                        <i class="ki-outline ki-arrow-left fs-4"></i>
                    </a>
                    <div style="min-width: 0;">
                        <div class="fw-bold text-gray-900 fs-5">Review Pesanan</div>
                        <div class="text-muted fs-7 text-truncate">{{ $order->number }} · {{ $order->customer?->business_name ?? 'Pelanggan tidak tersedia' }}</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap justify-content-start justify-content-lg-end align-items-center gap-2">
                    @if($canApprove)
                        @if($isReserveable)
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#reserveModal">
                                <i class="ki-outline ki-check-circle fs-5 me-1"></i>Reserve Stok
                            </button>
                        @endif
                        @if($isPackable)
                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#packModal">
                                <i class="ki-outline ki-package fs-5 me-1"></i>Mulai Packing
                            </button>
                        @endif
                        @if($isShipable)
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#shipModal">
                                <i class="ki-outline ki-delivery fs-5 me-1"></i>Kirim Pesanan
                            </button>
                        @endif
                        @if($isRejectable)
                            <button type="button" class="btn btn-sm btn-light-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="ki-outline ki-cross-circle fs-5 me-1"></i>Tolak
                            </button>
                        @endif
                    @endif
                    @if($order->invoices->isNotEmpty() && auth()->user()?->can('invoices.view'))
                        <a href="{{ route('invoices.show', $order->invoices->first()) }}" class="btn btn-sm btn-light-primary">
                            <i class="ki-outline ki-document fs-5 me-1"></i>Lihat Invoice
                        </a>
                    @endif
                    <a href="{{ route('warehouse.reservations.index') }}" class="btn btn-sm btn-light">
                        <i class="ki-outline ki-box fs-5 me-1"></i>Reservasi
                    </a>
                </div>
            </div>
        </div>

        <!-- ORDER HERO CARD -->
        <div class="card card-flush border shadow-sm">
            <div class="card-header border-bottom py-4 gap-4 flex-column flex-md-row align-items-stretch align-items-md-center">
                <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                    <span class="badge badge-{{ $statusColor }} fs-7 fw-bold px-3 py-2 flex-shrink-0">{{ $order->status->label() }}</span>
                    <div style="min-width: 0;">
                        <div class="fs-2 fw-bold text-gray-900">{{ $order->number }}</div>
                        <div class="text-muted fs-7 text-truncate">
                            <i class="ki-outline ki-user me-1"></i>
                            {{ $order->customer?->business_name ?? 'Customer' }}
                            @if($order->requested_delivery_date)
                                <span class="text-gray-400 mx-2">·</span>
                                <span class="text-warning">
                                    <i class="ki-outline ki-calendar me-1"></i>
                                    {{ $order->requested_delivery_date->format('d M Y') }} (Target Kirim)
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="ms-md-auto rounded border border-success border-opacity-25 bg-light-success px-5 py-3 text-start text-md-end flex-shrink-0">
                        <div class="text-gray-600 text-uppercase fw-semibold fs-8 mb-1">Grand Total</div>
                        <div class="fs-2 fw-bolder text-success lh-1">
                            {{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}
                        </div>
                </div>
            </div>
            <div class="card-body">
                <!-- MODERN PROGRESS STEPPER -->
                <x-metronic.stepper.modern-stepper :steps="$steps" :currentStepIndex="$currentStepIndex" :order="$order" />

                <!-- KPI CARDS -->
                <div class="row g-4 mb-6">
                    <div class="col-6 col-lg-3">
                        <x-metronic.kpi-card.modern-kpi
                            title="Total Items"
                            value="{{ $totalItems }}"
                            icon="ki-outline ki-file"
                            color="primary"
                        />
                    </div>
                    <div class="col-6 col-lg-3">
                        <x-metronic.kpi-card.modern-kpi
                            title="Qty Ordered"
                            value="{{ qty($totalQty) }}"
                            icon="ki-outline ki-up-box"
                            color="info"
                        />
                    </div>
                    <div class="col-6 col-lg-3">
                        <x-metronic.kpi-card.modern-kpi
                            title="Qty Reserved"
                            value="{{ qty($totalReserved) }}"
                            icon="ki-outline ki-box"
                            color="success"
                        />
                    </div>
                    <div class="col-6 col-lg-3">
                        <x-metronic.kpi-card.modern-kpi
                            title="Shortage"
                            value="{{ $itemsShortage }}"
                            icon="ki-outline {{ $itemsShortage > 0 ? 'ki-error' : 'ki-checked' }}"
                            color="{{ $itemsShortage > 0 ? 'danger' : 'success' }}"
                        />
                    </div>
                </div>

                <!-- ORDER INFO + SHIPPING -->
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card card-flush border h-100">
                            <div class="card-header border-bottom py-3">
                                <h3 class="card-title fw-bold">Informasi Pesanan</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered fs-6 gy-4 mb-0">
                                        <tr>
                                            <td class="text-muted px-4 py-3" style="width: 180px;">
                                                <i class="ki-outline ki-calendar text-primary me-2"></i>Tanggal Submit
                                            </td>
                                            <td class="fw-semibold px-4 py-3">
                                                {{ $order->submitted_at?->format('d M Y, H:i') ?: '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted px-4 py-3">
                                                <i class="ki-outline ki-user text-success me-2"></i>Requester
                                            </td>
                                            <td class="fw-semibold px-4 py-3">
                                                {{ $order->requester?->name ?? '-' }}
                                            </td>
                                        </tr>
                                        @if($order->approved_at)
                                        <tr>
                                            <td class="text-muted px-4 py-3">
                                                <i class="ki-outline ki-checked-circle text-success me-2"></i>Approval Date
                                            </td>
                                            <td class="fw-semibold px-4 py-3">
                                                {{ $order->approved_at->format('d M Y, H:i') }}
                                            </td>
                                        </tr>
                                        @endif
                                        @if($order->requested_delivery_date)
                                        <tr>
                                            <td class="text-muted px-4 py-3">
                                                <i class="ki-outline ki-delivery text-warning me-2"></i>Target Kirim
                                            </td>
                                            <td class="fw-semibold px-4 py-3 text-warning">
                                                {{ $order->requested_delivery_date->format('d M Y') }}
                                            </td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-flush border h-100">
                            <div class="card-header border-bottom py-3">
                                <h3 class="card-title fw-bold">Pengiriman</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-row-bordered fs-6 gy-4 mb-0">
                                        <tr>
                                            <td class="text-muted px-4 py-3" style="width: 180px;">
                                                <i class="ki-outline ki-credit-card text-primary me-2"></i>Payment
                                            </td>
                                            <td class="fw-semibold px-4 py-3">
                                                {{ $order->payment_preference ? ucfirst((string) $order->payment_preference) : '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted px-4 py-3">
                                                <i class="ki-outline ki-truck text-success me-2"></i>Delivery
                                            </td>
                                            <td class="fw-semibold px-4 py-3">
                                                {{ $order->delivery_method ? ucfirst((string) $order->delivery_method) : '-' }}
                                            </td>
                                        </tr>
                                        @if($order->courier_name)
                                        <tr>
                                            <td class="text-muted px-4 py-3">
                                                <i class="ki-outline ki-bag text-warning me-2"></i>Courier
                                            </td>
                                            <td class="fw-semibold px-4 py-3">
                                                {{ $order->courier_name }}
                                            </td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT ROW -->
        <div class="row g-6">
            <!-- LEFT COLUMN -->
            <div class="col-xl-8">
                <!-- ITEM ORDER TABLE -->
                <x-metronic.card title="Item Pesanan" class="mb-6">
                    @if($order->items->isEmpty())
                        <x-metronic.empty-state title="Belum ada item" description="Pesanan belum memiliki item." />
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle table-row-bordered fs-6 gy-4">
                                <thead>
                                    <tr class="text-uppercase text-gray-500 fw-semibold fs-7 border-bottom-2">
                                        <th class="min-w-200px">Produk</th>
                                        <th class="text-end min-w-90px">Qty</th>
                                        <th class="text-end min-w-90px">Disetujui</th>
                                        <th class="text-end min-w-90px">Reserved</th>
                                        <th class="text-end min-w-110px">Harga</th>
                                        <th class="text-end min-w-120px">Line Total</th>
                                        <th class="text-end min-w-90px">Stok</th>
                                        <th class="min-w-100px">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold">
                                    @foreach($order->items as $item)
                                        @php
                                            $stockAvailable = $item->available_stock_snapshot ?? 0;
                                            $stockNeeded = $item->quantity ?? 0;
                                            $stockStatus = $stockAvailable >= $stockNeeded ? 'available' : ($stockAvailable > 0 ? 'partial' : 'empty');
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="text-gray-900 fw-bold">{{ $item->product_name_snapshot }}</span>
                                                    <span class="text-gray-500 fs-7">{{ $item->sku_snapshot }} · {{ $item->unit_name_snapshot }}</span>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-light-warning fs-7">{{ qty($item->quantity) }}</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-light-success fs-7">{{ qty($item->approved_quantity ?? $item->quantity) }}</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-light-primary fs-7">{{ qty($item->reserved_quantity ?? 0) }}</span>
                                            </td>
                                            <td class="text-end text-muted">
                                                {{ App\Support\CurrencyFormatter::rupiah($item->selected_price) }}
                                            </td>
                                            <td class="text-end fw-bold">
                                                {{ App\Support\CurrencyFormatter::rupiah($item->line_total) }}
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-light-{{ $stockStatus === 'available' ? 'success' : ($stockStatus === 'partial' ? 'warning' : 'danger') }} fs-7">
                                                    {{ qty($stockAvailable) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($stockStatus === 'available')
                                                    <span class="badge badge-light-success">Tersedia</span>
                                                @elseif($stockStatus === 'partial')
                                                    <span class="badge badge-light-warning">Sebagian</span>
                                                @else
                                                    <span class="badge badge-light-danger">Habis</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tfoot>
                                        <tr class="bg-light border-top">
                                            <td colspan="5" class="text-end fw-bold text-gray-900">Total</td>
                                            <td class="text-end fw-bold text-primary fs-6">
                                                {{ App\Support\CurrencyFormatter::rupiah($order->items->sum(fn($i) => $i->line_total)) }}
                                            </td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-metronic.card>

                <!-- ACTIVE RESERVATIONS -->
                @if($order->reservations->isNotEmpty())
                <x-metronic.card title="Reservation Aktif" class="mb-6">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-bordered fs-6 gy-4">
                            <thead>
                                <tr class="text-uppercase text-gray-500 fw-semibold fs-7 border-bottom-2">
                                    <th class="min-w-180px">Produk</th>
                                    <th class="min-w-200px">Lokasi</th>
                                    <th class="text-end min-w-90px">Qty</th>
                                    <th class="min-w-120px">Status</th>
                                    <th class="min-w-150px">Expired</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold">
                                @foreach($order->reservations as $reservation)
                                    <tr>
                                        <td>{{ $reservation->product?->name ?? '-' }}</td>
                                        <td class="text-muted">
                                            <div>{{ $reservation->workLocation?->name ?? '-' }}</div>
                                            <div class="fs-7">{{ $reservation->warehouseLocation?->code ?? '-' }}</div>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge badge-light-primary fs-7">{{ qty($reservation->quantity) }}</span>
                                        </td>
                                        <td>
                                            <x-metronic.status-badge :status="$reservation->status" :label="$reservation->status->label()" />
                                        </td>
                                        <td class="text-muted">
                                            {{ $reservation->expires_at?->format('d/m/Y H:i') ?: '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-metronic.card>
                @endif

                <!-- STATUS TIMELINE -->
                <x-metronic.card title="Timeline Status" class="mb-6">
                    @if($order->statusHistories->isNotEmpty())
                        <div class="position-relative">
                            <div class="position-absolute" style="left: 19px; top: 10px; bottom: 10px; width: 2px; background: var(--bs-gray-200);"></div>
                            @foreach($order->statusHistories as $index => $history)
                                <div class="d-flex gap-4 mb-4 position-relative">
                                    <div class="symbol symbol-40px flex-shrink-0 position-relative z-1">
                                        <span class="symbol-circle bg-{{ $index === count($order->statusHistories) - 1 ? 'success' : 'light' }}-{{ $index === count($order->statusHistories) - 1 ? 'success' : 'gray' }}">
                                            <i class="ki-outline {{ $index === count($order->statusHistories) - 1 ? 'ki-checked' : 'ki-arrow-right' }} fs-3 text-{{ $index === count($order->statusHistories) - 1 ? 'success' : 'gray' }}"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 pt-1">
                                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                            <span class="badge badge-light-{{ $index === count($order->statusHistories) - 1 ? 'success' : 'gray' }} fw-bold">
                                                {{ $history->to_status }}
                                            </span>
                                            <span class="text-muted fs-7">{{ $history->created_at?->format('d M Y, H:i') }}</span>
                                        </div>
                                        <div class="text-gray-700">
                                            @if($history->actor)
                                                <strong>{{ $history->actor->name }}</strong>
                                            @endif
                                            @if($history->note)
                                                <span class="text-muted"> — {{ $history->note }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-metronic.empty-state title="Belum ada riwayat" description="Timeline status akan muncul setelah ada perubahan." />
                    @endif
                </x-metronic.card>

                <!-- MESSAGES -->
                @if($order->messages->isNotEmpty())
                <x-metronic.card title="Pesan" class="mb-6">
                    <div class="list-group list-group-flush">
                        @foreach($order->messages as $message)
                            <div class="list-group-item border-0 border-bottom py-4">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="symbol symbol-40px flex-shrink-0">
                                        <span class="symbol-circle bg-light-primary">
                                            <i class="ki-outline ki-message fs-2 text-primary"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                            <span class="fw-bold">{{ $message->user?->name ?: 'Sistem' }}</span>
                                            <span class="badge badge-light-secondary fs-7">{{ $message->visibility }}</span>
                                            <span class="text-muted fs-7">{{ $message->created_at?->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <div class="text-gray-700">{{ $message->message }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-metronic.card>
                @endif

                <!-- INVOICE & SHIPMENT -->
                <div class="row g-6 mb-6">
                    @if($order->invoices->isNotEmpty())
                        <div class="col-lg-6">
                            <x-metronic.card title="Invoice" class="h-100">
                                <ul class="list-group list-group-flush">
                                    @foreach($order->invoices as $invoice)
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="symbol symbol-40px flex-shrink-0">
                                                    <span class="symbol-circle bg-light-primary">
                                                        <i class="ki-outline ki-file fs-2 text-primary"></i>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="fw-bold">{{ $invoice->number }}</div>
                                                    <div class="text-muted fs-7">{{ $invoice->created_at?->format('d M Y') }}</div>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-primary">{{ App\Support\CurrencyFormatter::rupiah($invoice->total_amount ?? 0) }}</div>
                                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-light">
                                                    <i class="ki-outline ki-eye fs-6"></i>
                                                </a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-metronic.card>
                        </div>
                    @endif
                    @if($order->shipments->isNotEmpty())
                        <div class="col-lg-6">
                            <x-metronic.card title="Shipment" class="h-100">
                                <ul class="list-group list-group-flush">
                                    @foreach($order->shipments as $shipment)
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="symbol symbol-40px flex-shrink-0">
                                                    <span class="symbol-circle bg-light-success">
                                                        <i class="ki-outline ki-delivery fs-2 text-success"></i>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="fw-bold">{{ $shipment->tracking_number ?: 'Shipment #' . $shipment->id }}</div>
                                                    <div class="text-muted fs-7">{{ $shipment->courier_name ?? 'Kurir' }}</div>
                                                </div>
                                            </div>
                                            <x-metronic.status-badge :status="$shipment->status" :label="$shipment->status->label()" />
                                        </li>
                                    @endforeach
                                </ul>
                            </x-metronic.card>
                        </div>
                    @else
                        @if($statusValue === 'completed')
                        <div class="col-lg-6">
                            <x-metronic.card title="Shipment" class="h-100">
                                <x-metronic.empty-state title="Tidak ada shipment" description="Pesanan sudah dikirim, namun belum ada detail shipment." />
                            </x-metronic.card>
                        </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- RIGHT COLUMN - SIDEBAR -->
            <div class="col-xl-4">
                <!-- CUSTOMER INFO -->
                <x-metronic.card title="Informasi Pelanggan" class="mb-6">
                    @if($order->customer)
                        @can('view', $order->customer)
                            <x-slot:toolbar>
                                <a href="{{ route('admin.customers.show', $order->customer) }}" class="btn btn-sm btn-light-primary">
                                    <i class="ki-outline ki-profile-user fs-5 me-1"></i>Lihat Detail
                                </a>
                            </x-slot:toolbar>
                        @endcan
                    @endif
                    <div class="d-flex align-items-center mb-4">
                        <div class="symbol symbol-60px flex-shrink-0 me-3">
                            <span class="symbol-circle bg-light-primary">
                                <i class="ki-outline ki-business fs-2x text-primary"></i>
                            </span>
                        </div>
                        <div>
                            <div class="fw-bold fs-5">{{ $order->customer?->business_name ?? '-' }}</div>
                            <div class="text-muted">{{ $order->customer?->price_category ?? '-' }}</div>
                        </div>
                    </div>
                    <div class="separator mb-4"></div>
                    <div class="row g-3">
                        <div class="col-sm-4 col-xl-12 col-xxl-4">
                            <div class="rounded bg-light-primary p-3 h-100">
                                <div class="text-muted fs-8 mb-1">Total Limit</div>
                                <div class="fw-bold text-gray-900">{{ App\Support\CurrencyFormatter::rupiah($creditUsage['limit']) }}</div>
                            </div>
                        </div>
                        <div class="col-sm-4 col-xl-12 col-xxl-4">
                            <div class="rounded bg-light-warning p-3 h-100">
                                <div class="text-muted fs-8 mb-1">Sudah Digunakan</div>
                                <div class="fw-bold text-warning">{{ App\Support\CurrencyFormatter::rupiah($creditUsage['used']) }}</div>
                            </div>
                        </div>
                        <div class="col-sm-4 col-xl-12 col-xxl-4">
                            <div class="rounded bg-light-success p-3 h-100">
                                <div class="text-muted fs-8 mb-1">Sisa Limit</div>
                                <div class="fw-bold text-success">
                                    {{ App\Support\CurrencyFormatter::rupiah($creditUsage['remaining']) }}
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="rounded border p-4 mt-1">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                    <div>
                                        <div class="fw-bold text-gray-900">Pemakaian Limit</div>
                                        <div class="text-muted fs-8">
                                            {{ App\Support\CurrencyFormatter::rupiah($creditUsage['used']) }} dari {{ App\Support\CurrencyFormatter::rupiah($creditUsage['limit']) }}
                                        </div>
                                    </div>
                                    <span class="badge badge-light-{{ $creditUsage['color'] }}">
                                        {{ $creditUsage['label'] }} · {{ qty($creditUsage['percentage']) }}%
                                    </span>
                                </div>
                                <div class="progress bg-light-{{ $creditUsage['color'] }}" style="height: 10px;" role="progressbar" aria-label="Pemakaian limit pelanggan" aria-valuenow="{{ $creditUsage['bar_percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-{{ $creditUsage['color'] }}" style="width: {{ $creditUsage['bar_percentage'] }}%"></div>
                                </div>
                                <div class="d-flex flex-wrap justify-content-between gap-2 mt-2 text-muted fs-8">
                                    <span>Sisa limit: <strong class="text-gray-700">{{ App\Support\CurrencyFormatter::rupiah($creditUsage['remaining']) }}</strong></span>
                                    @if($creditUsage['excess'] !== '0.00')
                                        <span class="text-danger fw-semibold">Melebihi {{ App\Support\CurrencyFormatter::rupiah($creditUsage['excess']) }}</span>
                                    @else
                                        <span>{{ qty($creditUsage['percentage']) }}% digunakan</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </x-metronic.card>

                <!-- SHIPPING ADDRESS -->
                <x-metronic.card title="Alamat Kirim" class="mb-6">
                    @if($order->address)
                        <div class="d-flex align-items-start">
                            <div class="symbol symbol-50px flex-shrink-0 me-3">
                                <span class="symbol-circle bg-light-info">
                                    <i class="ki-outline ki-location fs-2x text-info"></i>
                                </span>
                            </div>
                            <div>
                                <div class="fw-bold">{{ $order->address->label ?: 'Alamat Utama' }}</div>
                                <div class="text-gray-700 mt-1">{{ $order->address->recipient_name }} · {{ $order->address->phone }}</div>
                                <div class="text-muted fs-7 mt-1">{{ $order->address->address_line }}</div>
                                <div class="text-muted fs-7">{{ $order->address->city }} {{ $order->address->province }} {{ $order->address->postal_code }}</div>
                            </div>
                        </div>
                    @else
                        <x-metronic.empty-state title="Alamat tidak tersedia" description="Customer belum memilih alamat pengiriman." />
                    @endif
                </x-metronic.card>

                <!-- ORDER NOTES -->
                @if($order->notes)
                <x-metronic.card title="Catatan Pesanan" class="mb-6">
                    <div class="bg-light p-3 rounded">
                        <i class="ki-outline ki-note text-primary me-2"></i>
                        {{ $order->notes }}
                    </div>
                </x-metronic.card>
                @endif

                @if(! $canApprove)
                    <x-metronic.card class="mb-6">
                        <div class="text-center py-6">
                            <div class="symbol symbol-70px mb-4">
                                <span class="symbol-circle bg-light-warning">
                                    <i class="ki-outline ki-lock fs-3x text-warning"></i>
                                </span>
                            </div>
                            <div class="fw-bold text-gray-900 mb-2">Aksi Diblokir</div>
                            <div class="text-muted fs-7">Anda tidak memiliki izin untuk memproses pesanan ini.</div>
                        </div>
                    </x-metronic.card>
                @endif
            </div>
        </div>
    </div>

    <!-- MODALS -->
    @includeIf('warehouse.b2b-orders.partials.reserve-modal', ['order' => $order, 'isReserveable' => $isReserveable])
    @includeIf('warehouse.b2b-orders.partials.pack-modal', ['order' => $order, 'isPackable' => $isPackable])
    @includeIf('warehouse.b2b-orders.partials.ship-modal', ['order' => $order, 'isShipable' => $isShipable])
    @includeIf('warehouse.b2b-orders.partials.reject-modal', ['order' => $order, 'isRejectable' => $isRejectable])
@endsection
