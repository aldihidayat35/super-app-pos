@extends('layouts.metronic.app')

@section('title', 'Histori Perubahan Harga - ' . config('app.name'))
@section('page_title', 'Histori Perubahan Harga')

@section('toolbar_actions')
    <a href="{{ route('pricing.product-prices.index') }}" class="btn btn-sm btn-light-primary me-2">
        <i class="ki-outline ki-price-tag fs-5 me-1"></i> Pengaturan Harga Produk
    </a>
    <a href="{{ route('pricing.history.export', request()->query()) }}" class="btn btn-sm btn-light-success">
        <i class="ki-outline ki-file-down fs-5 me-1"></i> Export CSV
    </a>
@endsection

@section('content')
    {{-- Card Menu Navigasi Internal Modul Harga & Margin --}}
    <div class="row row-cols-1 row-cols-sm-2 g-4 mb-6">
        <div class="col">
            <a href="{{ route('pricing.product-prices.index') }}" class="card card-flush h-100 hover-elevate-up border border-gray-200 bg-body text-decoration-none shadow-xs">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-light-primary me-3 flex-shrink-0">
                        <span class="symbol-label"><i class="ki-outline ki-price-tag fs-2 text-primary"></i></span>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fs-7 fw-bold text-gray-900 text-truncate">Harga Produk</div>
                        <div class="text-muted fs-8 text-truncate">Kelola harga jual &amp; batas minimum</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('pricing.history.index') }}" class="card card-flush h-100 border border-primary border-opacity-50 bg-light-primary text-decoration-none shadow-xs">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="symbol symbol-40px symbol-circle bg-primary me-3 flex-shrink-0">
                        <span class="symbol-label"><i class="ki-outline ki-time fs-2 text-white"></i></span>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fs-7 fw-bold text-gray-900 text-truncate">Histori Perubahan Harga</div>
                        <div class="text-muted fs-8 text-truncate">Riwayat dan log perubahan harga produk</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <x-metronic.card class="mb-6">
        <form method="GET" action="{{ route('pricing.history.index') }}" class="row g-3 align-items-end">
            <!-- Filter Produk -->
            <div class="col-md-3">
                <label class="form-label fw-bold text-gray-700 fs-8 mb-1">Cari Produk:</label>
                <select name="product_id" class="form-select form-select-solid form-select-sm" data-control="select2" data-placeholder="Semua produk">
                    <option value="">Semua produk</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>
                            {{ $product->sku }} — {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Lokasi -->
            <div class="col-md-2">
                <label class="form-label fw-bold text-gray-700 fs-8 mb-1">Lokasi Kerja / Cabang:</label>
                <select name="work_location_id" class="form-select form-select-solid form-select-sm">
                    <option value="">Semua Lokasi</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(($filters['work_location_id'] ?? '') == $loc->id)>
                            [{{ $loc->typeLabel() }}] {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Status Tren -->
            <div class="col-md-2">
                <label class="form-label fw-bold text-gray-700 fs-8 mb-1">Status Perubahan:</label>
                <select name="trend" class="form-select form-select-solid form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="up" @selected(($filters['trend'] ?? '') === 'up')>▲ Naik</option>
                    <option value="down" @selected(($filters['trend'] ?? '') === 'down')>▼ Turun</option>
                    <option value="initial" @selected(($filters['trend'] ?? '') === 'initial')>★ Harga Baru</option>
                </select>
            </div>

            <!-- Filter User -->
            <div class="col-md-2">
                <label class="form-label fw-bold text-gray-700 fs-8 mb-1">Pengubah:</label>
                <select name="user_id" class="form-select form-select-solid form-select-sm">
                    <option value="">Semua user</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Rentang Tanggal -->
            <div class="col-md-2">
                <label class="form-label fw-bold text-gray-700 fs-8 mb-1">Dari Tanggal:</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid form-control-sm">
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" title="Filter"><i class="ki-outline ki-filter fs-6"></i></button>
                <a href="{{ route('pricing.history.index') }}" class="btn btn-light btn-sm" title="Reset"><i class="ki-outline ki-arrows-circle fs-6"></i></a>
            </div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Riwayat Audit & Perubahan Harga">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-4 gy-3">
                <thead>
                    <tr class="fw-bold text-gray-600 text-uppercase fs-8 bg-light border-bottom border-gray-200">
                        <th class="ps-4 min-w-220px">Informasi Produk</th>
                        <th class="min-w-130px">Lokasi Cabang</th>
                        <th class="min-w-160px">Perubahan Harga</th>
                        <th class="min-w-140px text-center">Status & Tren</th>
                        <th class="min-w-130px">HPP & Modal</th>
                        <th class="min-w-140px">Sumber & Catatan</th>
                        <th class="min-w-120px pe-4">Pengubah & Waktu</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($histories as $history)
                    @php($trend = $history->trend())
                    <tr>
                        <!-- Produk -->
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                @if($history->product)
                                    <div class="symbol symbol-35px me-3 flex-shrink-0">
                                        <x-metronic.image-preview url="{{ $history->product->main_image_url }}" title="{{ $history->product->name }}" />
                                    </div>
                                @endif
                                <div>
                                    <div class="fw-bold text-gray-900 fs-7" data-mobile-primary>{{ $history->product?->name }}</div>
                                    <div class="text-muted fs-9 font-monospace" data-mobile-secondary>{{ $history->product?->sku }}</div>
                                </div>
                            </div>
                        </td>

                        <!-- Lokasi -->
                        <td>
                            @if($history->workLocation)
                                <span class="badge badge-light-primary fw-semibold fs-9">
                                    <i class="ki-outline ki-geolocation fs-9 me-1"></i> {{ $history->workLocation->name }}
                                </span>
                            @else
                                <span class="badge badge-light-secondary fs-9">Semua Lokasi (Global)</span>
                            @endif
                        </td>

                        <!-- Perubahan Harga -->
                        <td>
                            <div class="d-flex flex-column">
                                @if($history->old_price !== null && (float) $history->old_price > 0)
                                    <span class="text-muted fs-8 text-decoration-line-through">
                                        Rp {{ number_format((float) $history->old_price, 0, ',', '.') }}
                                    </span>
                                @else
                                    <span class="text-muted fs-9">Harga Perdana</span>
                                @endif
                                <span class="fw-bold fs-6 text-gray-900">
                                    Rp {{ number_format((float) $history->new_price, 0, ',', '.') }}
                                </span>
                            </div>
                        </td>

                        <!-- Status & Tren -->
                        <td class="text-center">
                            @if($trend === 'up')
                                <span class="badge badge-light-success fw-bold fs-8 py-1 px-2 d-inline-flex align-items-center">
                                    <i class="ki-outline ki-arrow-up fs-8 me-1 text-success"></i> Naik
                                    @php($diff = (float) $history->priceDifference())
                                    <span class="ms-1 fs-9 fw-semibold">(+Rp {{ number_format($diff, 0, ',', '.') }})</span>
                                </span>
                            @elseif($trend === 'down')
                                <span class="badge badge-light-danger fw-bold fs-8 py-1 px-2 d-inline-flex align-items-center">
                                    <i class="ki-outline ki-arrow-down fs-8 me-1 text-danger"></i> Turun
                                    @php($diff = abs((float) $history->priceDifference()))
                                    <span class="ms-1 fs-9 fw-semibold">(-Rp {{ number_format($diff, 0, ',', '.') }})</span>
                                </span>
                            @elseif($trend === 'initial')
                                <span class="badge badge-light-primary fw-bold fs-8 py-1 px-2 d-inline-flex align-items-center">
                                    <i class="ki-outline ki-star fs-8 me-1 text-primary"></i> Harga Baru
                                </span>
                            @else
                                <span class="badge badge-light-secondary fs-8 py-1 px-2 text-muted">= Tetap</span>
                            @endif
                        </td>

                        <!-- HPP & Modal -->
                        <td>
                            @if($history->hpp_snapshot !== null && (float) $history->hpp_snapshot > 0)
                                <div class="fs-8 text-gray-700">Modal: <strong>Rp {{ number_format((float) $history->hpp_snapshot, 0, ',', '.') }}</strong></div>
                                @php($margin = (float) $history->new_price - (float) $history->hpp_snapshot)
                                @if($margin > 0)
                                    <div class="text-success fs-9 fw-semibold"><i class="ki-outline ki-check fs-9 text-success"></i> Laba Rp {{ number_format($margin, 0, ',', '.') }}</div>
                                @elseif($margin < 0)
                                    <div class="text-danger fs-9 fw-bold"><i class="ki-outline ki-cross fs-9 text-danger"></i> Di bawah HPP</div>
                                @endif
                            @else
                                <span class="text-muted fs-8">-</span>
                            @endif
                        </td>

                        <!-- Sumber & Catatan -->
                        <td>
                            @if($history->source === 'quick_setup')
                                <span class="badge badge-light-info fs-9 py-0 px-2 fw-semibold">Quick Setup</span>
                            @elseif($history->source === 'product_prices')
                                <span class="badge badge-light-primary fs-9 py-0 px-2 fw-semibold">Harga Produk</span>
                            @elseif($history->source === 'goods_receipt')
                                <span class="badge badge-light-success fs-9 py-0 px-2 fw-semibold">Penerimaan Barang</span>
                            @else
                                <span class="badge badge-light-dark fs-9 py-0 px-2 fw-semibold">{{ ucfirst($history->source ?? 'Manual') }}</span>
                            @endif
                            <div class="text-muted fs-8 mt-1 text-truncate" style="max-width: 180px;" title="{{ $history->reason }}">
                                {{ $history->reason ?: '-' }}
                            </div>
                        </td>

                        <!-- Pengubah & Waktu -->
                        <td class="pe-4">
                            <div class="fw-semibold text-gray-800 fs-8">{{ $history->changer?->name ?? 'Sistem' }}</div>
                            <div class="text-muted fs-9">{{ $history->created_at?->format('d/m/Y H:i') }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-metronic.empty-state
                                title="Belum Ada Histori Perubahan Harga"
                                description="Setiap pembaruan harga dari Quick Setup, modul Harga Produk, maupun Penerimaan Barang akan otomatis tercatat rapi di sini." />
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $histories->links() }}
        </div>
    </x-metronic.card>
@endsection
