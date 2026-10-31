@extends('layouts.metronic.app')

@section('title', 'Detail Transfer - ' . config('app.name'))
@section('page_title', 'Detail Transfer dan Timeline')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-transfer-show" title="Panduan Halaman Detail Transfer">
        <x-slot:function>
            <p>Halaman ini menampilkan rincian lengkap satu transfer stok beserta timeline, mutasi, paket, dan aksi yang tersedia. Informasi mencakup sumber, tujuan, item, status, dan progres per tahap (reserve, pick, ship, receive).</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Halaman menampilkan ringkasan transfer dan tabel item dengan setiap tahap qty.</li><li>Mutasi stok terdaftar untuk audit perubahan saldo.</li><li>Timeline mencatat setiap perubahan status transfer.</li><li>Aksi tersedia sesuai role dan status transfer.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Sumber/Tujuan:</strong> gudang asal dan tujuan.</li><li><strong>Tanggal:</strong> tanggal pelaksanaan transfer.</li><li><strong>Status:</strong> progress proses transfer.</li><li><strong>Request Asal:</strong> nomor restock request jika ada.</li><li><strong>Pengirim/Penerima:</strong> user yang menangani.</li><li><strong>Resi/Kendaraan:</strong> info pengiriman.</li><li><strong>Rencana/Pengiriman/Penerimaan:</strong> ringkasan status kuantitas per produk.</li><li><strong>Mutasi Stok:</strong> daftar perubahan saldo akibat transfer.</li><li><strong>Aksi Dokumen:</strong> Approve, Selesaikan, Cancel.</li><li><strong>Timeline:</strong> histori perubahan status.</li><li><strong>Paket dan Bukti:</strong> daftar paket packing.</li><li><strong>Surat Jalan:</strong> cetak bukti pengiriman.</li><li><strong>Packing:</strong> halaman picking/packing.</li><li><strong>Kirim:</strong> halaman konfirmasi pengiriman.</li><li><strong>Terima di Cabang:</strong> halaman penerimaan tujuan.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Setiap aksi mengubah status transfer dan memengaruhi stok. Approve melakukan reserve, Ship mengeluarkan stok sumber, Receive menambah stok tujuan. Cancel memerlukan alasan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Periksa ringkasan sumber, tujuan, dan tanggal.</li><li>Lihat tabel item untuk qty per tahap.</li><li>Periksa mutasi stok yang terbentuk.</li><li>Gunakan aksi yang tersedia sesuai role Anda.</li><li>Catat timeline perubahan status.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Cancel memerlukan alasan yang wajib diisi.</li><li>Ship hanya tersedia setelah Packing selesai.</li><li>Aksi ditampilkan sesuai permission dan status transfer.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>TRF-001 status Shipped. Sumber Gudang Pusat, Tujuan Cabang Bogor. Item: Kopi 20 unit approved, 18 picked, 18 shipped. Timeline: Created → Approved → Packed → Shipped. Klik Terima di Cabang setelah barang tiba.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('warehouse.stock-transfers.print', $transfer) }}" class="btn btn-light-success"><i class="ki-outline ki-printer"></i> Surat Jalan</a>
    @can('pack', $transfer)<a href="{{ route('warehouse.stock-transfers.packing', $transfer) }}" class="btn btn-light-primary">Packing</a>@endcan
    @can('ship', $transfer)<a href="{{ route('warehouse.stock-transfers.ship-form', $transfer) }}" class="btn btn-light-info">Kirim</a>@endcan
    @can('receive', $transfer)<a href="{{ route('retail.stock-transfers.receive-form', $transfer) }}" class="btn btn-primary">Terima di Cabang</a>@endcan
@endsection

@section('content')
    @php
        $stages = [
            ['status' => 'draft', 'label' => 'Rancangan'],
            ['status' => 'pending_approval', 'label' => 'Menunggu Persetujuan'],
            ['status' => 'approved', 'label' => 'Disetujui'],
            ['status' => 'packing', 'label' => 'Pengambilan & Pengemasan'],
            ['status' => 'shipped', 'label' => 'Dikirim'],
            ['status' => 'received', 'label' => 'Penerimaan'],
            ['status' => 'completed', 'label' => 'Selesai'],
        ];
        $stageIndex = match($transfer->status->value) {
            'draft' => 0, 'pending_approval' => 1, 'approved' => 2, 'packing' => 3,
            'shipped' => 4, 'partially_received', 'fully_received' => 5, 'completed' => 6,
            default => -1,
        };
        $hasDamaged = $transfer->items->contains(fn($item) => \App\Support\Decimal::compare((string)$item->quantity_damaged, '0') > 0);
        $hasDiscrepancy = $transfer->items->contains(fn($item) => \App\Support\Decimal::compare($item->unresolvedDiscrepancyQuantity(), '0') > 0);
        $hasTransit = $transfer->items->contains(fn($item) => \App\Support\Decimal::compare($item->inTransitQuantity(), '0') > 0);
        $hasShort = $transfer->items->contains(fn($item) => \App\Support\Decimal::compare((string)$item->quantity_short, '0') > 0);
        $nextStep = match($transfer->status->value) {
            'draft' => ['title' => 'Kirim Sekarang (Dalam Perjalanan)', 'description' => 'Periksa kembali item transfer, lalu kirim langsung ke cabang tujuan tanpa birokrasi persetujuan.', 'action' => 'ship'],
            'pending_approval' => ['title' => 'Periksa dan Setujui Transfer', 'description' => 'Kepala Gudang sumber dapat menyetujui transfer atau langsung mengirim barang.', 'action' => 'approval'],
            'approved' => ['title' => 'Kirim Barang (Dispatch/Ship)', 'description' => 'Barang siap dikirim ke cabang tujuan.', 'action' => 'shipping'],
            'packing' => ['title' => 'Kirim Barang (Dispatch/Ship)', 'description' => 'Barang sudah selesai di-packing dan siap diberangkatkan.', 'action' => 'shipping'],
            'shipped' => ['title' => 'Terima Barang di Lokasi Tujuan', 'description' => 'Barang sedang dalam perjalanan. Petugas cabang tujuan dapat memeriksa dan menerima barang dengan 1-klik.', 'action' => 'receiving'],
            'partially_received' => $hasDiscrepancy
                ? ['title' => 'Selesaikan Selisih Penerimaan', 'description' => 'Ada selisih yang belum dipertanggungjawabkan. Tentukan penyelesaian dan unggah bukti sebelum transfer dapat dilanjutkan.', 'action' => 'discrepancy']
                : ['title' => 'Lanjutkan Penerimaan Barang', 'description' => 'Sebagian barang telah diterima, lanjutkan penerimaan untuk sisa barang.', 'action' => 'receiving'],
            'fully_received' => ['title' => 'Selesaikan Transfer', 'description' => 'Seluruh barang telah diterima. Klik selesaikan transfer untuk mengarsipkan dokumen.', 'action' => 'complete'],
            'completed' => ['title' => 'Transfer Selesai', 'description' => 'Dokumen telah selesai diterima penuh. Tidak ada tindakan lanjutan yang diperlukan.', 'action' => 'finished'],
            'cancelled' => ['title' => 'Transfer Dibatalkan', 'description' => 'Dokumen transfer ini telah dibatalkan.', 'action' => 'cancelled'],
            default => ['title' => 'Periksa Status Transfer', 'description' => 'Status dokumen belum memiliki arahan proses berikutnya.', 'action' => 'none'],
        };
        $approvalBlocked = $approvalStocks->contains(fn($row) => !$row['enough']);
        $heroCardBorder = match($transfer->status->value) {
            'pending_approval' => $approvalBlocked ? 'border-warning bg-light-warning' : 'border-success bg-light-success',
            'shipped' => 'border-primary bg-light-primary',
            'completed' => 'border-success bg-light-success',
            'cancelled' => 'border-danger bg-light-danger',
            default => 'border-primary bg-light-primary',
        };
        $heroIcon = match($transfer->status->value) {
            'draft' => 'ki-send',
            'pending_approval' => 'ki-check-circle',
            'approved' => 'ki-package',
            'packing' => 'ki-delivery',
            'shipped' => 'ki-delivery-3',
            'partially_received' => 'ki-information-2',
            'fully_received' => 'ki-check',
            'completed' => 'ki-verify',
            'cancelled' => 'ki-cross-circle',
            default => 'ki-route',
        };
        $heroIconColor = match($transfer->status->value) {
            'pending_approval' => $approvalBlocked ? 'text-warning' : 'text-success',
            'completed' => 'text-success',
            'cancelled' => 'text-danger',
            default => 'text-primary',
        };
    @endphp

    {{-- 1. KARTU 'AKSI UTAMA SEKARANG' (HERO ACTION CARD) DI PALING ATAS --}}
    <div class="card mb-6 border border-2 border-dashed {{ $heroCardBorder }} shadow-sm">
        <div class="card-body p-6 p-lg-8">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-5">
                <div class="d-flex align-items-start gap-4">
                    <div class="symbol symbol-60px flex-shrink-0">
                        <span class="symbol-label bg-white shadow-sm">
                            <i class="ki-outline {{ $heroIcon }} fs-2tx {{ $heroIconColor }}"></i>
                        </span>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <span class="badge badge-primary text-uppercase fw-bolder px-3 py-1 fs-8">Aksi Utama Sekarang</span>
                            <x-metronic.status-badge :status="$transfer->status" />
                        </div>
                        <h2 class="fs-2 fw-bolder text-gray-900 mb-1">{{ $nextStep['title'] }}</h2>
                        <p class="fs-6 text-gray-700 mb-3">{{ $nextStep['description'] }}</p>

                        <div class="rounded border border-gray-300 bg-white p-3 d-inline-flex flex-wrap align-items-center gap-2 shadow-xs">
                            <span class="text-muted text-uppercase fs-8 fw-bold">Penanggung Jawab Berikutnya:</span>
                            <span class="badge badge-light-primary fw-bold fs-7">{{ $nextResponsibility['role'] }}</span>
                            @if($nextResponsibility['location'])
                                <span class="text-muted fs-7"><i class="ki-outline ki-geolocation me-1"></i>{{ $nextResponsibility['location'] }}</span>
                            @endif
                            @if(!empty($nextResponsibility['users']))
                                <div class="d-flex flex-wrap gap-1 align-items-center ms-lg-2">
                                    @foreach($nextResponsibility['users'] as $responsibleUser)
                                        <span class="badge badge-light-dark"><i class="ki-outline ki-user me-1"></i>{{ $responsibleUser }}</span>
                                    @endforeach
                                </div>
                            @elseif(!in_array($transfer->status->value, ['completed', 'cancelled'], true))
                                <span class="text-warning fs-8 ms-lg-2">(Belum ada user aktif yang ditugaskan)</span>
                            @endif
                        </div>

                        @if($transfer->status->value === 'pending_approval' && $approvalBlocked)
                            <div class="alert alert-danger d-flex align-items-center p-3 mt-3 mb-0">
                                <i class="ki-outline ki-information-5 fs-2 text-danger me-2"></i>
                                <div class="fs-7 text-danger fw-semibold">
                                    <strong>Peringatan Stok Kurang:</strong> Beberapa produk di gudang asal tidak mencukupi untuk dialokasikan. Persetujuan ditangguhkan hingga stok mencukupi.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex-shrink-0 d-flex flex-column align-items-lg-end justify-content-center">
                    @if($transfer->status->value === 'draft')
                        @can('update', $transfer)
                            <form method="POST" action="{{ route('warehouse.stock-transfers.direct-ship', $transfer) }}" onsubmit="return confirm('Kirim transfer ini sekarang? Stok di lokasi asal akan langsung dipotong dan berstatus Dalam Perjalanan.');">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-lg fs-5 fw-bolder px-8 py-4 shadow-sm">
                                    <i class="ki-outline ki-send fs-2 me-2"></i>Kirim Sekarang (Dalam Perjalanan)
                                </button>
                            </form>
                        @else
                            <span class="btn btn-light disabled fs-7 py-3">Menunggu Pembuat Transfer</span>
                        @endcan

                    @elseif($transfer->status->value === 'pending_approval')
                        @can('approve', $transfer)
                            <form method="POST" action="{{ route('warehouse.stock-transfers.approve', $transfer) }}">
                                @csrf
                                @foreach($transfer->items as $item)
                                    <input type="hidden" name="items[{{ $item->id }}][quantity_approved]" value="{{ qty_input($item->quantity_approved) }}">
                                @endforeach
                                <button type="submit" class="btn btn-success btn-lg fs-5 fw-bolder px-8 py-4 shadow-sm" @disabled($approvalBlocked)>
                                    <i class="ki-outline ki-check-circle fs-2 me-2"></i>Setujui Transfer
                                </button>
                            </form>
                        @else
                            <span class="btn btn-light disabled fs-7 py-3">Menunggu Kepala Gudang</span>
                        @endcan

                    @elseif($transfer->status->value === 'approved')
                        @can('pack', $transfer)
                            <a href="{{ route('warehouse.stock-transfers.packing', $transfer) }}" class="btn btn-primary btn-lg fs-5 fw-bolder px-8 py-4 shadow-sm">
                                <i class="ki-outline ki-package fs-2 me-2"></i>Buka Halaman Packing
                            </a>
                        @else
                            <span class="btn btn-light disabled fs-7 py-3">Menunggu Petugas Gudang</span>
                        @endcan

                    @elseif($transfer->status->value === 'packing')
                        @can('ship', $transfer)
                            <div class="d-flex flex-column flex-sm-row gap-2">
                                <a href="{{ route('warehouse.stock-transfers.ship-form', $transfer) }}" class="btn btn-primary btn-lg fs-5 fw-bolder px-6 py-4 shadow-sm">
                                    <i class="ki-outline ki-delivery fs-2 me-2"></i>Buka Halaman Pengiriman
                                </a>
                                @can('pack', $transfer)
                                    <a href="{{ route('warehouse.stock-transfers.packing', $transfer) }}" class="btn btn-light-primary fs-6 px-4 py-3 align-self-center">
                                        Perbarui Packing
                                    </a>
                                @endcan
                            </div>
                        @else
                            <span class="btn btn-light disabled fs-7 py-3">Menunggu Petugas Pengiriman</span>
                        @endcan

                    @elseif($transfer->status->value === 'shipped')
                        @can('receive', $transfer)
                            <a href="{{ route('retail.stock-transfers.receive-form', $transfer) }}" class="btn btn-primary btn-lg fs-5 fw-bolder px-8 py-4 shadow-sm">
                                <i class="ki-outline ki-delivery-3 fs-2 me-2"></i>Proses Terima Barang
                            </a>
                        @else
                            <span class="btn btn-light disabled fs-7 py-3">Menunggu Petugas Cabang Tujuan</span>
                        @endcan

                    @elseif($transfer->status->value === 'partially_received')
                        @if($hasDiscrepancy)
                            @can('resolveDiscrepancy', $transfer)
                                <a href="#penyelesaian-selisih" class="btn btn-warning btn-lg fs-5 fw-bolder px-8 py-4 shadow-sm">
                                    <i class="ki-outline ki-information-2 fs-2 me-2"></i>Selesaikan Selisih
                                </a>
                            @else
                                <span class="btn btn-light disabled fs-7 py-3">Menunggu Penanggung Jawab Selisih</span>
                            @endcan
                        @else
                            @can('receive', $transfer)
                                <a href="{{ route('retail.stock-transfers.receive-form', $transfer) }}" class="btn btn-primary btn-lg fs-5 fw-bolder px-8 py-4 shadow-sm">
                                    <i class="ki-outline ki-delivery-3 fs-2 me-2"></i>Lanjutkan Penerimaan Barang
                                </a>
                            @else
                                <span class="btn btn-light disabled fs-7 py-3">Menunggu Petugas Cabang Tujuan</span>
                            @endcan
                        @endif

                    @elseif($transfer->status->value === 'fully_received')
                        @can('complete', $transfer)
                            <form method="POST" action="{{ route('warehouse.stock-transfers.complete', $transfer) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-lg fs-5 fw-bolder px-8 py-4 shadow-sm">
                                    <i class="ki-outline ki-check fs-2 me-2"></i>Selesaikan Transfer
                                </button>
                            </form>
                        @else
                            <span class="btn btn-light disabled fs-7 py-3">Menunggu Penanggung Jawab Tujuan</span>
                        @endcan

                    @elseif($transfer->status->value === 'completed')
                        <div class="d-flex flex-column align-items-lg-end gap-2">
                            <span class="badge badge-light-success fs-6 py-2 px-3 fw-bold"><i class="ki-outline ki-verify fs-4 me-1 text-success"></i>Transfer Selesai</span>
                            <a href="{{ route('warehouse.stock-transfers.print', $transfer) }}" class="btn btn-light-success btn-lg fs-6 fw-bold px-6 py-3">
                                <i class="ki-outline ki-printer fs-3 me-2"></i>Cetak Surat Jalan
                            </a>
                        </div>

                    @elseif($transfer->status->value === 'cancelled')
                        <div class="d-flex flex-column align-items-lg-end gap-2">
                            <span class="badge badge-light-danger fs-6 py-2 px-3 fw-bold"><i class="ki-outline ki-cross fs-4 me-1 text-danger"></i>Dokumen Dibatalkan</span>
                            <a href="{{ route('warehouse.stock-transfers.create') }}" class="btn btn-primary btn-lg fs-6 fw-bold px-6 py-3">
                                <i class="ki-outline ki-plus fs-3 me-2"></i>Buat Transfer Baru
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- 2. PERJALANAN TRANSFER (STAGE PROGRESS) --}}
    <x-metronic.card title="Perjalanan Transfer" class="mb-5">
        <div class="d-flex flex-column flex-lg-row align-items-stretch gap-2">
            @foreach($stages as $index => $stage)
                @php
                    $cancelled = $transfer->status->value === 'cancelled';
                    $complete = !$cancelled && $index < $stageIndex;
                    $active = !$cancelled && $index === $stageIndex;
                @endphp
                <div class="flex-fill d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 {{ $cancelled ? 'bg-light-danger text-danger' : ($complete ? 'bg-success text-white' : ($active ? 'bg-primary text-white' : 'bg-light text-muted')) }}" style="width:34px;height:34px">
                        <i class="ki-outline {{ $cancelled ? 'ki-cross' : ($complete ? 'ki-check' : 'ki-right') }} fs-5"></i>
                    </div>
                    <div class="fs-8 fw-semibold {{ $active ? 'text-primary' : ($complete ? 'text-success' : 'text-muted') }}">{{ $stage['label'] }}</div>
                    @if(!$loop->last)<div class="d-none d-lg-block flex-grow-1 border-top border-2 {{ $complete ? 'border-success' : 'border-gray-300' }}"></div>@endif
                </div>
            @endforeach
        </div>
        @if($transfer->status->value === 'cancelled')<div class="alert alert-danger mt-4 mb-0">Dokumen ini telah dibatalkan.</div>@endif
    </x-metronic.card>

    {{-- 3. PERHATIAN OPERASIONAL (JIKA ADA PERINGATAN) --}}
    @if($hasDamaged || $hasDiscrepancy || $hasTransit || $hasShort)
        <div class="alert alert-warning d-flex align-items-center p-4 mb-5">
            <i class="ki-outline ki-information-2 fs-2x text-warning me-3"></i>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-bold me-2">Perhatian Operasional:</span>
                @if($hasDamaged)<span class="badge badge-light-danger fs-8">Ada barang rusak</span>@endif
                @if($hasDiscrepancy)<span class="badge badge-light-danger fs-8">Ada selisih pengiriman</span>@endif
                @if($hasTransit)<span class="badge badge-light-warning fs-8">Masih ada barang dalam perjalanan</span>@endif
                @if($hasShort)<span class="badge badge-light-warning fs-8">Jumlah diambil kurang dari yang disetujui</span>@endif
            </div>
        </div>
    @endif

    {{-- 4. KONTEN UTAMA & SIDEBAR --}}
    <div class="row g-5">
        <div class="col-lg-8">
            <x-metronic.card title="{{ $transfer->number }}">
                <div class="row g-4 mb-5">
                    <div class="col-md-3"><div class="text-muted fs-8 text-uppercase fw-semibold">Sumber</div><div class="fw-bold text-gray-900 fs-7">{{ $transfer->sourceWorkLocation?->name }}</div></div>
                    <div class="col-md-3"><div class="text-muted fs-8 text-uppercase fw-semibold">Tujuan</div><div class="fw-bold text-gray-900 fs-7">{{ $transfer->destinationWorkLocation?->name }}</div></div>
                    <div class="col-md-3"><div class="text-muted fs-8 text-uppercase fw-semibold">Tanggal</div><div class="fw-bold text-gray-900 fs-7">{{ $transfer->transfer_date?->format('d/m/Y') }}</div></div>
                    <div class="col-md-3"><div class="text-muted fs-8 text-uppercase fw-semibold">Status</div><x-metronic.status-badge :status="$transfer->status" /></div>
                    <div class="col-md-3"><div class="text-muted fs-8 text-uppercase fw-semibold">Sumber Permintaan</div><div class="fs-7">@if($transfer->restockRequest)<a href="{{ route('retail.restock-requests.show', $transfer->restockRequest) }}" class="fw-bold text-primary">{{ $transfer->restockRequest->number }}</a>@else-@endif</div></div>
                    <div class="col-md-3"><div class="text-muted fs-8 text-uppercase fw-semibold">Penanggung Jawab Pengirim</div><div class="fs-7 text-gray-800 fw-bold">{{ $transfer->shipper?->name ?: ($transfer->requester?->name ?: '-') }}</div></div>
                    <div class="col-md-3"><div class="text-muted fs-8 text-uppercase fw-semibold">Penanggung Jawab Penerima</div><div class="fs-7 text-gray-800 fw-bold">{{ $transfer->receiver?->name ?: 'Menunggu Penerimaan' }}</div></div>
                    <div class="col-md-3"><div class="text-muted fs-8 text-uppercase fw-semibold">Resi/Kendaraan</div><div class="fs-7 text-gray-800">{{ $transfer->tracking_number ?: $transfer->vehicle_number ?: '-' }}</div></div>
                </div>

                {{-- TABEL ITEM DENGAN 4 KOLOM RINGKAS & INFORMATIF --}}
                <div class="table-responsive">
                    <table class="table table-row-dashed table-hover align-middle gs-4 gy-4">
                        <thead class="bg-light">
                            <tr class="text-muted fw-bold text-uppercase fs-7 border-bottom">
                                <th class="min-w-200px">Produk & Satuan</th>
                                <th class="min-w-125px">Rencana (Diminta / Disetujui)</th>
                                <th class="min-w-150px">Pengiriman (Diambil / Dikirim)</th>
                                <th class="min-w-200px">Penerimaan & Status</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            @foreach($transfer->items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-900 fw-bold fs-6">{{ $item->product_sku_snapshot }}</span>
                                            <span class="text-gray-700 fs-7">{{ $item->product_name_snapshot }}</span>
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <span class="badge badge-light-secondary fs-8">{{ $item->unit_name_snapshot ?? ($item->product?->baseUnit?->name ?? 'Unit') }}</span>
                                                @if($item->sourceWarehouseLocation || $item->destinationWarehouseLocation)
                                                    <span class="text-muted fs-8">
                                                        <i class="ki-outline ki-geolocation fs-8 me-1"></i>{{ $item->sourceWarehouseLocation?->full_code ?? 'Auto' }} &rarr; {{ $item->destinationWarehouseLocation?->full_code ?? 'Auto' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fs-6 fw-bold text-gray-900">{{ qty($item->quantity_approved) }} <span class="fs-8 text-muted fw-normal">disetujui</span></span>
                                            @if(\App\Support\Decimal::compare((string)$item->quantity_requested, (string)$item->quantity_approved) !== 0)
                                                <span class="text-muted fs-8"><del>Diminta: {{ qty($item->quantity_requested) }}</del></span>
                                            @else
                                                <span class="text-muted fs-8">Diminta: {{ qty($item->quantity_requested) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="text-muted fs-7">Dikirim:</span>
                                                <span class="fw-bold fs-6 {{ \App\Support\Decimal::compare((string)$item->quantity_shipped, '0') > 0 ? 'text-primary' : 'text-gray-400' }}">{{ qty($item->quantity_shipped) }}</span>
                                            </div>
                                            <span class="text-muted fs-8">Diambil: {{ qty($item->quantity_picked) }}</span>
                                            @if(\App\Support\Decimal::compare((string)$item->quantity_short, '0') > 0)
                                                <span class="badge badge-light-warning fs-8 mt-1 align-self-start">
                                                    <i class="ki-outline ki-information-2 me-1"></i>Kurang: {{ qty($item->quantity_short) }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $shipped = (string)$item->quantity_shipped;
                                            $received = (string)$item->quantity_received;
                                            $damaged = (string)$item->quantity_damaged;
                                            $unresolved = $item->unresolvedDiscrepancyQuantity();
                                            $inTransit = $item->inTransitQuantity();
                                            $isShipped = \App\Support\Decimal::compare($shipped, '0') > 0;
                                            $isPerfect = $isShipped
                                                && \App\Support\Decimal::compare($received, $shipped) === 0
                                                && \App\Support\Decimal::compare($damaged, '0') === 0
                                                && \App\Support\Decimal::compare($unresolved, '0') === 0;
                                        @endphp

                                        @if(!$isShipped)
                                            <span class="text-muted fs-8 fst-italic">Belum ada pengiriman</span>
                                        @else
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                                    <span class="badge badge-light-primary fw-bold fs-8">Dikirim: {{ qty($shipped) }}</span>
                                                    <i class="ki-outline ki-arrow-right fs-8 text-muted"></i>
                                                    <span class="badge badge-light-success fw-bold fs-8">Diterima: {{ qty($received) }}</span>
                                                </div>
                                                <div class="d-flex flex-wrap gap-1 mt-1">
                                                    @if(\App\Support\Decimal::compare($damaged, '0') > 0)
                                                        <span class="badge badge-light-danger fs-8">
                                                            <i class="ki-outline ki-cross-circle me-1"></i>{{ qty($damaged) }} Rusak
                                                        </span>
                                                    @endif
                                                    @if(\App\Support\Decimal::compare($unresolved, '0') > 0)
                                                        <span class="badge badge-light-danger fs-8" title="Dicatat {{ qty($item->quantity_discrepancy) }}, diselesaikan {{ qty($item->resolvedDiscrepancyQuantity()) }}">
                                                            <i class="ki-outline ki-information me-1"></i>{{ qty($unresolved) }} Selisih
                                                        </span>
                                                    @endif
                                                    @if(\App\Support\Decimal::compare($inTransit, '0') > 0)
                                                        <span class="badge badge-light-warning fs-8">
                                                            <i class="ki-outline ki-delivery me-1"></i>{{ qty($inTransit) }} In-Transit
                                                        </span>
                                                    @endif
                                                    @if($isPerfect)
                                                        <span class="badge badge-success fs-8">
                                                            <i class="ki-outline ki-check fs-8 me-1 text-white"></i>Sempurna
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            @if($transfer->discrepancyResolutions->isNotEmpty())
                <x-metronic.card title="Riwayat Penyelesaian Selisih" class="mt-5">
                    <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr><th>Produk</th><th>Penyelesaian</th><th>Jumlah</th><th>Pelaksana</th><th>Catatan</th><th>Referensi</th></tr></thead><tbody>
                        @foreach($transfer->discrepancyResolutions as $resolution)
                            <tr><td>{{ $resolution->item?->product_sku_snapshot }}</td><td>{{ $resolution->resolution_type->label() }}</td><td>{{ qty($resolution->quantity) }}</td><td>{{ $resolution->resolver?->name }}<div class="text-muted fs-8">{{ $resolution->resolved_at?->format('d/m/Y H:i') }}</div></td><td>{{ $resolution->notes }}</td><td>{{ $resolution->inventoryLoss?->number ?: '-' }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                </x-metronic.card>
            @endif

            <x-metronic.card title="Mutasi Stok" class="mt-5">
                <div class="table-responsive"><table class="table align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Jenis</th><th>On Hand</th><th>Reserved</th><th>Lokasi</th></tr></thead><tbody>@forelse($transfer->stockMutations as $mutation)<tr><td>{{ $mutation->occurred_at?->format('d/m/Y H:i') }}</td><td>{{ $mutation->product?->sku }}</td><td>{{ $mutation->mutation_type->label() }}</td><td>{{ qty($mutation->quantity_on_hand_change) }}</td><td>{{ qty($mutation->quantity_reserved_change) }}</td><td>{{ $mutation->workLocation?->name }}</td></tr>@empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada mutasi" description="Reserve, ship, dan receive akan membuat mutasi stok." /></td></tr>@endforelse</tbody></table></div>
            </x-metronic.card>
        </div>

        <div class="col-lg-4">
            <x-metronic.card title="Aksi Dokumen" id="aksi-dokumen">
                <div class="d-grid gap-3">
                    @can('update', $transfer)
                        @if($transfer->status === \App\Enums\StockTransferStatus::DRAFT)
                            <form method="POST" action="{{ route('warehouse.stock-transfers.submit', $transfer) }}" onsubmit="return confirm('Ajukan transfer ini untuk persetujuan Kepala Gudang?');">
                                @csrf
                                <button class="btn btn-primary w-100"><i class="ki-outline ki-send"></i>Ajukan untuk Persetujuan</button>
                            </form>
                        @endif
                    @endcan
                    @can('approve', $transfer)
                        @php($approvalBlocked = $approvalStocks->contains(fn($row) => !$row['enough']))
                        <div class="border rounded p-3">
                            <div class="fw-bold mb-2">Ketersediaan stok sumber</div>
                            @foreach($transfer->items as $item)@php($balance = $approvalStocks->get($item->id))
                                <div class="fs-8 py-2 border-bottom"><span class="fw-semibold">{{ $item->product_sku_snapshot }}</span>: tersedia {{ qty($balance['available']) }} / perlu {{ qty($balance['needed']) }} <span class="badge badge-light-{{ $balance['enough'] ? 'success' : 'danger' }}">{{ $balance['enough'] ? 'Cukup' : 'Kurang '.qty(\App\Support\Decimal::sub($balance['needed'], $balance['available'])) }}</span><div class="text-muted">On hand {{ qty($balance['on_hand']) }}, reserved {{ qty($balance['reserved']) }}, rusak {{ qty($balance['damaged']) }}</div></div>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('warehouse.stock-transfers.approve', $transfer) }}">@csrf @foreach($transfer->items as $item)<input type="hidden" name="items[{{ $item->id }}][quantity_approved]" value="{{ qty_input($item->quantity_approved) }}">@endforeach<button class="btn btn-success w-100" @disabled($approvalBlocked)>Setujui & Alokasikan Stok</button>@if($approvalBlocked)<div class="text-danger fs-8 mt-2">Approval dinonaktifkan sampai stok sumber mencukupi.</div>@endif</form>
                    @endcan
                    @can('complete', $transfer)<form method="POST" action="{{ route('warehouse.stock-transfers.complete', $transfer) }}">@csrf<button class="btn btn-primary">Selesaikan Transfer</button></form>@endcan
                    @can('cancel', $transfer)<form method="POST" action="{{ route('warehouse.stock-transfers.cancel', $transfer) }}">@csrf<input name="reason" class="form-control mb-2" placeholder="Alasan pembatalan" required><button class="btn btn-light-danger">Batalkan Transfer</button></form>@endcan
                </div>
            </x-metronic.card>

            @if($hasDiscrepancy)
                <x-metronic.card title="Penyelesaian Selisih" class="mt-5" id="penyelesaian-selisih">
                    @can('resolveDiscrepancy', $transfer)
                        @foreach($transfer->items as $item)
                            @if(\App\Support\Decimal::compare($item->unresolvedDiscrepancyQuantity(), '0') > 0)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('warehouse.stock-transfers.resolve-discrepancy', $transfer) }}" class="border rounded p-4 mb-4">
                                    @csrf
                                    <input type="hidden" name="stock_transfer_item_id" value="{{ $item->id }}">
                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <div class="fw-bold mb-1">{{ $item->product_sku_snapshot }} — {{ $item->product_name_snapshot }}</div>
                                    <div class="text-muted fs-8 mb-3">Belum diselesaikan: {{ qty($item->unresolvedDiscrepancyQuantity()) }}</div>
                                    <label class="form-label required">Jenis Penyelesaian</label>
                                    <select name="resolution_type" class="form-select form-select-solid mb-3" required>
                                        @foreach(\App\Enums\StockTransferDiscrepancyResolutionType::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select>
                                    <label class="form-label required">Jumlah</label>
                                    <input type="number" step="1" min="1" max="{{ qty_input($item->unresolvedDiscrepancyQuantity()) }}" name="quantity" class="form-control form-control-solid mb-3" required>
                                    <label class="form-label required">Alasan dan Catatan</label>
                                    <textarea name="notes" class="form-control form-control-solid mb-3" rows="3" required></textarea>
                                    <label class="form-label">Bukti</label>
                                    <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="form-control form-control-solid mb-3">
                                    <button class="btn btn-warning w-100">Simpan Penyelesaian</button>
                                </form>
                            @endif
                        @endforeach
                    @else
                        <div class="alert alert-light-info mb-0">Anda dapat melihat selisih, tetapi tidak memiliki izin menyelesaikannya.</div>
                    @endcan
                </x-metronic.card>
            @endif

            <x-metronic.card title="Timeline" class="mt-5">
                @forelse($timeline as $history)@php($fromStatus = $history->from_status ? \App\Enums\StockTransferStatus::tryFrom($history->from_status)?->label() : 'Awal')@php($toStatus = \App\Enums\StockTransferStatus::tryFrom($history->to_status)?->label() ?? $history->to_status)<div class="border-start border-3 ps-4 mb-4"><div class="fw-bold">{{ $fromStatus }} → {{ $toStatus }}</div><div class="text-muted">{{ $history->created_at->format('d/m/Y H:i') }} oleh {{ $history->actor?->name ?: '-' }}</div><div>{{ $history->notes ?: '-' }}</div></div>@empty<x-metronic.empty-state title="Belum ada riwayat status" description="Perubahan status transfer akan tercatat di sini." />@endforelse
            </x-metronic.card>

            <x-metronic.card title="Paket dan Bukti" class="mt-5">
                @forelse($transfer->packages as $package)<div class="mb-3"><div class="fw-bold">{{ $package->package_no }}</div><div class="text-muted">Checker {{ $package->checker?->name ?: '-' }}</div><div>{{ $package->notes ?: '-' }}</div></div>@empty<div class="text-muted">Belum ada paket.</div>@endforelse
            </x-metronic.card>
        </div>
    </div>
@endsection
