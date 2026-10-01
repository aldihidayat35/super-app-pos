@extends('layouts.metronic.app')

@section('title', 'Buat Transfer Stok - '.config('app.name'))
@section('page_title', 'Buat Transfer Stok')

@section('content')
<form method="POST" action="{{ route('retail.restock-requests.convert', $restockRequest) }}" id="convert-restock-form">
    @csrf
    
    <div class="row g-5">
        <!-- Kolom Kiri: Daftar Item -->
        <div class="col-xl-8">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h3 class="fs-4 fw-bold text-gray-900 m-0">
                    Daftar Item Transfer
                </h3>
                <span class="badge badge-light-primary fw-bold fs-6 px-3 py-2">
                    {{ $restockRequest->items->count() }} Produk
                </span>
            </div>

            <!-- Peringatan Stok (Muncul jika ada error) -->
            <div id="stock-warning" class="alert alert-danger d-none align-items-center p-5 mb-5 shadow-sm border border-danger border-dashed rounded">
                <i class="ki-outline ki-shield-cross fs-2hx text-danger me-4"></i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-danger">Peringatan Kuantitas!</h4>
                    <span>Kuantitas transfer melebihi ketersediaan stok fisik atau melebihi kuantitas yang disetujui. Harap sesuaikan kuantitas di bawah ini.</span>
                </div>
            </div>

            <div class="d-flex flex-column gap-4">
                @foreach($restockRequest->items as $item)
                    <div class="card border border-dashed border-gray-300 shadow-sm item-card" data-product="{{ $item->product_id }}">
                        <div class="card-body p-5">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-5">
                                <!-- Info Produk -->
                                <div class="d-flex align-items-center gap-4">
                                    <div class="bg-light rounded-3 overflow-hidden border border-gray-200 flex-shrink-0" style="width: 60px; height: 60px;">
                                        <x-metronic.image-preview url="{{ $item->product?->main_image_url ?? \App\Models\Product::defaultImageUrl() }}" title="{{ $item->product?->name }}" classes="w-100 h-100 object-fit-cover" size="" />
                                    </div>
                                    <div class="d-flex flex-column">
                                        <strong class="text-gray-900 fs-5 mb-1">{{ $item->product?->sku }}</strong>
                                        <div class="text-muted fs-7 mb-2">{{ $item->product?->name }}</div>
                                        <div class="d-flex align-items-center gap-3 fs-8">
                                            <span class="text-gray-600">Disetujui: <strong class="text-gray-800">{{ qty($item->quantity_approved) }} {{ $item->product?->baseUnit?->symbol }}</strong></span>
                                            <span class="bullet bullet-dot bg-gray-400"></span>
                                            <span class="text-gray-600">Stok di Gudang: 
                                                <strong class="source-balance text-gray-800">0</strong> 
                                                {{ $item->product?->baseUnit?->symbol }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Input Qty Transfer -->
                                <div class="d-flex flex-column align-items-end min-w-150px">
                                    <label class="form-label fs-8 text-muted fw-semibold mb-2">Qty Transfer</label>
                                    <div class="input-group input-group-solid flex-nowrap border border-gray-300 rounded" style="width: 140px;">
                                        <input type="number" 
                                            min="0.0001" 
                                            max="{{ qty_input($item->quantity_approved) }}" 
                                            step="any" 
                                            name="items[{{ $item->id }}][quantity_transfer]" 
                                            value="{{ old("items.{$item->id}.quantity_transfer", qty_input($item->quantity_approved)) }}" 
                                            class="form-control text-end transfer-qty fw-bold fs-5 bg-transparent border-0" 
                                            required>
                                        <span class="input-group-text bg-transparent border-0 text-muted fw-bold">{{ $item->product?->baseUnit?->symbol }}</span>
                                    </div>
                                    <div class="invalid-feedback d-none text-danger fs-8 mt-1 text-end qty-error">Melebihi stok!</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        
        <!-- Kolom Kanan: Pengaturan & Submit -->
        <div class="col-xl-4">
            <div class="card shadow-sm border border-gray-200 position-sticky" style="top: 100px;">
                <div class="card-header bg-light-primary py-4 min-h-50px">
                    <h3 class="card-title text-primary fw-bold m-0 fs-5">
                        <i class="ki-outline ki-clipboard text-primary fs-3 me-2"></i> Ringkasan Transfer
                    </h3>
                </div>
                
                <div class="card-body p-6">
                    <!-- Info Permintaan -->
                    <div class="mb-7">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted fs-7">No. Permintaan</span>
                            <span class="text-gray-900 fw-bold fs-6">{{ $restockRequest->number }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted fs-7">Cabang Tujuan</span>
                            <span class="text-gray-900 fw-bold fs-6">{{ $restockRequest->branch?->name }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted fs-7">Status</span>
                            <x-metronic.status-badge :status="$restockRequest->status" />
                        </div>
                    </div>
                    
                    <div class="separator border-gray-300 border-dashed mb-7"></div>
                    
                    <!-- Pilihan Gudang & Lokasi -->
                    <div class="mb-5">
                        <x-metronic.form-group name="source_warehouse_id" label="Gudang Sumber" required class="mb-4">
                            <select name="source_warehouse_id" id="convert-warehouse" class="form-select form-select-solid border-gray-300" data-control="select2" data-placeholder="Pilih gudang sumber..." required>
                                <option value=""></option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((int) old('source_warehouse_id', $restockRequest->source_warehouse_id) === $warehouse->id)>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-metronic.form-group>

                        <x-metronic.form-group name="source_warehouse_location_id" label="Lokasi Ambil (Bin)" required>
                            <select name="source_warehouse_location_id" id="convert-location" class="form-select form-select-solid border-gray-300" data-control="select2" data-placeholder="Pilih lokasi/bin..." required data-current-val="{{ old('source_warehouse_location_id') }}">
                                <option value=""></option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" data-warehouse="{{ $location->warehouse_id }}" @selected((int) old('source_warehouse_location_id') === $location->id)>
                                        {{ $location->full_code }} - {{ $location->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted fs-8 mt-2">Stok fisik produk akan diambil dari lokasi ini.</div>
                        </x-metronic.form-group>
                    </div>
                </div>
                
                <div class="card-footer p-6 bg-light-subtle">
                    <button type="submit" class="btn btn-primary w-100 mb-3" id="btn-submit" data-confirm="Buat transfer stok dari permintaan ini?">
                        <i class="ki-outline ki-arrow-right-left fs-4 me-1"></i> Buat Transfer Stok
                    </button>
                    <a href="{{ route('retail.restock-requests.show', $restockRequest) }}" class="btn btn-light-danger w-100">
                        Batal
                    </a>
                </div>
            </div>
            
            <div class="alert alert-dismissible bg-light-info border border-info border-dashed d-flex flex-column flex-sm-row p-5 mt-5">
                <i class="ki-outline ki-information-5 fs-2hx text-info mb-4 mb-sm-0 me-4"></i>
                <div class="d-flex flex-column pe-0 pe-sm-10">
                    <h5 class="mb-1 text-info">Info Sistem</h5>
                    <span class="fs-7 text-info opacity-75">Stok pada lokasi sumber akan langsung di-reserve ketika transfer ini dibuat dan disetujui.</span>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const warehouse = $('#convert-warehouse');
    const location = $('#convert-location'); 
    if (!warehouse.length || !location.length) return;
    
    const balances = @json($balances); 
    // Save original options
    const originalLocationOptions = location.find('option').toArray().map(opt => ({
        val: opt.value,
        text: opt.text,
        warehouse: opt.dataset.warehouse
    })).filter(opt => opt.val !== "");
    
    const refreshLocations = () => { 
        const selectedWarehouseId = warehouse.val();
        
        location.empty();
        location.append('<option value=""></option>');
        
        let hasSelected = false;
        const currentVal = location.attr('data-current-val') || location.val();
        
        originalLocationOptions.forEach(opt => {
            if (opt.warehouse === selectedWarehouseId) {
                const isSelected = opt.val === currentVal;
                if (isSelected) hasSelected = true;
                location.append(new Option(opt.text, opt.val, false, isSelected));
            }
        });
        
        if (!hasSelected) {
            location.val('').trigger('change.select2');
        } else {
            location.trigger('change.select2');
        }
        
        refreshBalances();
    };
    
    const refreshBalances = () => { 
        let warning = false; 
        const locVal = location.val();
        
        document.querySelectorAll('.item-card').forEach(card => { 
            const productId = card.dataset.product;
            const available = locVal ? Number(balances[`${locVal}-${productId}`] || 0) : 0; 
            
            const balanceEl = card.querySelector('.source-balance');
            balanceEl.textContent = available.toLocaleString('id-ID', {maximumFractionDigits:4}); 
            
            const input = card.querySelector('.transfer-qty');
            const qty = Number(input.value || 0); 
            const maxApproved = Number(input.max || 0);
            
            const isExceedingStock = qty > available && locVal;
            const isExceedingApproved = qty > maxApproved;
            const hasError = isExceedingStock || isExceedingApproved;
            
            // Highlight card and input
            if (hasError) {
                card.classList.add('border-danger', 'bg-light-danger');
                card.classList.remove('border-gray-300');
                input.classList.add('text-danger');
                balanceEl.classList.add('text-danger');
            } else {
                card.classList.remove('border-danger', 'bg-light-danger');
                card.classList.add('border-gray-300');
                input.classList.remove('text-danger');
                balanceEl.classList.remove('text-danger');
            }
            
            // Show/hide error text
            const errorMsg = card.querySelector('.qty-error');
            if (errorMsg) {
                if (isExceedingStock) {
                    errorMsg.classList.remove('d-none');
                    errorMsg.textContent = 'Melebihi ketersediaan stok fisik!';
                    input.parentElement.classList.add('border-danger');
                } else if (isExceedingApproved) {
                    errorMsg.classList.remove('d-none');
                    errorMsg.textContent = 'Melebihi batas yang disetujui!';
                    input.parentElement.classList.add('border-danger');
                } else if (!locVal && qty > 0) {
                    errorMsg.classList.remove('d-none');
                    errorMsg.textContent = 'Pilih lokasi ambil!';
                    input.parentElement.classList.add('border-warning');
                } else {
                    errorMsg.classList.add('d-none');
                    input.parentElement.classList.remove('border-danger', 'border-warning');
                }
            }

            warning = warning || hasError || (!locVal && qty > 0); 
        }); 
        
        document.getElementById('stock-warning').classList.toggle('d-none', !warning); 
        
        // Disable submit button if warning
        const btnSubmit = document.getElementById('btn-submit');
        if (btnSubmit) {
            if (warning) {
                btnSubmit.classList.add('disabled');
                btnSubmit.disabled = true;
            } else {
                btnSubmit.classList.remove('disabled');
                btnSubmit.disabled = false;
            }
        }
    };
    
    warehouse.on('change', refreshLocations); 
    location.on('change', function() {
        location.attr('data-current-val', location.val());
        refreshBalances();
    });
    
    document.querySelectorAll('.transfer-qty').forEach(input => input.addEventListener('input', refreshBalances)); 
    
    // Initial setup
    refreshLocations();
});
</script>
@endpush
