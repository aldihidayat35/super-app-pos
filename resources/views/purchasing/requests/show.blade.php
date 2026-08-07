@extends('layouts.metronic.app')

@section('title', 'Permintaan ' . $purchaseRequest->number . ' - ' . config('app.name'))
@section('page_title', 'Detail Permintaan Pembelian')

@section('toolbar_actions')
    <a href="{{ route('purchasing.requests.index') }}" class="btn btn-light">
        <i class="ki-outline ki-arrow-left fs-5 me-2"></i>Kembali
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="purchasing-request-show" title="Panduan Proses Permintaan">
        <x-slot:function><p>Halaman ini memisahkan proses review internal, pemilihan supplier, dan pembuatan Purchase Order sesuai status serta izin pengguna.</p></x-slot:function>
        <x-slot:workflow><ol><li>Tinjau kebutuhan barang.</li><li>Setujui atau tolak permintaan.</li><li>Setelah disetujui, Purchasing memilih supplier dan membuat PO draft.</li></ol></x-slot:workflow>
        <x-slot:warnings><p>Supplier baru tersimpan ketika proses konversi berhasil karena Purchase Request existing tidak memiliki field supplier terpisah.</p></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    @php
        $status = $purchaseRequest->status;
        $isRejected = $status === \App\Enums\PurchaseRequestStatus::REJECTED;
        $isApproved = in_array($status, [\App\Enums\PurchaseRequestStatus::APPROVED, \App\Enums\PurchaseRequestStatus::CONVERTED], true);
        $isConverted = $status === \App\Enums\PurchaseRequestStatus::CONVERTED;
        $steps = [
            ['label' => 'Diajukan', 'description' => 'Kebutuhan barang dicatat dan dikirim untuk review.', 'state' => $status === \App\Enums\PurchaseRequestStatus::DRAFT ? 'pending' : 'done'],
            ['label' => $isRejected ? 'Ditolak' : 'Persetujuan', 'description' => $isRejected ? 'Permintaan ditolak dan proses berhenti.' : 'Ditinjau oleh pengguna dengan izin approval.', 'state' => $isRejected ? 'failed' : ($status === \App\Enums\PurchaseRequestStatus::SUBMITTED ? 'current' : ($isApproved ? 'done' : 'pending'))],
            ['label' => 'Pilih Supplier', 'description' => 'Purchasing menentukan pemasok tujuan PO.', 'state' => $status === \App\Enums\PurchaseRequestStatus::APPROVED ? 'current' : ($isConverted ? 'done' : 'pending')],
            ['label' => 'PO Dibuat', 'description' => 'Item permintaan disalin menjadi Purchase Order draft.', 'state' => $isConverted ? 'done' : 'pending'],
        ];
        [$holder, $nextAction] = match ($status) {
            \App\Enums\PurchaseRequestStatus::DRAFT => ['Pemohon internal', 'Permintaan perlu diajukan sebelum dapat ditinjau.'],
            \App\Enums\PurchaseRequestStatus::SUBMITTED => ['Pengguna dengan izin purchase_orders.approve', 'Periksa kebutuhan lalu setujui atau tolak permintaan.'],
            \App\Enums\PurchaseRequestStatus::APPROVED => ['Purchasing dengan izin purchase_orders.create', 'Pilih supplier aktif lalu buat Purchase Order.'],
            \App\Enums\PurchaseRequestStatus::REJECTED => ['Tidak ada tindakan lanjutan', 'Permintaan ditolak. Buat permintaan baru jika kebutuhan masih ada.'],
            \App\Enums\PurchaseRequestStatus::CONVERTED => ['Proses berlanjut pada Purchase Order', 'Buka PO terkait untuk melanjutkan proses pembelian.'],
        };
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
        <div>
            <div class="d-flex align-items-center gap-3 mb-1">
                <h2 class="fs-3 fw-bold mb-0">{{ $purchaseRequest->number }}</h2>
                <x-metronic.status-badge :status="$purchaseRequest->status" />
            </div>
            <div class="text-muted fs-7">Diajukan oleh {{ $purchaseRequest->requester?->name ?: '-' }} pada {{ $purchaseRequest->submitted_at?->format('d/m/Y H:i') ?: '-' }}</div>
        </div>
        @if ($purchaseRequest->convertedPurchaseOrder)
            @can('view', $purchaseRequest->convertedPurchaseOrder)
                <a href="{{ route('purchasing.purchase-orders.show', $purchaseRequest->convertedPurchaseOrder) }}" class="btn btn-light-success">
                    <i class="ki-outline ki-document fs-5 me-2"></i>{{ $purchaseRequest->convertedPurchaseOrder->number }}
                </a>
            @endcan
        @endif
    </div>

    <x-metronic.card title="Posisi Proses">
        <div class="row g-3">
            @foreach ($steps as $step)
                @php
                    $stepColor = match ($step['state']) {'done' => 'success', 'current' => 'primary', 'failed' => 'danger', default => 'secondary'};
                    $stepIcon = match ($step['state']) {'done' => 'ki-check', 'current' => 'ki-right', 'failed' => 'ki-cross', default => 'ki-minus'};
                @endphp
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="border rounded p-4 h-100 {{ $step['state'] === 'current' ? 'border-primary bg-light-primary' : '' }}">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <span class="symbol symbol-35px bg-light-{{ $stepColor }}"><span class="symbol-label"><i class="ki-outline {{ $stepIcon }} text-{{ $stepColor }}"></i></span></span>
                            <span class="fw-bold">{{ $loop->iteration }}. {{ $step['label'] }}</span>
                        </div>
                        <div class="text-muted fs-8">{{ $step['description'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="row g-4 mt-2 pt-4 border-top">
            <div class="col-md-6"><div class="text-muted fs-8 mb-1">PIHAK YANG MEMEGANG PROSES</div><div class="fw-semibold">{{ $holder }}</div></div>
            <div class="col-md-6"><div class="text-muted fs-8 mb-1">LANGKAH BERIKUTNYA</div><div class="fw-semibold">{{ $nextAction }}</div></div>
        </div>
    </x-metronic.card>

    <div class="row g-5 mt-0">
        <div class="col-xl-8">
            <x-metronic.card title="Kebutuhan Pembelian">
                <div class="row g-4 mb-5">
                    <div class="col-md-4"><div class="text-muted fs-8 mb-1">GUDANG TUJUAN</div><div class="fw-semibold">{{ $purchaseRequest->warehouse?->code }} - {{ $purchaseRequest->warehouse?->name }}</div><div class="text-muted fs-8">Gudang yang membutuhkan dan akan menerima barang.</div></div>
                    <div class="col-md-3"><div class="text-muted fs-8 mb-1">PRIORITAS</div><div class="fw-semibold">{{ ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Mendesak'][$purchaseRequest->priority] ?? ucfirst($purchaseRequest->priority) }}</div></div>
                    <div class="col-md-5"><div class="text-muted fs-8 mb-1">ALASAN PERMINTAAN</div><div class="fw-semibold">{{ $purchaseRequest->reason }}</div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Satuan</th><th class="text-end">Jumlah Dibutuhkan</th><th>Catatan</th></tr></thead>
                        <tbody>
                            @foreach ($purchaseRequest->items as $item)
                                <tr>
                                    <td><span class="fw-semibold">{{ $item->product?->name }}</span><div class="text-muted fs-8">{{ $item->product?->sku }}</div></td>
                                    <td>{{ $item->unit?->name ?: $item->product?->baseUnit?->name ?: '-' }}</td>
                                    <td class="text-end fw-semibold">{{ qty($item->quantity) }}</td>
                                    <td>{{ $item->reason ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Riwayat Status" class="mt-5">
                @forelse ($purchaseRequest->statusHistories as $history)
                    <div class="d-flex gap-3 {{ $loop->last ? '' : 'mb-4 pb-4 border-bottom' }}">
                        <span class="symbol symbol-35px bg-light-primary"><span class="symbol-label"><i class="ki-outline ki-check text-primary"></i></span></span>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap justify-content-between gap-2"><span class="fw-semibold">{{ \App\Enums\PurchaseRequestStatus::tryFrom($history->to_status)?->label() ?: ucfirst($history->to_status) }}</span><span class="text-muted fs-8">{{ $history->created_at->format('d/m/Y H:i') }}</span></div>
                            <div class="text-muted fs-8">Oleh {{ $history->actor?->name ?: 'Sistem' }}</div>
                            @if ($history->notes)<div class="mt-1">{{ $history->notes }}</div>@endif
                        </div>
                    </div>
                @empty
                    <x-metronic.empty-state title="Belum ada riwayat" description="Perubahan status akan tercatat di bagian ini." />
                @endforelse
            </x-metronic.card>
        </div>

        <div class="col-xl-4">
            <x-metronic.card title="Aksi yang Tersedia untuk Anda">
                @if ($status === \App\Enums\PurchaseRequestStatus::SUBMITTED)
                    <div class="alert alert-light-warning fs-7">Permintaan sedang menunggu pengguna dengan izin approval untuk mengambil keputusan.</div>
                    @can('approve', $purchaseRequest)
                        <div class="mb-5">
                            <div class="fw-bold mb-1">Tahap 1 - Review Permintaan</div>
                            <div class="text-muted fs-8 mb-3">Pastikan gudang, alasan, produk, dan jumlah sudah sesuai.</div>
                            <form method="POST" action="{{ route('purchasing.requests.approve', $purchaseRequest) }}">
                                @csrf
                                <button class="btn btn-success w-100"><i class="ki-outline ki-check fs-5 me-2"></i>Setujui Permintaan</button>
                            </form>
                        </div>
                        <div class="border-top pt-5">
                            <form method="POST" action="{{ route('purchasing.requests.reject', $purchaseRequest) }}">
                                @csrf
                                <x-metronic.form-group name="reason" label="Alasan Penolakan" required help="Alasan disimpan pada riwayat dokumen agar pemohon memahami keputusan.">
                                    <textarea name="reason" rows="3" class="form-control form-control-solid" required>{{ old('reason') }}</textarea>
                                </x-metronic.form-group>
                                <button class="btn btn-light-danger w-100"><i class="ki-outline ki-cross fs-5 me-2"></i>Tolak Permintaan</button>
                            </form>
                        </div>
                    @else
                        <div class="text-muted fs-7">Anda dapat melihat proses, tetapi tidak memiliki izin untuk menyetujui atau menolak permintaan ini.</div>
                    @endcan
                @elseif ($status === \App\Enums\PurchaseRequestStatus::APPROVED)
                    <div class="alert alert-light-success fs-7">Permintaan sudah disetujui. Purchasing dapat memilih supplier dan membuat Purchase Order draft.</div>
                    @can('convert', $purchaseRequest)
                        <form method="POST" action="{{ route('purchasing.requests.convert', $purchaseRequest) }}">
                            @csrf
                            <div class="fw-bold mb-1">Tahap 2 - Supplier untuk Purchase Order</div>
                            <x-metronic.form-group name="supplier_id" label="Pilih Supplier" required help="Pilih supplier aktif yang akan menerima Purchase Order ini. Sistem tidak memilih otomatis karena satu produk dapat memiliki beberapa supplier.">
                                <select name="supplier_id" class="form-select form-select-solid" required>
                                    <option value="">Pilih supplier tujuan</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->code }} - {{ $supplier->name }} (termin {{ $supplier->payment_term_days }} hari)</option>
                                    @endforeach
                                </select>
                            </x-metronic.form-group>
                            <div class="border-top pt-5 mt-2">
                                <div class="fw-bold mb-1">Tahap 3 - Buat Purchase Order</div>
                                <div class="text-muted fs-8 mb-3">PO draft akan dibuat dari gudang dan item pada permintaan ini. Harga dapat dilengkapi pada PO sebelum diajukan.</div>
                                <button class="btn btn-primary w-100"><i class="ki-outline ki-document fs-5 me-2"></i>Buat Purchase Order</button>
                            </div>
                        </form>
                    @else
                        <div class="text-muted fs-7">Anda tidak memiliki izin untuk membuat Purchase Order. Proses berikutnya ditangani oleh Purchasing.</div>
                    @endcan
                @elseif ($status === \App\Enums\PurchaseRequestStatus::CONVERTED)
                    <div class="alert alert-light-success fs-7">Permintaan telah dikonversi. Seluruh proses berikutnya dilakukan pada Purchase Order terkait.</div>
                    @if ($purchaseRequest->convertedPurchaseOrder)
                        @can('view', $purchaseRequest->convertedPurchaseOrder)
                            <a href="{{ route('purchasing.purchase-orders.show', $purchaseRequest->convertedPurchaseOrder) }}" class="btn btn-primary w-100">Buka {{ $purchaseRequest->convertedPurchaseOrder->number }}</a>
                        @endcan
                    @endif
                @elseif ($status === \App\Enums\PurchaseRequestStatus::REJECTED)
                    <div class="alert alert-light-danger fs-7 mb-0">Permintaan ditolak. Tidak ada aksi lanjutan pada dokumen ini.</div>
                @else
                    <div class="text-muted fs-7">Belum ada aksi yang tersedia untuk status ini.</div>
                @endif
            </x-metronic.card>

            <x-metronic.card title="Peran dalam Proses" class="mt-5">
                <div class="mb-4"><div class="fw-semibold">Staf Internal / Gudang</div><div class="text-muted fs-8">Membuat permintaan berdasarkan kebutuhan stok dan operasional gudang.</div></div>
                <div class="mb-4"><div class="fw-semibold">Approver</div><div class="text-muted fs-8">Pengguna dengan izin <code>purchase_orders.approve</code> yang meninjau dan memutuskan permintaan.</div></div>
                <div><div class="fw-semibold">Purchasing</div><div class="text-muted fs-8">Pengguna dengan izin <code>purchase_orders.create</code> yang memilih supplier dan membuat PO.</div></div>
            </x-metronic.card>
        </div>
    </div>
@endsection
