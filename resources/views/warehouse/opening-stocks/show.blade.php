@extends('layouts.metronic.app')

@section('title', $document->number . ' - Pendataan Stok')
@section('page_title')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('warehouse.opening-stocks.index', ['work_location_id' => $document->work_location_id]) }}" class="btn btn-icon btn-light btn-sm me-2">
            <i class="ki-outline ki-arrow-left fs-3"></i>
        </a>
        Detail Pendataan <span class="text-primary">{{ $document->number }}</span>
    </div>
@endsection

@section('content')
    @error('opening_stock')
        <div class="alert alert-danger d-flex align-items-center p-5 mb-6">
            <i class="ki-outline ki-information-5 fs-2hx text-danger me-4"></i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-danger">Terjadi Kesalahan</h4>
                <span>{{ $message }}</span>
            </div>
        </div>
    @enderror

    @php
        $zeroCount = $document->items->filter(fn($item) => (float)$item->physical_qty === 0.0)->count();
        $increaseCount = $document->items->filter(fn($item) => (float)$item->difference_qty > 0)->count();
        $decreaseCount = $document->items->filter(fn($item) => (float)$item->difference_qty < 0)->count();
        $inventoryValue = $document->items->sum(fn($item) => (float)$item->physical_qty * (float)$item->unit_cost_snapshot);
        $isCreator = (int)$document->created_by === (int)auth()->id();
        $isHead = auth()->user()->hasAnyRole(['kepala_gudang','kepala_toko','super_admin']);
    @endphp

    <div class="row g-5 mb-6">
        <div class="col-lg-8">
            <x-metronic.card title="Informasi Pendataan">
                <x-slot:toolbar>
                    @php
                        $statusColors = [
                            'draft' => 'warning',
                            'pending_approval' => 'primary',
                            'completed' => 'success',
                            'rejected' => 'danger',
                            'cancelled' => 'secondary'
                        ];
                        $statusLabels = [
                            'draft' => 'Draft',
                            'pending_approval' => 'Menunggu Persetujuan',
                            'completed' => 'Selesai',
                            'rejected' => 'Ditolak',
                            'cancelled' => 'Dibatalkan'
                        ];
                        $color = $statusColors[$document->status] ?? 'secondary';
                        $label = $statusLabels[$document->status] ?? $document->status;
                    @endphp
                    <span class="badge badge-light-{{ $color }} fs-7 px-4 py-2 text-uppercase fw-bold">{{ $label }}</span>
                </x-slot:toolbar>

                <div class="row g-5 mb-4">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-40px me-3">
                                <div class="symbol-label bg-light-primary"><i class="ki-outline ki-geolocation fs-2 text-primary"></i></div>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="text-muted fw-semibold fs-8 text-uppercase">Lokasi Kerja</span>
                                <span class="text-gray-800 fw-bold fs-6">{{ $document->workLocation?->name }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-40px me-3">
                                <div class="symbol-label bg-light-info"><i class="ki-outline ki-user fs-2 text-info"></i></div>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="text-muted fw-semibold fs-8 text-uppercase">Dibuat Oleh</span>
                                <span class="text-gray-800 fw-bold fs-6">{{ $document->creator?->name }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-40px me-3">
                                <div class="symbol-label bg-light-success"><i class="ki-outline ki-check-circle fs-2 text-success"></i></div>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="text-muted fw-semibold fs-8 text-uppercase">Diposting Oleh</span>
                                <span class="text-gray-800 fw-bold fs-6">{{ $document->approver?->name ?? 'Belum diposting' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if($document->notes)
                    <div class="bg-light-secondary rounded p-4 mt-2">
                        <span class="fw-semibold text-gray-700"><i class="ki-outline ki-notepad-edit fs-5 text-gray-500 me-1"></i> Catatan Dokumen:</span>
                        <p class="text-gray-800 mt-2 mb-0">{{ $document->notes }}</p>
                    </div>
                @endif
                
                @if($document->decision_note)
                    <div class="alert alert-warning d-flex align-items-center p-4 mt-4 mb-0">
                        <i class="ki-outline ki-message-text-2 fs-2x text-warning me-4"></i>
                        <div class="d-flex flex-column">
                            <h6 class="mb-1 text-warning">Catatan Keputusan:</h6>
                            <span class="text-gray-800">{{ $document->decision_note }}</span>
                        </div>
                    </div>
                @endif
            </x-metronic.card>
        </div>

        <div class="col-lg-4">
            <x-metronic.card title="Akses Lokasi" class="h-100">
                @if($access?->isCurrentlyActive())
                    <div class="d-flex flex-column justify-content-center align-items-center bg-light-success rounded p-6 text-center">
                        <i class="ki-outline ki-check-circle fs-4x text-success mb-3"></i>
                        <span class="badge badge-success fs-6 px-4 py-2 mb-2">Akses Terbuka</span>
                        <span class="text-gray-700 fw-medium mt-2">Pengajuan & Posting aktif sampai:</span>
                        <strong class="text-gray-900 fs-5 mt-1">{{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong>
                    </div>
                @else
                    <div class="d-flex flex-column justify-content-center align-items-center bg-light-danger rounded p-6 text-center">
                        <i class="ki-outline ki-cross-circle fs-4x text-danger mb-3"></i>
                        <span class="badge badge-danger fs-6 px-4 py-2 mb-2">Akses Terkunci</span>
                        <span class="text-gray-700 fw-medium mt-2">Waktu pendataan untuk lokasi ini telah ditutup.</span>
                    </div>
                @endif
            </x-metronic.card>
        </div>
    </div>

    <!-- Ringkasan Statistik -->
    <div class="row g-5 mb-6">
        <div class="col-md-3">
            <div class="card bg-light-primary border-0">
                <div class="card-body p-5 d-flex align-items-center">
                    <div class="symbol symbol-50px me-4">
                        <div class="symbol-label bg-body shadow-sm"><i class="ki-outline ki-dropbox fs-1 text-primary"></i></div>
                    </div>
                    <div>
                        <div class="fs-7 text-muted fw-semibold text-uppercase">Total Produk</div>
                        <div class="fs-2 fw-bold text-gray-800">{{ $document->items->count() }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light-warning border-0">
                <div class="card-body p-5 d-flex align-items-center">
                    <div class="symbol symbol-50px me-4">
                        <div class="symbol-label bg-body shadow-sm"><i class="ki-outline ki-minus-circle fs-1 text-warning"></i></div>
                    </div>
                    <div>
                        <div class="fs-7 text-muted fw-semibold text-uppercase">Stok Nol</div>
                        <div class="fs-2 fw-bold text-gray-800">{{ $zeroCount }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light-info border-0">
                <div class="card-body p-5 d-flex align-items-center">
                    <div class="symbol symbol-50px me-4">
                        <div class="symbol-label bg-body shadow-sm"><i class="ki-outline ki-graph-up fs-1 text-info"></i></div>
                    </div>
                    <div>
                        <div class="fs-7 text-muted fw-semibold text-uppercase">Naik / Turun</div>
                        <div class="fs-3 fw-bold"><span class="text-success">+{{ $increaseCount }}</span> <span class="text-muted mx-1">|</span> <span class="text-danger">-{{ $decreaseCount }}</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light-success border-0">
                <div class="card-body p-5 d-flex align-items-center">
                    <div class="symbol symbol-50px me-4">
                        <div class="symbol-label bg-body shadow-sm"><i class="ki-outline ki-wallet fs-1 text-success"></i></div>
                    </div>
                    <div>
                        <div class="fs-7 text-muted fw-semibold text-uppercase">Total Nilai Fisik</div>
                        <div class="fs-3 fw-bold text-gray-800">Rp {{ number_format($inventoryValue, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rincian Produk (Table) -->
    <x-metronic.card title="Rincian Produk">
        <div class="alert alert-primary d-flex align-items-center p-4 mb-6 mt-2">
            <i class="ki-outline ki-information-5 fs-2hx text-primary me-4"></i>
            <div class="d-flex flex-column">
                <h6 class="mb-1 text-primary">Informasi Pendataan Fisik</h6>
                <span>Kolom <strong>Jumlah Fisik Sekarang</strong> adalah saldo akhir di lokasi tersebut. Sistem secara otomatis hanya memutasi nilai <strong>Selisih</strong> dari sistem.</span>
            </div>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-row-bordered table-row-dashed align-middle gs-4 gy-4 mb-0">
                <thead class="bg-light-subtle text-muted fw-bold text-uppercase fs-8">
                    <tr>
                        <th class="min-w-250px">Produk</th>
                        <th class="min-w-120px">Rak</th>
                        <th class="text-end min-w-100px">Jml Sistem</th>
                        <th class="text-end min-w-120px text-primary">Jml Fisik (Aktual)</th>
                        <th class="text-end min-w-100px">Selisih</th>
                        <th class="text-end min-w-120px">HPP Item</th>
                        <th class="min-w-150px">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($document->items as $item)
                        @php
                            $diff = (float)$item->difference_qty;
                            $diffClass = $diff > 0 ? 'text-success bg-light-success' : ($diff < 0 ? 'text-danger bg-light-danger' : 'text-gray-600 bg-light-secondary');
                            $diffIcon = $diff > 0 ? 'ki-arrow-up' : ($diff < 0 ? 'ki-arrow-down' : 'ki-minus');
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3 flex-shrink-0">
                                        <x-metronic.image-preview 
                                            url="{{ $item->product?->main_image_url ?? \App\Models\Product::defaultImageUrl() }}" 
                                            title="{{ $item->product_name_snapshot }}" 
                                            size="50px" 
                                            classes="rounded object-fit-cover border border-gray-200" />
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-gray-800 fs-6 text-truncate" style="max-width: 250px;" title="{{ $item->product_name_snapshot }}">{{ $item->product_name_snapshot }}</span>
                                        <span class="text-muted fs-8">SKU: <strong class="text-gray-700">{{ $item->product_sku_snapshot }}</strong> &bull; Satuan: {{ $item->product?->baseUnit?->symbol ?? 'unit' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-outline badge-dark fs-8 px-2 py-1"><i class="ki-outline ki-abstract-14 fs-8 me-1 text-dark"></i>{{ $item->warehouseLocation?->full_code ?? 'Lokasi Utama' }}</span>
                            </td>
                            <td class="text-end text-muted fw-semibold">{{ qty($item->system_qty_snapshot) }}</td>
                            <td class="text-end fw-bolder text-primary fs-5">{{ qty($item->physical_qty) }}</td>
                            <td class="text-end">
                                <span class="badge {{ $diffClass }} fw-bold px-2 py-1 d-inline-flex align-items-center">
                                    <i class="ki-outline {{ $diffIcon }} fs-8 me-1 {{ str_replace('bg-light-', 'text-', $diffClass) }}"></i>
                                    {{ qty($item->difference_qty) }}
                                </span>
                            </td>
                            <td class="text-end fw-semibold text-gray-700">Rp {{ number_format((float)$item->unit_cost_snapshot, 0, ',', '.') }}</td>
                            <td class="text-muted fs-8">{{ $item->note ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-metronic.card>

    <!-- Formulir Edit Draft -->
    @if($document->status === 'draft' && $isCreator && $access?->isCurrentlyActive())
        <x-metronic.card class="mt-6" title="Perbarui Form Draft">
            <x-slot:toolbar>
                <i class="ki-outline ki-pencil fs-2 text-primary"></i>
            </x-slot:toolbar>
            
            <form method="POST" action="{{ route('warehouse.opening-stocks.update', $document) }}">
                @csrf @method('PUT')
                <input type="hidden" name="work_location_id" value="{{ $document->work_location_id }}">
                
                <div class="table-responsive border rounded mb-5 mt-2">
                    <table class="table table-row-bordered align-middle gs-4 gy-4 mb-0">
                        <thead class="bg-light text-muted fw-bold text-uppercase fs-8">
                            <tr>
                                <th class="min-w-200px">Produk</th>
                                <th class="min-w-150px">Rak</th>
                                <th class="min-w-120px">Jml Fisik</th>
                                <th class="min-w-200px">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($document->items as $index => $item)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-gray-800 fs-7 text-truncate" style="max-width: 200px;" title="{{ $item->product_name_snapshot }}">{{ $item->product_sku_snapshot }} - {{ $item->product_name_snapshot }}</div>
                                        <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                                    </td>
                                    <td>
                                        <select name="items[{{ $index }}][warehouse_location_id]" class="form-select form-select-sm" required>
                                            @foreach($bins as $bin)
                                                <option value="{{ $bin->id }}" @selected($item->warehouse_location_id === $bin->id)>{{ $bin->full_code }} - {{ $bin->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="any" min="0" name="items[{{ $index }}][physical_qty]" value="{{ qty_input($item->physical_qty) }}" class="form-control form-control-sm border-primary" required>
                                    </td>
                                    <td>
                                        <input name="items[{{ $index }}][note]" value="{{ $item->note }}" class="form-control form-control-sm" placeholder="Opsional">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mb-5">
                    <label class="form-label fw-bold">Catatan Umum Pendataan</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Tulis catatan jika diperlukan...">{{ $document->notes }}</textarea>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-4 border-top">
                    <div class="text-muted fs-8">Perubahan ini hanya akan disimpan ke dalam Draft (belum diajukan).</div>
                    <button type="submit" class="btn btn-light-primary font-weight-bold">
                        <i class="ki-outline ki-save-2 fs-4 me-1"></i> Simpan Perubahan Draft
                    </button>
                </div>
            </form>
        </x-metronic.card>
        
        <!-- Submit Button (Separate Form) -->
        <div class="d-flex justify-content-end mt-4">
            <form method="POST" action="{{ route('warehouse.opening-stocks.submit', $document) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Apakah Anda yakin ingin mengajukan pendataan ini? Anda tidak bisa mengedit draft setelah diajukan.')">
                    <i class="ki-outline ki-send fs-2 me-2"></i> Ajukan Pendataan untuk Posting
                </button>
            </form>
        </div>
    @endif

    <!-- Approval / Posting Logic -->
    @if($document->status === 'pending_approval' && auth()->user()->can('opening_stocks.approve') && $isHead && $access?->isCurrentlyActive())
        <div class="card mt-6 border border-success border-dashed bg-light-success">
            <div class="card-body">
                <h3 class="card-title fw-bold text-success m-0 mb-4">
                    <i class="ki-outline ki-check-square fs-2 text-success me-2"></i> Pemeriksaan & Posting Saldo Stok
                </h3>
                
                @if($isCreator)
                    <div class="alert alert-warning d-flex align-items-center p-4 mb-5">
                        <i class="ki-outline ki-shield-tick fs-2hx text-warning me-4"></i>
                        <div class="d-flex flex-column">
                            <h6 class="mb-1">Hak Akses Bypass</h6>
                            <span>Anda membuat dokumen ini sebagai Kepala Lokasi. Anda diizinkan untuk mempostingnya sendiri, namun tindakan ini dicatat sebagai tindakan berisiko tinggi.</span>
                        </div>
                    </div>
                @else
                    <div class="alert alert-primary d-flex align-items-center p-4 mb-5">
                        <i class="ki-outline ki-information-5 fs-2hx text-primary me-4"></i>
                        <div class="d-flex flex-column">
                            <h6 class="mb-1">Tinjauan Draft Pendataan</h6>
                            <span>Harap periksa kolom <strong>Jumlah Fisik Sekarang</strong> dan <strong>Rak</strong> sebelum memposting. Jika ada yang salah, Anda dapat menolak dokumen ini.</span>
                        </div>
                    </div>
                @endif
                
                <div class="row g-6">
                    <div class="col-md-6">
                        <form method="POST" action="{{ route('warehouse.opening-stocks.approve', $document) }}" class="bg-white p-5 rounded shadow-sm">
                            @csrf
                            <label class="form-label fw-bold text-success">Approve & Posting</label>
                            <textarea name="notes" class="form-control bg-light-success border-success" rows="3" placeholder="Tambahkan catatan posting..." required></textarea>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-success" onclick="return confirm('Apakah Anda yakin? Stok akan langsung ter-update di sistem.')">
                                    <i class="ki-outline ki-check-circle fs-3 me-2"></i> Posting sebagai Saldo Akhir
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    @if(!$isCreator)
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('warehouse.opening-stocks.reject', $document) }}" class="bg-white p-5 rounded shadow-sm">
                                @csrf
                                <label class="form-label fw-bold text-danger">Tolak Pendataan</label>
                                <textarea name="notes" class="form-control bg-light-danger border-danger" rows="3" placeholder="Alasan penolakan..." required></textarea>
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Tolak dokumen pendataan ini?')">
                                        <i class="ki-outline ki-cross-circle fs-3 me-2"></i> Tolak Dokumen
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel Logic -->
    @if(in_array($document->status, ['draft','pending_approval'], true) && ($isCreator || auth()->user()->hasRole('super_admin')))
        <div class="card mt-6 border-0 shadow-sm">
            <div class="card-body py-5">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="ki-outline ki-trash fs-2x text-danger me-4"></i>
                        <div class="d-flex flex-column">
                            <h5 class="mb-1 text-danger">Pembatalan Dokumen</h5>
                            <span class="text-muted fs-7">Jika dokumen ini tidak relevan, Anda dapat membatalkannya.</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('warehouse.opening-stocks.cancel', $document) }}" class="d-flex align-items-center">
                        @csrf
                        <input name="notes" class="form-control form-control-solid me-3 w-250px" minlength="5" placeholder="Alasan pembatalan..." required>
                        <button type="submit" class="btn btn-light-danger" onclick="return confirm('Batalkan dokumen pendataan stok ini? Tindakan ini tidak dapat dikembalikan.')">
                            Batalkan Pendataan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Riwayat Keputusan / Approvals -->
    @if($document->approvals->isNotEmpty())
        <x-metronic.card title="Riwayat Aktivitas & Keputusan" class="mt-6">
            <div class="timeline mt-2">
                @foreach($document->approvals as $approval)
                    @php
                        $iconClass = $approval->decision === 'approved' ? 'ki-check-circle text-success' : ($approval->decision === 'rejected' ? 'ki-cross-circle text-danger' : 'ki-information-5 text-primary');
                        $badgeClass = $approval->decision === 'approved' ? 'badge-light-success' : ($approval->decision === 'rejected' ? 'badge-light-danger' : 'badge-light-primary');
                        $decisionLabel = $approval->decision === 'approved' ? 'Diposting' : ($approval->decision === 'rejected' ? 'Ditolak' : ucfirst($approval->decision));
                    @endphp
                    <div class="timeline-item">
                        <div class="timeline-line w-40px"></div>
                        <div class="timeline-icon symbol symbol-circle symbol-40px">
                            <div class="symbol-label bg-light">
                                <i class="ki-outline {{ $iconClass }} fs-2"></i>
                            </div>
                        </div>
                        <div class="timeline-content mt-n1">
                            <div class="pe-3 mb-5">
                                <div class="fs-5 fw-semibold mb-2">Dokumen <strong>{{ $decisionLabel }}</strong> oleh <span class="text-primary fw-bold">{{ $approval->approver?->name }}</span></div>
                                <div class="d-flex align-items-center mt-1 fs-7">
                                    <div class="text-muted me-2 fs-8"><i class="ki-outline ki-calendar fs-7 me-1"></i>{{ $approval->decided_at?->format('d M Y, H:i') }} WIB</div>
                                </div>
                                <div class="bg-light rounded p-3 mt-3 text-gray-700">
                                    <span class="fw-bold">Catatan:</span> {{ $approval->notes }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-metronic.card>
    @endif
@endsection
