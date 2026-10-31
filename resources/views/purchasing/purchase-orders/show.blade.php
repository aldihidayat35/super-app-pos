@extends('layouts.metronic.app')

@section('title', 'Pesanan ' . $purchaseOrder->number . ' - ' . config('app.name'))
@section('page_title', 'Detail dan Approval PO - Pesanan ke Supplier')

@section('toolbar_actions')
    @php
        $supplierPhone = $purchaseOrder->supplier?->whatsapp_number ?: $purchaseOrder->supplier?->phone_number;
        $cleanPhone = preg_replace('/[^0-9]/', '', $supplierPhone ?? '');
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }
        $lines = [];
        $lines[] = "*PESANAN PEMBELIAN (PURCHASE ORDER)*";
        $lines[] = "Halo *" . ($purchaseOrder->supplier?->contact_name ?: $purchaseOrder->supplier?->name) . "*, kami dari *" . config('app.name', 'GudangToko') . "* ingin memesan barang dengan rincian sebagai berikut:";
        $lines[] = "";
        $lines[] = "📄 *No. PO:* " . $purchaseOrder->number;
        $lines[] = "📅 *Tanggal:* " . optional($purchaseOrder->order_date)->format('d/m/Y');
        $lines[] = "📍 *Tujuan Kirim:* " . ($purchaseOrder->destinationWorkLocation?->name ?? $purchaseOrder->warehouse?->name);
        if ($purchaseOrder->expected_at) {
            $lines[] = "⏱ *Estimasi Tiba:* " . optional($purchaseOrder->expected_at)->format('d/m/Y');
        }
        $lines[] = "";
        $lines[] = "*Daftar Barang:*";
        foreach ($purchaseOrder->items as $idx => $item) {
            $lines[] = ($idx + 1) . ". *" . $item->product_name_snapshot . "*: " . qty($item->quantity_ordered) . " " . $item->unit_name_snapshot . " @ Rp " . number_format((float) $item->unit_price, 0, ',', '.');
        }
        $lines[] = "";
        $lines[] = "💰 *Total Nilai Pesanan:* Rp " . number_format((float) $purchaseOrder->grand_total, 0, ',', '.');
        if ($purchaseOrder->notes) {
            $lines[] = "📝 *Catatan:* " . $purchaseOrder->notes;
        }
        $lines[] = "";
        $lines[] = "Mohon konfirmasi ketersediaan barang dan jadwal pengirimannya. Terima kasih! 🙏";
        $waMessage = implode("\n", $lines);
        $waUrl = !empty($cleanPhone) ? "https://wa.me/{$cleanPhone}?text=" . rawurlencode($waMessage) : null;
    @endphp

    @if($waUrl)
        <a href="{{ $waUrl }}" target="_blank" class="btn btn-success" id="btn-send-whatsapp" title="Kirim rincian PO via WhatsApp ke Supplier" onclick="handleWaSend(event, '{{ $waUrl }}', {{ in_array($purchaseOrder->status, [\App\Enums\PurchaseOrderStatus::DRAFT, \App\Enums\PurchaseOrderStatus::APPROVED]) ? 'true' : 'false' }})">
            <i class="ki-outline ki-whatsapp fs-4 me-1"></i> Kirim WhatsApp
        </a>
    @else
        <button type="button" class="btn btn-light-success" onclick="copyWaText({{ json_encode($waMessage) }})" title="Nomor WhatsApp belum terdaftar di master supplier, klik untuk salin teks pesanan">
            <i class="ki-outline ki-copy fs-4 me-1"></i> Salin Teks Pesanan
        </button>
    @endif

    <form id="form-mark-sent" method="POST" action="{{ route('purchasing.purchase-orders.send', $purchaseOrder) }}" style="display: none;">
        @csrf
    </form>

    <a href="{{ route('purchasing.purchase-orders.print', $purchaseOrder) }}" class="btn btn-light-success" title="Cetak Pesanan ke Supplier">
        <i class="ki-outline ki-printer fs-5 me-1"></i>Cetak
    </a>
    <a href="{{ route('purchasing.purchase-orders.print', [$purchaseOrder, 'download' => 'pdf']) }}" class="btn btn-light-primary" title="Unduh PDF">
        <i class="ki-outline ki-file-down fs-5 me-1"></i>PDF
    </a>
    <a href="{{ route('purchasing.purchase-orders.export-one', $purchaseOrder) }}" class="btn btn-light-info" title="Unduh Excel">
        <i class="ki-outline ki-file-down fs-5 me-1"></i>Excel
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="purchasing-po-show" title="Panduan Pesanan ke Supplier">
        <x-slot:function><p>Pesanan ke Supplier (Purchase Order) adalah dokumen pemesanan resmi perusahaan kepada pemasok eksternal. Halaman ini memuat rincian barang, harga, termin, serta tombol pengiriman langsung via WhatsApp.</p></x-slot:function>
        <x-slot:workflow><ol><li>Purchasing menyusun dan menyetujui pesanan.</li><li>Kirim PO ke supplier via tombol WhatsApp atau cetak PDF.</li><li>Tandai status sebagai 'Sudah Dikirim ke Supplier'.</li><li>Gudang mencatat penerimaan barang (Goods Receipt) saat truk tiba.</li></ol></x-slot:workflow>
        <x-slot:warnings><p>Pesanan yang sudah menerima barang tidak dapat dibatalkan untuk menjaga keakuratan stok dan utang usaha.</p></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    @php
        $status = $purchaseOrder->status;
        $totalOrdered = (float) $purchaseOrder->orderedQuantity();
        $totalReceived = (float) $purchaseOrder->receivedQuantity();
        $totalOutstanding = max($totalOrdered - $totalReceived, 0);
        $progressPercent = $totalOrdered > 0 ? min(100, ($totalReceived / $totalOrdered) * 100) : 0;
        
        $processSteps = [
            [
                'step' => 1,
                'label' => 'Pesanan Dibuat',
                'description' => 'Disusun oleh ' . ($purchaseOrder->creator?->name ?: 'Penanggung Jawab') . '.',
                'active' => true,
                'completed' => !in_array($status, [\App\Enums\PurchaseOrderStatus::DRAFT]),
            ],
            [
                'step' => 2,
                'label' => 'Dikirim ke Supplier',
                'description' => $purchaseOrder->sent_at ? 'Dikirim pada ' . $purchaseOrder->sent_at->format('d/m/Y H:i') : 'Kirim via WhatsApp atau berkas PO.',
                'active' => in_array($status, [\App\Enums\PurchaseOrderStatus::APPROVED, \App\Enums\PurchaseOrderStatus::SENT_TO_SUPPLIER]),
                'completed' => in_array($status, [\App\Enums\PurchaseOrderStatus::SENT_TO_SUPPLIER, \App\Enums\PurchaseOrderStatus::PARTIALLY_RECEIVED, \App\Enums\PurchaseOrderStatus::COMPLETED]),
            ],
            [
                'step' => 3,
                'label' => 'Penerimaan Fisik',
                'description' => $totalReceived > 0 ? qty($totalReceived) . ' / ' . qty($totalOrdered) . ' diterima' : 'Verifikasi fisik di gudang/toko saat tiba.',
                'active' => in_array($status, [\App\Enums\PurchaseOrderStatus::SENT_TO_SUPPLIER, \App\Enums\PurchaseOrderStatus::PARTIALLY_RECEIVED]),
                'completed' => $status === \App\Enums\PurchaseOrderStatus::COMPLETED,
            ],
        ];

        [$processHolder, $processNext] = match ($status) {
            \App\Enums\PurchaseOrderStatus::DRAFT => [$purchaseOrder->creator?->name ?: 'Penanggung Jawab Pesanan', 'Lengkapi pesanan dan klik "Kirim Pesanan ke Supplier" atau langsung kirim via WhatsApp.'],
            \App\Enums\PurchaseOrderStatus::SUBMITTED, \App\Enums\PurchaseOrderStatus::APPROVED, \App\Enums\PurchaseOrderStatus::SENT_TO_SUPPLIER => [$purchaseOrder->supplier?->name ?: 'Supplier Rekanan', 'Menunggu pengiriman barang. Catat penerimaan fisik saat truk tiba di lokasi.'],
            \App\Enums\PurchaseOrderStatus::PARTIALLY_RECEIVED => ['Petugas Gudang / Toko Penerima', 'Barang tiba sebagian. Lanjutkan penerimaan untuk sisa barang yang belum tiba.'],
            \App\Enums\PurchaseOrderStatus::COMPLETED => ['Selesai', 'Seluruh barang telah diterima fisik, stok gudang/toko bertambah, dan tercatat di utang dagang.'],
            \App\Enums\PurchaseOrderStatus::CANCELLED => ['Dibatalkan', 'Pesanan ke supplier telah dibatalkan.'],
            default => [$purchaseOrder->creator?->name ?: 'Penanggung Jawab', 'Proses pesanan.'],
        };

        $canEdit = (auth()->user()?->can('update', $purchaseOrder) ?? false) && $totalReceived == 0;
        $canSend = auth()->user()?->can('send', $purchaseOrder) ?? false;
        $canCancel = (auth()->user()?->can('cancel', $purchaseOrder) ?? false) && $totalReceived == 0;
        $canReceive = (auth()->user()?->can('goods_receipts.create') ?? false)
            && in_array($status, [\App\Enums\PurchaseOrderStatus::APPROVED, \App\Enums\PurchaseOrderStatus::SENT_TO_SUPPLIER, \App\Enums\PurchaseOrderStatus::PARTIALLY_RECEIVED], true);
        $hasAction = $canEdit || $canSend || $canCancel || $canReceive;
    @endphp

    @if ($status === \App\Enums\PurchaseOrderStatus::CANCELLED)
        <div class="alert alert-light-danger d-flex align-items-start mb-5">
            <i class="ki-outline ki-cross-circle fs-2 text-danger me-3"></i>
            <div><div class="fw-bold">Pesanan ke Supplier dibatalkan</div><div class="fs-7">{{ $purchaseOrder->cancel_reason ?: 'Tidak ada alasan pembatalan yang dicatat.' }}</div></div>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
        <div>
            <div class="d-flex align-items-center gap-3 mb-1">
                <h2 class="fs-3 fw-bold mb-0">{{ $purchaseOrder->number }}</h2>
                <x-metronic.status-badge :status="$status" />
            </div>
            <div class="text-muted fs-7">Penanggung Jawab: <span class="fw-semibold text-gray-800">{{ $purchaseOrder->creator?->name ?: 'Sistem' }}</span> | Diterbitkan pada {{ $purchaseOrder->created_at?->format('d/m/Y H:i') ?: '-' }}</div>
        </div>
        <div class="text-end">
            <div class="text-muted fs-8 fw-bold text-uppercase">TOTAL NILAI PESANAN</div>
            <div class="fs-2 fw-bolder text-primary">Rp {{ number_format((float) $purchaseOrder->grand_total, 0, ',', '.') }}</div>
        </div>
    </div>

    <x-metronic.card title="Status dan Alur Operasional Pesanan">
        <div class="row g-4">
            @foreach ($processSteps as $step)
                @php
                    $isDone = $step['completed'];
                    $isCurrent = $step['active'] && !$isDone;
                    $borderClass = $isDone ? 'border-success bg-light-success bg-opacity-25' : ($isCurrent ? 'border-primary bg-light-primary' : 'border-gray-200');
                    $badgeClass = $isDone ? 'badge-success' : ($isCurrent ? 'badge-primary' : 'badge-light-secondary');
                @endphp
                <div class="col-12 col-md-4">
                    <div class="border rounded p-4 h-100 {{ $borderClass }}">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge {{ $badgeClass }} fs-7">{{ $step['step'] }}</span>
                            <span class="fw-bolder fs-6 text-gray-800">{{ $step['label'] }}</span>
                            @if($isDone)
                                <i class="ki-outline ki-check-circle fs-5 text-success ms-auto"></i>
                            @elseif($isCurrent)
                                <span class="badge badge-light-primary ms-auto fs-8">Sedang Berjalan</span>
                            @endif
                        </div>
                        <div class="text-muted fs-7">{{ $step['description'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="row g-4 mt-1 pt-3 border-top">
            <div class="col-md-6">
                <div class="text-muted fs-8 mb-1 text-uppercase fw-semibold">Penanggung Jawab Pesanan</div>
                <div class="fw-bold text-gray-800 fs-6">
                    <i class="ki-outline ki-user fs-5 text-primary me-1"></i>
                    {{ $purchaseOrder->creator?->name ?: 'Penanggung Jawab' }}
                    <span class="badge badge-light-info ms-2 fs-8">{{ $purchaseOrder->destinationWorkLocation?->typeLabel() ?? 'Lokasi Penerima' }}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-muted fs-8 mb-1 text-uppercase fw-semibold">Langkah Selanjutnya</div>
                <div class="fw-semibold text-gray-700 fs-7">{{ $processNext }}</div>
            </div>
        </div>
    </x-metronic.card>

    <div class="row g-5 mt-0">
        <div class="col-xl-8">
            <x-metronic.card title="Informasi Dokumen Pesanan">
                <div class="row g-4">
                    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-8 mb-1">NOMOR PO</div><div class="fw-semibold">{{ $purchaseOrder->number }}</div></div>
                    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-8 mb-1">TANGGAL PESANAN</div><div class="fw-semibold">{{ $purchaseOrder->order_date?->format('d/m/Y') ?: '-' }}</div></div>
                    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-8 mb-1">ESTIMASI TIBA</div><div class="fw-semibold {{ $purchaseOrder->expected_at?->isPast() && ! $status->isFinal() ? 'text-danger' : '' }}">{{ $purchaseOrder->expected_at?->format('d/m/Y') ?: 'Belum ditentukan' }}</div></div>
                    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-8 mb-1">STATUS SAAT INI</div><x-metronic.status-badge :status="$status" /></div>
                    <div class="col-md-6">
                        <div class="text-muted fs-8 mb-1">REFERENSI PENGAJUAN INTERNAL</div>
                        @if ($purchaseOrder->purchaseRequest)
                            @can('view', $purchaseOrder->purchaseRequest)
                                <a href="{{ route('purchasing.requests.show', $purchaseOrder->purchaseRequest) }}" class="fw-semibold text-primary">Berasal dari {{ $purchaseOrder->purchaseRequest->number }}</a>
                            @else
                                <span class="fw-semibold">{{ $purchaseOrder->purchaseRequest->number }}</span>
                            @endcan
                        @else
                            <span class="text-muted">PO dibuat langsung (Direct PO).</span>
                        @endif
                    </div>
                    @if ($purchaseOrder->notes)
                        <div class="col-md-6"><div class="text-muted fs-8 mb-1">CATATAN PO</div><div class="fw-semibold">{{ $purchaseOrder->notes }}</div></div>
                    @endif
                </div>

                <div class="separator my-5"></div>

                <div class="row g-5">
                    <div class="col-md-6">
                        <div class="fw-bold mb-2 fs-6">Supplier Rekanan</div>
                        <div class="text-muted fs-8 mb-1">Pemasok resmi yang menerima pesanan ini:</div>
                        @if (auth()->user()?->can('suppliers.view'))
                            <a href="{{ route('admin.suppliers.show', $purchaseOrder->supplier_id) }}" class="fw-bold text-primary fs-6">{{ $purchaseOrder->supplier?->code }} - {{ $purchaseOrder->supplier?->name }}</a>
                        @else
                            <div class="fw-bold fs-6">{{ $purchaseOrder->supplier?->code }} - {{ $purchaseOrder->supplier?->name }}</div>
                        @endif
                        <div class="text-muted fs-7 mt-2">
                            <i class="ki-outline ki-phone fs-7 me-1"></i>Kontak: {{ $purchaseOrder->supplier?->contact_name ?: '-' }} / {{ $purchaseOrder->supplier?->phone_number ?: '-' }}
                            @if($purchaseOrder->supplier?->whatsapp_number)
                                <span class="badge badge-light-success ms-1">WA: {{ $purchaseOrder->supplier->whatsapp_number }}</span>
                            @endif
                        </div>
                        <div class="text-muted fs-7 mt-1">Termin pembayaran: {{ $purchaseOrder->payment_term_days }} hari</div>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bold mb-2 fs-6">Lokasi Penerimaan Fisik</div>
                        <div class="text-muted fs-8 mb-1">Gudang atau toko penerima kiriman barang:</div>
                        <div class="fw-bold fs-6 text-gray-800">{{ $purchaseOrder->destinationWorkLocation?->typeLabel() ?? 'Gudang' }} - {{ $purchaseOrder->destinationName() }}</div>
                        <div class="text-muted fs-7 mt-2">Penerimaan fisik barang dicatat melalui menu Penerimaan Barang (Goods Receipt).</div>
                    </div>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Daftar Barang yang Dipesan" class="mt-5">
                @if ($totalOrdered > 0)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                        <div>
                            <span class="fw-semibold">Progress Penerimaan {{ number_format($progressPercent, 0) }}%</span>
                            <div class="text-muted fs-8">{{ qty($totalReceived) }} diterima dari {{ qty($totalOrdered) }} dipesan; {{ qty($totalOutstanding) }} sisa.</div>
                        </div>
                        <div class="progress w-200px" style="height: 8px;">
                            <div class="progress-bar bg-{{ $progressPercent >= 100 ? 'success' : 'primary' }}" style="width: {{ $progressPercent }}%"></div>
                        </div>
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase fs-7">
                                <th>Produk</th>
                                <th class="text-end">Dipesan</th>
                                <th class="text-end">Diterima</th>
                                <th class="text-end">Harga Satuan</th>
                                <th class="text-end">Diskon</th>
                                <th class="text-end">Pajak</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchaseOrder->items as $item)
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-gray-800">{{ $item->product_name_snapshot }}</span>
                                        <div class="text-muted fs-8">{{ $item->product_sku_snapshot }} / {{ $item->unit_name_snapshot }} x {{ qty($item->conversion_factor_snapshot) }}</div>
                                    </td>
                                    <td class="text-end fw-semibold">{{ qty($item->quantity_ordered) }}</td>
                                    <td class="text-end">
                                        <span class="{{ (float) $item->quantity_received > 0 ? 'text-success fw-bold' : '' }}">{{ qty($item->quantity_received) }}</span>
                                        <div class="text-muted fs-8">Sisa {{ qty($item->outstandingQuantity()) }}</div>
                                    </td>
                                    <td class="text-end">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format((float) $item->discount_amount, 0, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format((float) $item->tax_amount, 0, ',', '.') }}</td>
                                    <td class="text-end fw-bold">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Rincian Total Biaya" class="mt-5">
                <div class="row g-4">
                    <div class="col-6 col-lg"><div class="text-muted fs-8">SUBTOTAL ITEM</div><div class="fw-semibold">Rp {{ number_format((float) $purchaseOrder->items_subtotal, 0, ',', '.') }}</div></div>
                    <div class="col-6 col-lg"><div class="text-muted fs-8">DISKON NOTA</div><div class="fw-semibold text-danger">Rp {{ number_format((float) $purchaseOrder->header_discount, 0, ',', '.') }}</div></div>
                    <div class="col-6 col-lg"><div class="text-muted fs-8">ONGKOS KIRIM</div><div class="fw-semibold">Rp {{ number_format((float) $purchaseOrder->freight_cost, 0, ',', '.') }}</div></div>
                    <div class="col-6 col-lg"><div class="text-muted fs-8">BIAYA LAIN</div><div class="fw-semibold">Rp {{ number_format((float) $purchaseOrder->additional_cost, 0, ',', '.') }}</div></div>
                    <div class="col-12 col-lg"><div class="text-muted fs-8 fw-bold">GRAND TOTAL</div><div class="fs-3 fw-bold text-primary">Rp {{ number_format((float) $purchaseOrder->grand_total, 0, ',', '.') }}</div></div>
                </div>
            </x-metronic.card>
        </div>

        <div class="col-xl-4">
            <x-metronic.card title="Aksi Cepat Pesanan">
                <div class="alert alert-light-primary fs-7 mb-4 d-flex align-items-start">
                    <i class="ki-outline ki-information-5 fs-4 text-primary me-2 mt-1"></i>
                    <div>{{ $processNext }}</div>
                </div>

                {{-- Status Draft: Opsi Kirim Langsung atau Terbitkan --}}
                @if ($status === \App\Enums\PurchaseOrderStatus::DRAFT && $canSend)
                    <form method="POST" action="{{ route('purchasing.purchase-orders.send', $purchaseOrder) }}" class="mb-3">
                        @csrf
                        <button class="btn btn-primary w-100 fs-6 py-3">
                            <i class="ki-outline ki-send fs-4 me-2"></i>Kirim Pesanan ke Supplier
                        </button>
                    </form>
                    <form method="POST" action="{{ route('purchasing.purchase-orders.approve', $purchaseOrder) }}" class="mb-3">
                        @csrf
                        <button class="btn btn-light-success w-100">
                            <i class="ki-outline ki-check-circle fs-5 me-2"></i>Terbitkan & Siap Kirim
                        </button>
                    </form>
                @endif

                {{-- Status Submitted: Langsung bisa dikirim / disetujui --}}
                @if ($status === \App\Enums\PurchaseOrderStatus::SUBMITTED && $canSend)
                    <form method="POST" action="{{ route('purchasing.purchase-orders.send', $purchaseOrder) }}" class="mb-3">
                        @csrf
                        <button class="btn btn-primary w-100 fs-6 py-3">
                            <i class="ki-outline ki-send fs-4 me-2"></i>Kirim Pesanan ke Supplier
                        </button>
                    </form>
                @endif

                {{-- Status Approved (Siap Kirim): Tombol Tandai Sudah Dikirim --}}
                @if ($status === \App\Enums\PurchaseOrderStatus::APPROVED && $canSend)
                    <form method="POST" action="{{ route('purchasing.purchase-orders.send', $purchaseOrder) }}" class="mb-3">
                        @csrf
                        <button class="btn btn-info w-100 fs-6 py-3 mb-3">
                            <i class="ki-outline ki-send fs-4 me-2"></i>Tandai Sudah Dikirim ke Supplier
                        </button>
                    </form>
                @endif

                {{-- Penerimaan Barang --}}
                @if ($canReceive)
                    <a href="{{ route('warehouse.goods-receipts.create', ['purchase_order_id' => $purchaseOrder->id]) }}" class="btn btn-success w-100 fs-6 py-3 mb-3">
                        <i class="ki-outline ki-package fs-4 me-2"></i>Catat Penerimaan Barang
                    </a>
                @endif

                {{-- Edit Rincian jika belum ada penerimaan barang --}}
                @if ($canEdit)
                    <a href="{{ route('purchasing.purchase-orders.edit', $purchaseOrder) }}" class="btn btn-light-primary w-100 mb-3">
                        <i class="ki-outline ki-pencil fs-5 me-2"></i>Edit Rincian Pesanan
                    </a>
                @endif

                {{-- Pembatalan jika belum ada penerimaan barang --}}
                @if ($canCancel)
                    <div class="border-top pt-4 mt-2">
                        <form method="POST" action="{{ route('purchasing.purchase-orders.cancel', $purchaseOrder) }}" onsubmit="return confirm('Yakin ingin membatalkan Pesanan ke Supplier ini?')">
                            @csrf
                            <x-metronic.form-group name="reason" label="Alasan Pembatalan" required help="Pembatalan bersifat final dan dicatat pada riwayat audit.">
                                <textarea name="reason" rows="2" class="form-control form-control-solid" required placeholder="Contoh: Supplier kehabisan stok / ganti rekanan">{{ old('reason') }}</textarea>
                            </x-metronic.form-group>
                            <button class="btn btn-light-danger w-100"><i class="ki-outline ki-cross fs-5 me-2"></i>Batalkan Pesanan</button>
                        </form>
                    </div>
                @endif

                @unless ($hasAction)
                    <div class="text-muted fs-7">Tidak ada aksi transaksi lanjutan yang memerlukan tindakan Anda saat ini.</div>
                @endunless
            </x-metronic.card>

            <x-metronic.card title="Riwayat Status" class="mt-5">
                @forelse ($purchaseOrder->statusHistories as $history)
                    @php
                        $historyStatus = \App\Enums\PurchaseOrderStatus::tryFrom($history->to_status);
                        $historyColor = $historyStatus?->badge() ?? 'secondary';
                    @endphp
                    <div class="d-flex gap-3 {{ $loop->last ? '' : 'mb-4 pb-4 border-bottom' }}">
                        <span class="symbol symbol-35px bg-light-{{ $historyColor }}"><span class="symbol-label"><i class="ki-outline ki-check text-{{ $historyColor }}"></i></span></span>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap justify-content-between gap-2"><span class="fw-semibold">{{ $historyStatus?->label() ?: ucfirst($history->to_status) }}</span><span class="text-muted fs-8">{{ $history->created_at->format('d/m/Y H:i') }}</span></div>
                            <div class="text-muted fs-8">Oleh {{ $history->actor?->name ?: 'Sistem' }}</div>
                            @if ($history->notes)<div class="mt-1 fs-7">{{ $history->notes }}</div>@endif
                        </div>
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada riwayat" description="Perubahan status pesanan akan tercatat di sini." />
                @endforelse
            </x-metronic.card>

            @if ($purchaseOrder->approvals->isNotEmpty())
                <x-metronic.card title="Catatan Approval" class="mt-5">
                    @foreach ($purchaseOrder->approvals as $approval)
                        <div class="{{ $loop->last ? '' : 'mb-4 pb-4 border-bottom' }}">
                            <div class="d-flex justify-content-between gap-2"><span class="fw-semibold">{{ $approval->approver?->name ?: '-' }}</span><span class="badge badge-light-success">Disetujui</span></div>
                            <div class="text-muted fs-8">{{ $approval->approved_at?->format('d/m/Y H:i') ?: '-' }}</div>
                            @if ($approval->notes)<div class="mt-2 fs-7">{{ $approval->notes }}</div>@endif
                        </div>
                    @endforeach
                </x-metronic.card>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function handleWaSend(event, waUrl, shouldMarkSent) {
        event.preventDefault();
        window.open(waUrl, '_blank');
        if (shouldMarkSent) {
            setTimeout(function() {
                const form = document.getElementById('form-mark-sent');
                if (form) form.submit();
            }, 500);
        }
    }

    function copyWaText(text) {
        navigator.clipboard.writeText(text).then(function() {
            toastr && toastr.success('Teks pesanan berhasil disalin ke clipboard! Silakan tempel di WhatsApp.');
        }, function(err) {
            toastr && toastr.error('Gagal menyalin teks.');
        });
    }
</script>
@endpush
