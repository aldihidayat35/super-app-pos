@extends('layouts.metronic.app')

@section('title', 'Transfer Antar Cabang - ' . config('app.name'))
@section('page_title', 'Daftar Transfer Stok')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-transfers" title="Panduan Transfer Antar Cabang">
        <x-slot:function>
            <p>Halaman ini merupakan pusat pemindahan stok <strong>antar cabang atau antar gudang</strong>. Alur transfer telah disederhanakan tanpa birokrasi persetujuan (approval): barang langsung dikirim (In-Transit) oleh penanggung jawab pengirim, kemudian diterima dan diverifikasi dengan 1-klik di cabang tujuan oleh penanggung jawab penerima.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol>
                <li><strong>Kirim Barang:</strong> Klik tombol <strong>Kirim Transfer</strong>, pilih cabang asal dan cabang tujuan, tentukan barang, lalu kirim langsung. Status otomatis menjadi <em>Dalam Perjalanan (In-Transit)</em>.</li>
                <li><strong>Terima Barang:</strong> Cabang tujuan melihat transfer pada tab <strong>Penerimaan Masuk</strong> dan memverifikasi barang dengan tombol 1-klik <em>Terima Semua</em>.</li>
            </ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul>
                <li><strong>Tab Kirim Transfer:</strong> Memantau semua pengiriman transfer keluar dari lokasi Anda.</li>
                <li><strong>Tab Penerimaan Masuk:</strong> Daftar barang kiriman dari cabang lain yang siap Anda terima.</li>
                <li><strong>Tab Permintaan Restok:</strong> Permintaan kebutuhan stok dari cabang yang dapat langsung diproses menjadi pengiriman transfer.</li>
                <li><strong>Penanggung Jawab:</strong> Setiap akun yang menginput tercatat bertanggung jawab penuh atas data yang dibuat.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Pengiriman transfer langsung memotong stok di cabang asal dan mencatat stok dalam perjalanan. Penerimaan di cabang tujuan langsung menambah stok on-hand cabang tujuan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Gunakan tab navigasi untuk beralih antara transfer keluar, penerimaan masuk, atau permintaan restok.</li>
                <li>Gunakan filter nomor, cabang, atau tanggal jika diperlukan.</li>
                <li>Klik tombol <strong>Terima Barang</strong> pada tab penerimaan untuk verifikasi instan.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <ul>
                    <li>Pastikan cabang asal dan cabang tujuan berbeda.</li>
                    <li>Stok cabang pengirim harus mencukupi kuantitas yang dikirim.</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Gudang Pusat mengirim 20 unit Tumbler ke Toko Kedai Awal. Barang langsung berstatus Dikirim. Staf Kedai Awal membuka tab Penerimaan Masuk dan klik Terima Semua.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('warehouse.location-transfers.index') }}" class="btn btn-light-info me-2">
        <i class="ki-outline ki-arrow-right-left fs-4 me-1"></i> Pindah Rak Internal
    </a>
    <x-metronic.permission-button permission="stock_transfers.create" :href="route('warehouse.stock-transfers.create')" icon="ki-outline ki-plus" class="btn-primary">
        Kirim Transfer
    </x-metronic.permission-button>
@endsection

@section('content')
    <!-- Nav Tabs Terpadu -->
    <ul class="nav nav-tabs nav-line-tabs nav-stretch fs-6 border-0 mb-6">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'outgoing' ? 'active text-primary fw-bolder' : 'text-muted' }}" href="{{ route('warehouse.stock-transfers.index', ['tab' => 'outgoing']) }}">
                <i class="ki-outline ki-send fs-4 me-2 {{ $activeTab === 'outgoing' ? 'text-primary' : '' }}"></i>
                Kirim Transfer (Keluar)
                @if($outgoingCount > 0)
                    <span class="badge badge-sm badge-circle badge-light-primary ms-2">{{ $outgoingCount }}</span>
                @endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'incoming' ? 'active text-success fw-bolder' : 'text-muted' }}" href="{{ route('warehouse.stock-transfers.index', ['tab' => 'incoming']) }}">
                <i class="ki-outline ki-delivery fs-4 me-2 {{ $activeTab === 'incoming' ? 'text-success' : '' }}"></i>
                Penerimaan Masuk
                @if($incomingCount > 0)
                    <span class="badge badge-sm badge-circle badge-light-success ms-2">{{ $incomingCount }}</span>
                @endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'restock' ? 'active text-warning fw-bolder' : 'text-muted' }}" href="{{ route('warehouse.stock-transfers.index', ['tab' => 'restock']) }}">
                <i class="ki-outline ki-basket fs-4 me-2 {{ $activeTab === 'restock' ? 'text-warning' : '' }}"></i>
                Permintaan Restok Cabang
                @if($restockCount > 0)
                    <span class="badge badge-sm badge-circle badge-light-warning ms-2">{{ $restockCount }}</span>
                @endif
            </a>
        </li>
    </ul>

    @if($activeTab === 'restock')
        <!-- Tab Permintaan Restok Cabang -->
        <x-metronic.card title="Permintaan Restok dari Cabang">
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7">
                            <th class="ps-4">Nomor Permintaan</th>
                            <th>Tanggal</th>
                            <th>Cabang Pemohon</th>
                            <th>Gudang Sumber</th>
                            <th>Total Item</th>
                            <th>Status</th>
                            <th>Pemohon</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($restockRequests ?? [] as $request)
                            <tr>
                                <td class="ps-4 fw-bold">
                                    <a href="{{ route('retail.restock-requests.show', $request) }}" class="text-primary fw-bolder">
                                        {{ $request->number }}
                                    </a>
                                </td>
                                <td>{{ $request->requested_at?->format('d/m/Y H:i') ?: '-' }}</td>
                                <td><span class="badge badge-light-primary fw-bold">{{ $request->branch?->name ?: $request->branch?->workLocation?->name }}</span></td>
                                <td>{{ $request->sourceWarehouse?->name ?: '-' }}</td>
                                <td>{{ $request->items->count() }} item</td>
                                <td><x-metronic.status-badge :status="$request->status" /></td>
                                <td>{{ $request->requester?->name ?: '-' }}</td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('retail.restock-requests.show', $request) }}" class="btn btn-sm btn-light-primary">
                                        Lihat Request
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <x-metronic.empty-state title="Tidak ada permintaan restok" description="Permintaan restok dari cabang akan tampil di sini untuk diproses pengirimannya." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($restockRequests)
                {{ $restockRequests->links() }}
            @endif
        </x-metronic.card>
    @else
        <!-- Tab Kirim Transfer atau Penerimaan Masuk -->
        <x-metronic.card>
            <!-- Form Filter Cepat -->
            <form method="GET" class="row g-3 mb-6">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <div class="col-md-3">
                    <input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-solid" placeholder="Nomor transfer">
                </div>
                <div class="col-md-3">
                    <input name="source_reference" value="{{ $filters['source_reference'] ?? '' }}" class="form-control form-control-solid" placeholder="Nomor permintaan restok">
                </div>
                <div class="col-md-3">
                    <select name="source_work_location_id" class="form-select form-select-solid">
                        <option value="">Semua lokasi asal</option>
                        @foreach($workLocations as $location)
                            <option value="{{ $location->id }}" @selected(($filters['source_work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="destination_work_location_id" class="form-select form-select-solid">
                        <option value="">Semua lokasi tujuan</option>
                        @foreach($workLocations as $location)
                            <option value="{{ $location->id }}" @selected(($filters['destination_work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-solid">
                        <option value="">Semua status</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid" aria-label="Tanggal mulai">
                </div>
                <div class="col-md-3">
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid" aria-label="Tanggal selesai">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-light-primary w-100 fw-bold">
                        <i class="ki-outline ki-filter fs-5 me-1"></i>Terapkan Filter
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead>
                        <tr class="text-muted fw-bold text-uppercase fs-7">
                            <th class="ps-4 min-w-140px">Nomor Transfer</th>
                            <th>Tanggal</th>
                            <th>Lokasi Asal (Pengirim)</th>
                            <th>Lokasi Tujuan</th>
                            <th>Item / Qty</th>
                            <th>Status</th>
                            <th>Penanggung Jawab</th>
                            <th class="text-end pe-4 min-w-160px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($transfers as $transfer)
                        <tr>
                            <td class="ps-4">
                                <a href="{{ route('warehouse.stock-transfers.show', $transfer) }}" class="fw-bolder text-gray-800 text-hover-primary">
                                    {{ $transfer->number }}
                                </a>
                                @if($transfer->restockRequest)
                                    <div class="text-muted fs-9">Ref: {{ $transfer->restockRequest->number }}</div>
                                @endif
                            </td>
                            <td>{{ $transfer->transfer_date?->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge badge-light-primary fw-bold">{{ $transfer->sourceWorkLocation?->name }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light-info fw-bold">{{ $transfer->destinationWorkLocation?->name }}</span>
                            </td>
                            <td>
                                <span class="fw-bolder text-gray-800">{{ $transfer->items->count() }} item</span>
                                <div class="text-muted fs-8">{{ qty($transfer->items->reduce(fn($carry, $item) => \App\Support\Decimal::add($carry, (string) $item->quantity_requested), '0.0000')) }} Total Qty</div>
                            </td>
                            <td>
                                <x-metronic.status-badge :status="$transfer->status" />
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="text-gray-800 fw-bold fs-8">
                                        <i class="ki-outline ki-user text-muted me-1"></i>{{ $transfer->shipper?->name ?: ($transfer->requester?->name ?: '-') }}
                                    </span>
                                    @if($transfer->receiver)
                                        <span class="text-success fs-9">
                                            <i class="ki-outline ki-check-circle me-1"></i>{{ $transfer->receiver->name }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                @if($activeTab === 'incoming' && in_array($transfer->status->value, ['shipped', 'partially_received'], true) && auth()->user()->can('stock_transfers.receive'))
                                    <a href="{{ route('retail.stock-transfers.receive-form', $transfer) }}" class="btn btn-sm btn-success fw-bold me-1">
                                        <i class="ki-outline ki-check-circle fs-5 me-1"></i>Terima Barang
                                    </a>
                                @endif
                                <a href="{{ route('warehouse.stock-transfers.show', $transfer) }}" class="btn btn-sm btn-light-primary" title="Lihat Detail">Detail</a>
                                <a href="{{ route('warehouse.stock-transfers.print', $transfer) }}" class="btn btn-sm btn-light-secondary" title="Cetak Surat Jalan"><i class="ki-outline ki-printer fs-5"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-metronic.empty-state
                                    title="Belum ada transfer stok"
                                    description="{{ $activeTab === 'incoming' ? 'Tidak ada kiriman transfer masuk yang perlu diterima saat ini.' : 'Daftar pengiriman transfer antar cabang akan tampil di sini.' }}" />
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $transfers->links() }}
        </x-metronic.card>
    @endif
@endsection
