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
            <ul><li><strong>Sumber/Tujuan:</strong> gudang asal dan tujuan.</li><li><strong>Tanggal:</strong> tanggal pelaksanaan transfer.</li><li><strong>Status:</strong> progress proses transfer.</li><li><strong>Request Asal:</strong> nomor restock request jika ada.</li><li><strong>Pengirim/Penerima:</strong> user yang menangani.</li><li><strong>Resi/Kendaraan:</strong> info pengiriman.</li><li><strong>Request/Approved/Picked/Short/Shipped/Received/Damaged/Discrepancy:</strong> qty per tahap item.</li><li><strong>In Transit:</strong> qty masih dalam perjalanan.</li><li><strong>Mutasi Stok:</strong> daftar perubahan saldo akibat transfer.</li><li><strong>Aksi Dokumen:</strong> Approve, Selesaikan, Cancel.</li><li><strong>Timeline:</strong> histori perubahan status.</li><li><strong>Paket dan Bukti:</strong> daftar paket packing.</li><li><strong>Surat Jalan:</strong> cetak bukti pengiriman.</li><li><strong>Packing:</strong> halaman picking/packing.</li><li><strong>Kirim:</strong> halaman konfirmasi pengiriman.</li><li><strong>Terima di Cabang:</strong> halaman penerimaan tujuan.</li></ul>
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
            'draft' => ['title' => 'Ajukan untuk Persetujuan', 'description' => 'Periksa kembali item transfer, lalu ajukan dokumen agar Kepala Gudang dapat melakukan persetujuan.', 'action' => 'submit'],
            'pending_approval' => ['title' => 'Periksa dan Setujui Transfer', 'description' => 'Kepala Gudang sumber perlu memeriksa jumlah yang diminta serta kecukupan stok sebelum melakukan alokasi.', 'action' => 'approval'],
            'approved' => ['title' => 'Mulai Picking dan Packing', 'description' => 'Stok sumber sudah dialokasikan. Petugas gudang harus mengambil barang dari rak dan mencatat hasil pengemasan.', 'action' => 'packing'],
            'packing' => ['title' => 'Konfirmasi Pengiriman', 'description' => 'Barang sudah masuk proses packing. Lengkapi hasil picking bila perlu, kemudian catat kendaraan atau kurir dan kirim barang.', 'action' => 'shipping'],
            'shipped' => ['title' => 'Terima Barang di Lokasi Tujuan', 'description' => 'Barang sedang dalam perjalanan. Petugas tujuan harus memeriksa jumlah diterima, rusak, dan selisih saat barang tiba.', 'action' => 'receiving'],
            'partially_received' => $hasDiscrepancy
                ? ['title' => 'Selesaikan Selisih Penerimaan', 'description' => 'Ada selisih yang belum dipertanggungjawabkan. Tentukan penyelesaian dan unggah bukti sebelum transfer dapat dilanjutkan.', 'action' => 'discrepancy']
                : ['title' => 'Lanjutkan Penerimaan Barang', 'description' => 'Sebagian barang telah diterima, tetapi masih ada barang dalam perjalanan yang harus dicatat saat tiba.', 'action' => 'receiving'],
            'fully_received' => ['title' => 'Tutup Transfer', 'description' => 'Seluruh barang telah dipertanggungjawabkan. Penanggung jawab tujuan dapat menyelesaikan dokumen transfer.', 'action' => 'complete'],
            'completed' => ['title' => 'Transfer Selesai', 'description' => 'Tidak ada tindakan operasional berikutnya. Dokumen dapat dicetak atau ditinjau melalui daftar transfer.', 'action' => 'finished'],
            'cancelled' => ['title' => 'Transfer Dibatalkan', 'description' => 'Tidak ada tindakan lanjutan pada dokumen ini. Buat transfer baru jika pengiriman tetap diperlukan.', 'action' => 'cancelled'],
            default => ['title' => 'Periksa Status Transfer', 'description' => 'Status dokumen belum memiliki arahan proses berikutnya.', 'action' => 'none'],
        };
    @endphp

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

    <div class="row g-5 mb-5">
        <div class="col-lg-7">
            <x-metronic.card title="Langkah Berikutnya" class="h-100">
                <div class="d-flex align-items-start gap-4 mb-5">
                    <div class="symbol symbol-50px flex-shrink-0">
                        <span class="symbol-label bg-light-primary"><i class="ki-outline ki-route fs-2x text-primary"></i></span>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-gray-900 mb-1">{{ $nextStep['title'] }}</div>
                        <div class="text-gray-700">{{ $nextStep['description'] }}</div>
                    </div>
                </div>

                <div class="rounded border border-dashed border-gray-300 bg-light-primary p-4 mb-5">
                    <div class="text-muted text-uppercase fs-8 fw-bold mb-2">Penanggung Jawab Berikutnya</div>
                    <div class="fw-bold text-gray-900">{{ $nextResponsibility['role'] }}</div>
                    @if($nextResponsibility['location'])
                        <div class="text-muted fs-7"><i class="ki-outline ki-geolocation me-1"></i>{{ $nextResponsibility['location'] }}</div>
                    @endif
                    @if($nextResponsibility['users'])
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @foreach($nextResponsibility['users'] as $responsibleUser)
                                <span class="badge badge-light-primary"><i class="ki-outline ki-user me-1"></i>{{ $responsibleUser }}</span>
                            @endforeach
                        </div>
                    @elseif(!in_array($transfer->status->value, ['completed', 'cancelled'], true))
                        <div class="text-warning fs-8 mt-2">Belum ada user aktif dengan role dan penempatan lokasi yang sesuai.</div>
                    @endif
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @if($nextStep['action'] === 'packing')
                        @can('pack', $transfer)
                            <a href="{{ route('warehouse.stock-transfers.packing', $transfer) }}" class="btn btn-primary"><i class="ki-outline ki-package"></i>Buka Halaman Packing</a>
                        @else
                            <span class="btn btn-light disabled">Menunggu Petugas Gudang</span>
                        @endcan
                    @elseif($nextStep['action'] === 'shipping')
                        @can('ship', $transfer)
                            <a href="{{ route('warehouse.stock-transfers.ship-form', $transfer) }}" class="btn btn-primary"><i class="ki-outline ki-delivery"></i>Buka Halaman Pengiriman</a>
                            @can('pack', $transfer)<a href="{{ route('warehouse.stock-transfers.packing', $transfer) }}" class="btn btn-light-primary">Perbarui Packing</a>@endcan
                        @else
                            <span class="btn btn-light disabled">Menunggu Petugas Pengiriman</span>
                        @endcan
                    @elseif($nextStep['action'] === 'receiving')
                        @can('receive', $transfer)
                            <a href="{{ route('retail.stock-transfers.receive-form', $transfer) }}" class="btn btn-primary"><i class="ki-outline ki-delivery-3"></i>Buka Halaman Penerimaan</a>
                        @else
                            <span class="btn btn-light disabled">Menunggu Petugas Lokasi Tujuan</span>
                        @endcan
                    @elseif($nextStep['action'] === 'discrepancy')
                        @can('resolveDiscrepancy', $transfer)
                            <a href="#penyelesaian-selisih" class="btn btn-warning"><i class="ki-outline ki-information-2"></i>Buka Penyelesaian Selisih</a>
                        @else
                            <span class="btn btn-light disabled">Menunggu Penanggung Jawab Selisih</span>
                        @endcan
                    @elseif($nextStep['action'] === 'submit')
                        @can('update', $transfer)
                            <a href="#aksi-dokumen" class="btn btn-primary"><i class="ki-outline ki-down"></i>Buka Aksi Pengajuan</a>
                        @else
                            <span class="btn btn-light disabled">Menunggu Pembuat Transfer</span>
                        @endcan
                    @elseif($nextStep['action'] === 'approval')
                        @can('approve', $transfer)
                            <a href="#aksi-dokumen" class="btn btn-primary"><i class="ki-outline ki-down"></i>Buka Aksi Persetujuan</a>
                        @else
                            <span class="btn btn-light disabled">Menunggu Kepala Gudang</span>
                        @endcan
                    @elseif($nextStep['action'] === 'complete')
                        @can('complete', $transfer)
                            <a href="#aksi-dokumen" class="btn btn-primary"><i class="ki-outline ki-down"></i>Buka Aksi Penyelesaian</a>
                        @else
                            <span class="btn btn-light disabled">Menunggu Penanggung Jawab Tujuan</span>
                        @endcan
                    @elseif($nextStep['action'] === 'finished')
                        <a href="{{ route('warehouse.stock-transfers.print', $transfer) }}" class="btn btn-light-success"><i class="ki-outline ki-printer"></i>Cetak Surat Jalan</a>
                        <a href="{{ route('warehouse.stock-transfers.index') }}" class="btn btn-light-primary">Kembali ke Daftar</a>
                    @elseif($nextStep['action'] === 'cancelled')
                        <a href="{{ route('warehouse.stock-transfers.create') }}" class="btn btn-primary"><i class="ki-outline ki-plus"></i>Buat Transfer Baru</a>
                        <a href="{{ route('warehouse.stock-transfers.index') }}" class="btn btn-light-primary">Kembali ke Daftar</a>
                    @endif
                </div>
            </x-metronic.card>
        </div>
        <div class="col-lg-5">
            <x-metronic.card title="Perhatian Operasional" class="h-100">
                @if(!$hasDamaged && !$hasDiscrepancy && !$hasTransit && !$hasShort)
                    <div class="alert alert-success mb-0">Tidak ada kerusakan, selisih pengiriman, kekurangan pengambilan, atau barang dalam perjalanan yang perlu ditindaklanjuti.</div>
                @else
                    <div class="d-flex flex-wrap gap-2">
                        @if($hasDamaged)<span class="badge badge-light-danger">Ada barang rusak</span>@endif
                        @if($hasDiscrepancy)<span class="badge badge-light-danger">Ada selisih pengiriman</span>@endif
                        @if($hasTransit)<span class="badge badge-light-warning">Masih ada barang dalam perjalanan</span>@endif
                        @if($hasShort)<span class="badge badge-light-warning">Jumlah diambil kurang dari yang disetujui</span>@endif
                    </div>
                @endif
            </x-metronic.card>
        </div>
    </div>

    <div class="row g-5">
        <div class="col-lg-8">
            <x-metronic.card title="{{ $transfer->number }}">
                <div class="row g-4 mb-5">
                    <div class="col-md-3"><div class="text-muted">Sumber</div><div class="fw-bold">{{ $transfer->sourceWorkLocation?->name }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Tujuan</div><div class="fw-bold">{{ $transfer->destinationWorkLocation?->name }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Tanggal</div><div>{{ $transfer->transfer_date?->format('d/m/Y') }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Status</div><x-metronic.status-badge :status="$transfer->status" /></div>
                    <div class="col-md-3"><div class="text-muted">Sumber Permintaan</div><div>@if($transfer->restockRequest)<a href="{{ route('retail.restock-requests.show', $transfer->restockRequest) }}" class="fw-bold">{{ $transfer->restockRequest->number }}</a>@else-@endif</div></div>
                    <div class="col-md-3"><div class="text-muted">Pengirim</div><div>{{ $transfer->shipper?->name ?: '-' }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Penerima</div><div>{{ $transfer->receiver?->name ?: '-' }}</div></div>
                    <div class="col-md-3"><div class="text-muted">Resi/Kendaraan</div><div>{{ $transfer->tracking_number ?: $transfer->vehicle_number ?: '-' }}</div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Diminta</th><th>Disetujui</th><th>Diambil/Kurang</th><th>Dikirim</th><th>Diterima</th><th>Rusak</th><th>Selisih Pengiriman</th><th>Dalam Perjalanan</th></tr></thead>
                        <tbody>@foreach($transfer->items as $item)<tr><td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td><td>{{ qty($item->quantity_requested) }}</td><td>{{ qty($item->quantity_approved) }}</td><td class="{{ \App\Support\Decimal::compare((string)$item->quantity_short, '0') > 0 ? 'text-warning fw-bold' : '' }}">{{ qty($item->quantity_picked) }} / {{ qty($item->quantity_short) }}</td><td>{{ qty($item->quantity_shipped) }}</td><td>{{ qty($item->quantity_received) }}</td><td class="{{ \App\Support\Decimal::compare((string)$item->quantity_damaged, '0') > 0 ? 'text-danger fw-bold' : '' }}">{{ qty($item->quantity_damaged) }}</td><td class="{{ \App\Support\Decimal::compare($item->unresolvedDiscrepancyQuantity(), '0') > 0 ? 'text-danger fw-bold' : '' }}">{{ qty($item->unresolvedDiscrepancyQuantity()) }} belum selesai<div class="text-muted fs-8">Dicatat {{ qty($item->quantity_discrepancy) }}, diselesaikan {{ qty($item->resolvedDiscrepancyQuantity()) }}</div></td><td class="{{ \App\Support\Decimal::compare($item->inTransitQuantity(), '0') > 0 ? 'text-warning fw-bold' : 'fw-bold' }}">{{ qty($item->inTransitQuantity()) }}</td></tr>@endforeach</tbody>
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
