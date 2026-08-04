@if($isPackable)
<div class="modal fade" id="packModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fw-bold">Mulai Packing - {{ $order->number }}</h2>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <i class="ki-outline ki-cross fs-2"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('warehouse.b2b-orders.pack', $order) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info mb-4">
                        <i class="ki-outline ki-information fs-4 me-2"></i>
                        Pesanan akan dipindahkan ke status packing. Siapkan barang untuk pengiriman.
                    </div>
                    <x-metronic.form-group name="internal_note" label="Catatan Packing">
                        <textarea name="internal_note" rows="3" class="form-control form-control-solid" placeholder="Catatan internal saat packing">{{ old('internal_note', $order->internal_note) }}</textarea>
                    </x-metronic.form-group>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info">
                        <i class="ki-outline ki-package fs-5 me-2"></i>Mulai Packing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
