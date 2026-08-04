@if($isRejectable)
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fw-bold text-danger">Reject Pesanan - {{ $order->number }}</h2>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <i class="ki-outline ki-cross fs-2"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('warehouse.b2b-orders.reject', $order) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-danger mb-4">
                        <i class="ki-outline ki-error fs-4 me-2"></i>
                        Pesanan akan ditolak dan reservation aktif akan dilepas. Tindakan ini tidak dapat dibatalkan.
                    </div>
                    <x-metronic.form-group name="reason" label="Alasan Penolakan" required>
                        <textarea name="reason" rows="4" class="form-control form-control-solid" required placeholder="Contoh: Stok tidak tersedia, limit kredit terlampaui.">{{ old('reason', $order->reject_reason) }}</textarea>
                    </x-metronic.form-group>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="ki-outline ki-cross fs-5 me-2"></i>Reject Pesanan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
