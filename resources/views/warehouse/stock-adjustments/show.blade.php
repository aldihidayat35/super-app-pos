@extends('layouts.metronic.app')

@section('title', $adjustment->number . ' - Koreksi Stok')
@section('page_title', 'Detail Koreksi ' . $adjustment->number)

@section('content')
    @error('adjustment')
        <div class="alert alert-danger d-flex align-items-center p-5 mb-5">
            <i class="ki-outline ki-information-5 fs-2qx text-danger me-4"></i>
            <div>{{ $message }}</div>
        </div>
    @enderror

    <!-- Info Header -->
    <div class="row g-6 mb-6">
        <div class="col-lg-8">
            <x-metronic.card title="Informasi Dokumen" icon="ki-outline ki-document text-primary">
                <div class="d-flex flex-wrap gap-5">
                    <!-- Creator Profile -->
                    <div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-40px symbol-circle me-3">
                                @if($adjustment->creator?->avatar_path)
                                    <img src="{{ $adjustment->creator->avatar_url }}" alt="{{ $adjustment->creator->name }}" style="object-fit: cover;" onerror="this.outerHTML='<span class=\'symbol-label bg-light-primary text-primary fs-3 fw-bold\'>{{ substr($adjustment->creator?->name ?? 'U', 0, 1) }}</span>'" />
                                @else
                                    <span class="symbol-label bg-light-primary text-primary fs-3 fw-bold">{{ substr($adjustment->creator?->name ?? 'U', 0, 1) }}</span>
                                @endif
                            </div>
                            <div>
                                <div class="fs-6 text-gray-800 fw-bold">{{ $adjustment->creator?->name }}</div>
                                <div class="fs-7 text-muted">Pembuat Dokumen</div>
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-40px me-3">
                                <span class="symbol-label bg-light-success"><i class="ki-outline ki-geolocation fs-2x text-success"></i></span>
                            </div>
                            <div>
                                <div class="fs-6 text-gray-800 fw-bold">{{ $adjustment->workLocation?->name }}</div>
                                <div class="fs-7 text-muted">Lokasi / Cabang</div>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 mb-3">
                        <div class="d-flex align-items-center">
                            @php
                                $statusColor = match($adjustment->status) {
                                    'draft' => 'secondary',
                                    'pending_approval' => 'warning',
                                    'approved' => 'success',
                                    'rejected', 'cancelled' => 'danger',
                                    default => 'primary'
                                };
                            @endphp
                            <div class="symbol symbol-40px me-3">
                                <span class="symbol-label bg-light-{{ $statusColor }}"><i class="ki-outline ki-flag fs-2x text-{{ $statusColor }}"></i></span>
                            </div>
                            <div>
                                <div class="fs-6 text-{{ $statusColor }} fw-bold text-capitalize">{{ str_replace('_', ' ', $adjustment->status) }}</div>
                                <div class="fs-7 text-muted">Status Saat Ini</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($adjustment->notes)
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4 mt-6">
                        <i class="ki-outline ki-information fs-2tx text-primary me-4"></i>
                        <div class="d-flex flex-stack flex-grow-1">
                            <div class="fw-semibold">
                                <h4 class="text-gray-900 fw-bold m-0">Catatan Pembuat</h4>
                                <div class="fs-6 text-gray-700 mt-1">{{ $adjustment->notes }}</div>
                            </div>
                        </div>
                    </div>
                @endif
                
                @if($adjustment->decision_note)
                    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4 mt-4">
                        <i class="ki-outline ki-shield-tick fs-2tx text-warning me-4"></i>
                        <div class="d-flex flex-stack flex-grow-1">
                            <div class="fw-semibold">
                                <h4 class="text-gray-900 fw-bold m-0">Catatan Persetujuan</h4>
                                <div class="fs-6 text-gray-700 mt-1">{{ $adjustment->decision_note }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            </x-metronic.card>
        </div>

        <div class="col-lg-4">
            <x-metronic.card title="Akses Lokasi" icon="ki-outline ki-lock-2 text-dark">
                @if($access?->isCurrentlyActive())
                    <div class="d-flex align-items-center mb-5">
                        <i class="ki-outline ki-check-circle fs-1 text-success me-3"></i>
                        <div class="d-flex flex-column">
                            <span class="fs-4 fw-bold text-gray-800">Akses Terbuka</span>
                            <span class="text-muted fw-semibold">Bisa melakukan pengajuan</span>
                        </div>
                    </div>
                    <div class="bg-light-success border border-success border-dashed rounded p-4">
                        <span class="text-success fw-bold d-block mb-1"><i class="ki-outline ki-timer text-success me-1"></i> Berlaku Sampai:</span>
                        <span class="fs-6 text-gray-800 fw-bold">{{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
                    </div>
                @else
                    <div class="d-flex align-items-center mb-5">
                        <i class="ki-outline ki-cross-circle fs-1 text-danger me-3"></i>
                        <div class="d-flex flex-column">
                            <span class="fs-4 fw-bold text-gray-800">Akses Terkunci</span>
                            <span class="text-muted fw-semibold">Pengajuan dibekukan</span>
                        </div>
                    </div>
                @endif
            </x-metronic.card>
        </div>
    </div>

    <!-- Product Details -->
    <x-metronic.card title="Rincian Produk" icon="ki-outline ki-handcart text-info" class="mb-6">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-muted bg-light">
                        <th class="ps-4 min-w-250px rounded-start">Produk</th>
                        <th class="min-w-125px">Lokasi Rak</th>
                        <th class="text-end min-w-100px">Stok Sistem</th>
                        <th class="text-end min-w-100px">Fisik Final</th>
                        <th class="text-end min-w-100px">Selisih</th>
                        <th class="text-end min-w-125px">Nilai (Rp)</th>
                        <th class="pe-4 rounded-end">Catatan Item</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($adjustment->items as $item)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <x-metronic.image-preview 
                                            url="{{ $item->product?->main_image_url ?? \App\Models\Product::defaultImageUrl() }}" 
                                            title="{{ $item->product_name_snapshot }}" 
                                            size="50px" 
                                            classes="rounded object-fit-cover border border-gray-200" />
                                    </div>
                                    <div class="d-flex justify-content-start flex-column">
                                        <span class="text-gray-800 fw-bold mb-1 fs-6">{{ $item->product_name_snapshot }}</span>
                                        <span class="text-muted fw-semibold d-block fs-7">SKU: {{ $item->product_sku_snapshot }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-light-dark">{{ $item->warehouseLocation?->full_code ?? 'Lokasi Utama' }}</span>
                            </td>
                            <td class="text-end">
                                <span class="text-gray-700 fw-bold">{{ qty($item->system_qty_snapshot) }}</span>
                            </td>
                            <td class="text-end">
                                <span class="text-primary fw-bolder fs-5">{{ qty($item->physical_qty) }}</span>
                            </td>
                            <td class="text-end">
                                @if($item->difference_qty < 0)
                                    <span class="badge badge-light-danger fs-7 px-2 py-1"><i class="ki-outline ki-arrow-down fs-7 text-danger"></i> {{ qty(abs($item->difference_qty)) }}</span>
                                @elseif($item->difference_qty > 0)
                                    <span class="badge badge-light-success fs-7 px-2 py-1"><i class="ki-outline ki-arrow-up fs-7 text-success"></i> {{ qty($item->difference_qty) }}</span>
                                @else
                                    <span class="badge badge-light-secondary fs-7 px-2 py-1">0</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <span class="text-gray-800 fw-semibold">{{ number_format((float)$item->difference_value, 0, ',', '.') }}</span>
                            </td>
                            <td class="pe-4 text-muted fs-7">
                                {{ $item->note ?: '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-metronic.card>

    @can('update', $adjustment)
        @if($access?->isCurrentlyActive())
            <x-metronic.card title="Perbarui Draft" icon="ki-outline ki-pencil text-warning" class="mb-6">
                <form method="POST" action="{{ route('warehouse.stock-adjustments.update', $adjustment) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="work_location_id" value="{{ $adjustment->work_location_id }}">
                    @foreach($adjustment->items as $index => $item)
                        <div class="row g-3 mb-5 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Produk / Stok</label>
                                <select name="items[{{ $index }}][stock_id]" class="form-select form-select-solid" required>
                                    @foreach($stocks as $stock)
                                        <option value="{{ $stock->id }}" @selected($item->stock_id === $stock->id)>
                                            {{ $stock->product?->sku }} — {{ $stock->product?->name }} ({{ qty($stock->quantity_on_hand) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Stok Fisik Final</label>
                                <input type="number" step="any" min="0" name="items[{{ $index }}][physical_qty]" value="{{ qty_input($item->physical_qty) }}" class="form-control form-control-solid" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Catatan Item</label>
                                <input name="items[{{ $index }}][note]" value="{{ $item->note }}" class="form-control form-control-solid" placeholder="Contoh: Barang rusak kemasan" required>
                            </div>
                        </div>
                    @endforeach
                    <div class="separator my-5"></div>
                    <label class="form-label">Catatan Umum Dokumen</label>
                    <textarea name="notes" class="form-control form-control-solid" rows="3" placeholder="Tambahkan catatan tambahan jika diperlukan">{{ $adjustment->notes }}</textarea>
                    
                    <div class="mt-5 d-flex gap-3">
                        <button class="btn btn-light-primary"><i class="ki-outline ki-save-2"></i> Simpan Perubahan Draft</button>
                    </div>
                </form>
            </x-metronic.card>

            <form method="POST" action="{{ route('warehouse.stock-adjustments.submit', $adjustment) }}" class="mb-6 text-end">
                @csrf
                <button class="btn btn-primary btn-lg"><i class="ki-outline ki-send"></i> Ajukan Persetujuan Sekarang</button>
            </form>
        @endif
    @endcan

    @can('decide', $adjustment)
        @if($access?->isCurrentlyActive())
            <x-metronic.card title="Keputusan Petugas" icon="ki-outline ki-check-square text-success" class="mt-6 border-success">
                <div class="alert alert-dismissible bg-light-success border border-success border-dashed d-flex flex-column flex-sm-row w-100 p-5 mb-5">
                    <i class="ki-outline ki-shield-tick fs-2hx text-success me-4 mb-5 mb-sm-0"></i>
                    <div class="d-flex flex-column pe-0 pe-sm-10">
                        <h5 class="mb-1">Persetujuan Koreksi Stok</h5>
                        <span>Tindakan persetujuan akan langsung memposting koreksi seluruh item dan menyesuaikan saldo stok secara permanen dalam satu transaksi.</span>
                    </div>
                </div>
                
                <div class="row g-6">
                    <div class="col-md-6">
                        <div class="bg-light-primary rounded p-5">
                            <form method="POST" action="{{ route('warehouse.stock-adjustments.approve', $adjustment) }}">
                                @csrf
                                <label class="form-label fw-bold">Catatan Persetujuan</label>
                                <textarea name="notes" class="form-control form-control-solid" rows="3" placeholder="Alasan mengapa disetujui (contoh: Sesuai pengecekan fisik)" required></textarea>
                                <button class="btn btn-success w-100 mt-4"><i class="ki-outline ki-check-circle"></i> Setujui & Perbarui Stok</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light-danger rounded p-5">
                            <form method="POST" action="{{ route('warehouse.stock-adjustments.reject', $adjustment) }}">
                                @csrf
                                <label class="form-label fw-bold text-danger">Catatan Penolakan</label>
                                <textarea name="notes" class="form-control form-control-solid border-danger" rows="3" placeholder="Alasan mengapa ditolak" required></textarea>
                                <button class="btn btn-danger w-100 mt-4"><i class="ki-outline ki-cross-circle"></i> Tolak Koreksi</button>
                            </form>
                        </div>
                    </div>
                </div>
            </x-metronic.card>
        @endif
    @endcan

    @if(in_array($adjustment->status, ['draft','pending_approval'], true) && ((int)$adjustment->created_by === (int)auth()->id() || auth()->user()->hasRole('super_admin')))
        <div class="card mt-6">
            <div class="card-body">
                <form method="POST" action="{{ route('warehouse.stock-adjustments.cancel', $adjustment) }}" class="d-flex align-items-center gap-3">
                    @csrf
                    <div class="flex-grow-1">
                        <input name="notes" class="form-control form-control-solid" minlength="5" placeholder="Alasan pembatalan (misal: Salah input produk)" required>
                    </div>
                    <button class="btn btn-light-danger"><i class="ki-outline ki-trash"></i> Batalkan Dokumen</button>
                </form>
            </div>
        </div>
    @endif

    @if($adjustment->approvals->isNotEmpty())
        <x-metronic.card title="Riwayat Keputusan & Audit" icon="ki-outline ki-time text-gray-700" class="mt-6">
            <div class="timeline-label">
                @foreach($adjustment->approvals as $approval)
                    <div class="timeline-item">
                        <div class="timeline-label fw-bold text-gray-800 fs-7" style="width: 100px;">
                            {{ $approval->decided_at?->format('H:i') }}<br>
                            <span class="text-muted">{{ $approval->decided_at?->format('d/m/Y') }}</span>
                        </div>
                        
                        <div class="timeline-badge">
                            @if($approval->decision === 'approved')
                                <i class="ki-outline ki-check-circle text-success fs-1"></i>
                            @else
                                <i class="ki-outline ki-cross-circle text-danger fs-1"></i>
                            @endif
                        </div>
                        
                        <div class="fw-mormal timeline-content ps-3 pb-4">
                            <div class="d-flex align-items-center mb-1">
                                <div class="symbol symbol-30px symbol-circle me-2">
                                    @if($approval->approver?->avatar_path)
                                        <img src="{{ $approval->approver->avatar_url }}" alt="{{ $approval->approver->name }}" style="object-fit: cover;" onerror="this.outerHTML='<span class=\'symbol-label bg-light-info text-info fs-6 fw-bold\'>{{ substr($approval->approver?->name ?? 'A', 0, 1) }}</span>'" />
                                    @else
                                        <span class="symbol-label bg-light-info text-info fs-6 fw-bold">{{ substr($approval->approver?->name ?? 'A', 0, 1) }}</span>
                                    @endif
                                </div>
                                <span class="text-gray-800 fw-bold">{{ $approval->approver?->name }}</span>
                                <span class="badge badge-light-{{ $approval->decision === 'approved' ? 'success' : 'danger' }} ms-2 text-capitalize">{{ $approval->decision }}</span>
                            </div>
                            <div class="text-gray-700 mt-2 bg-light p-3 rounded border border-dashed border-gray-300">
                                <i class="ki-outline ki-messages text-gray-500 me-1"></i> {{ $approval->notes }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-metronic.card>
    @endif
@endsection
