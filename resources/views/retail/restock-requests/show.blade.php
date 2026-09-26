@extends('layouts.metronic.app')

@section('title', 'Detail Restok '.$restockRequest->number.' - '.config('app.name'))
@section('page_title', 'Detail Permintaan Restok')
@section('toolbar_actions')
    <a href="{{ route('retail.restock-requests.index') }}" class="btn btn-sm btn-light">
        <i class="ki-outline ki-arrow-left fs-5 me-1"></i> Kembali ke Daftar
    </a>
    @if($restockRequest->status === \App\Enums\RestockRequestStatus::APPROVED->value && empty($restockRequest->stockTransfers))
        <a href="{{ route('retail.restock-requests.convert', $restockRequest) }}" class="btn btn-sm btn-primary ms-3">
            <i class="ki-outline ki-arrow-right-left fs-5 me-1"></i> Buat Transfer Stok
        </a>
    @endif
@endsection

@section('content')
<div class="row g-5">
    <!-- Kolom Kiri: Info Utama & Daftar Item -->
    <div class="col-xl-8">
        
        <!-- Header Info Utama -->
        <div class="card shadow-sm border border-gray-200 mb-5">
            <div class="card-body p-6">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-5 mb-7">
                    <div class="d-flex flex-column">
                        <span class="text-muted fw-semibold fs-7 mb-1">NO. PERMINTAAN</span>
                        <h2 class="text-gray-900 fw-bold m-0 fs-2">{{ $restockRequest->number }}</h2>
                        <span class="text-muted fs-8 mt-1"><i class="ki-outline ki-calendar fs-8 me-1"></i> {{ $restockRequest->created_at?->format('d M Y, H:i') }}</span>
                    </div>
                    <div>
                        <x-metronic.status-badge :status="$restockRequest->status" />
                    </div>
                </div>

                <div class="separator border-gray-300 border-dashed mb-5"></div>

                <div class="row g-5">
                    <div class="col-sm-6 col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted fw-semibold fs-8 mb-1">CABANG TUJUAN</span>
                            <span class="text-gray-900 fw-bold fs-6">
                                <i class="ki-outline ki-shop text-primary me-1"></i> {{ $restockRequest->branch?->name }}
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted fw-semibold fs-8 mb-1">GUDANG SUMBER</span>
                            <span class="text-gray-900 fw-bold fs-6">
                                {{ $restockRequest->sourceWarehouse?->name ?: 'Belum ditentukan' }}
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="d-flex flex-column">
                            <span class="text-muted fw-semibold fs-8 mb-1">REQUESTER</span>
                            <span class="text-gray-900 fw-bold fs-6">
                                {{ $restockRequest->requester?->name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daftar Item Diminta -->
        <div class="d-flex align-items-center justify-content-between mb-4 mt-8">
            <h3 class="fs-4 fw-bold text-gray-900 m-0">
                Daftar Item Diminta
            </h3>
            <span class="badge badge-light-primary fw-bold fs-6 px-3 py-2">
                {{ $restockRequest->items->count() }} Produk
            </span>
        </div>

        <div class="d-flex flex-column gap-4">
            @foreach($restockRequest->items as $item)
                <div class="card border border-gray-200 shadow-sm item-card">
                    <div class="card-body p-5">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-5">
                            <!-- Info Produk -->
                            <div class="d-flex align-items-center gap-4 min-w-250px">
                                <div class="bg-light rounded-3 overflow-hidden border border-gray-200 flex-shrink-0" style="width: 60px; height: 60px;">
                                    <x-metronic.image-preview url="{{ $item->product?->main_image_url ?? \App\Models\Product::defaultImageUrl() }}" title="{{ $item->product?->name }}" classes="w-100 h-100 object-fit-cover" size="" />
                                </div>
                                <div class="d-flex flex-column">
                                    <strong class="text-gray-900 fs-5 mb-1">{{ $item->product?->sku }}</strong>
                                    <div class="text-muted fs-7">{{ $item->product?->name }}</div>
                                </div>
                            </div>
                            
                            <!-- Prioritas & Alasan -->
                            <div class="d-flex flex-column min-w-150px">
                                <span class="text-muted fs-8 mb-1">Prioritas & Alasan</span>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge badge-light-{{ $item->priority === 'tinggi' ? 'danger' : ($item->priority === 'sedang' ? 'warning' : 'info') }} fs-8">
                                        {{ ucfirst($item->priority) }}
                                    </span>
                                </div>
                                <span class="text-gray-600 fs-8 fst-italic text-truncate" style="max-width: 150px;" title="{{ $item->notes }}">
                                    {{ $item->notes ?: 'Tidak ada catatan' }}
                                </span>
                            </div>

                            <!-- Qty Diminta & Disetujui -->
                            <div class="d-flex flex-column align-items-end min-w-100px border-start border-gray-200 ps-5">
                                <div class="d-flex justify-content-between w-100 mb-2">
                                    <span class="text-muted fs-8">Diminta</span>
                                    <strong class="text-gray-800">{{ qty($item->quantity_requested) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between w-100">
                                    <span class="text-muted fs-8 me-3">Disetujui</span>
                                    <strong class="text-primary fs-5">{{ qty($item->quantity_approved) }}</strong>
                                </div>
                                <span class="text-muted fs-9 mt-1">{{ $item->product?->baseUnit?->symbol ?: '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Kolom Kanan: Status, Waktu, History -->
    <div class="col-xl-4">
        
        <!-- Info Tambahan -->
        <div class="card shadow-sm border border-gray-200 mb-5">
            <div class="card-header bg-light-primary py-4 min-h-50px">
                <h3 class="card-title text-primary fw-bold m-0 fs-5">Informasi Tambahan</h3>
            </div>
            <div class="card-body p-5">
                <div class="d-flex justify-content-between mb-4">
                    <span class="text-muted fs-7">Prioritas Permintaan</span>
                    <span class="badge badge-light-{{ $restockRequest->priority === 'tinggi' ? 'danger' : ($restockRequest->priority === 'sedang' ? 'warning' : 'info') }} fw-bold fs-7">
                        {{ ucfirst($restockRequest->priority) }}
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-4">
                    <span class="text-muted fs-7">Dibutuhkan Tanggal</span>
                    <span class="text-gray-900 fw-bold fs-7">
                        @if($restockRequest->needed_at)
                            <i class="ki-outline ki-calendar text-gray-500 me-1"></i> {{ $restockRequest->needed_at->format('d M Y') }}
                        @else
                            -
                        @endif
                    </span>
                </div>
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Catatan Permintaan</span>
                    <div class="bg-light rounded p-3 text-gray-700 fs-7 border border-gray-200 fst-italic">
                        {{ $restockRequest->notes ?: 'Tidak ada catatan.' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Terkait -->
        <div class="card shadow-sm border border-gray-200 mb-5">
            <div class="card-header bg-light-success py-4 min-h-50px">
                <h3 class="card-title text-success fw-bold m-0 fs-5">
                    <i class="ki-outline ki-truck text-success fs-3 me-2"></i> Transfer Stok Terkait
                </h3>
            </div>
            <div class="card-body p-5">
                @forelse($restockRequest->stockTransfers as $transfer)
                    <a href="{{ route('warehouse.stock-transfers.show', $transfer) }}" class="d-flex align-items-center justify-content-between bg-light-dark border border-gray-300 rounded p-4 text-decoration-none transition-hover mb-3">
                        <div class="d-flex flex-column">
                            <strong class="text-gray-900 fs-6 mb-1">{{ $transfer->number }}</strong>
                            <span class="text-muted fs-8"><i class="ki-outline ki-calendar fs-8 me-1"></i> {{ $transfer->transfer_date?->format('d M Y') }}</span>
                        </div>
                        <x-metronic.status-badge :status="$transfer->status" />
                    </a>
                @empty
                    <div class="text-center p-5 text-muted border border-gray-200 border-dashed rounded">
                        <i class="ki-outline ki-information-5 fs-2x text-gray-400 mb-2 d-block"></i>
                        Belum ada dokumen transfer stok yang dibuat dari permintaan ini.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Riwayat Status -->
        <div class="card shadow-sm border border-gray-200">
            <div class="card-header bg-light py-4 min-h-50px">
                <h3 class="card-title text-gray-800 fw-bold m-0 fs-5">
                    <i class="ki-outline ki-time text-gray-600 fs-3 me-2"></i> Riwayat Status
                </h3>
            </div>
            <div class="card-body p-5">
                <div class="timeline">
                    @forelse($restockRequest->statusHistories as $history)
                        <div class="timeline-item align-items-start mb-4">
                            <div class="timeline-label fw-bold text-gray-800 fs-8" style="width: 60px;">
                                {{ $history->created_at?->format('H:i') }}
                            </div>
                            <div class="timeline-badge">
                                <i class="fa fa-genderless text-primary fs-1"></i>
                            </div>
                            <div class="timeline-content d-flex flex-column ps-3">
                                <strong class="text-gray-800 mb-1 fs-7">
                                    {{ $history->from_status ?: 'Awal' }} &rarr; {{ $history->to_status }}
                                </strong>
                                <span class="text-muted fs-8 mb-1">oleh {{ $history->actor?->name ?: 'Sistem' }} ({{ $history->created_at?->format('d M Y') }})</span>
                                @if($history->notes)
                                    <div class="bg-light-info rounded p-2 text-info fs-8 mt-1">
                                        {{ $history->notes }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted">Belum ada riwayat perubahan status.</div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    /* Styling khusus timeline jika Metronic timeline class kurang padding */
    .timeline {
        display: flex;
        flex-direction: column;
    }
    .timeline-item {
        display: flex;
        position: relative;
    }
    .timeline-badge {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 1px;
        z-index: 1;
        width: 14px;
        height: 14px;
        background-color: var(--bs-body-bg);
        border-radius: 100%;
        margin-left: 10px;
        margin-right: 10px;
    }
    .timeline-item:not(:last-child):before {
        content: "";
        position: absolute;
        left: 76px; /* label width + padding */
        top: 14px;
        bottom: -20px;
        width: 2px;
        background-color: var(--bs-gray-300);
    }
    .transition-hover:hover {
        background-color: var(--bs-gray-200) !important;
        border-color: var(--bs-gray-400) !important;
    }
</style>
@endsection
