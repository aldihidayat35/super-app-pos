@if($isShipable)
<div class="modal fade" id="shipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fw-bold">Kirim Pesanan - {{ $order->number }}</h2>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <i class="ki-outline ki-cross fs-2"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('warehouse.b2b-orders.ship', $order) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-success mb-4">
                        <i class="ki-outline ki-information fs-4 me-2"></i>
                        Stok yang di-reserve akan dikonversi menjadi issue stock. Pastikan barang sudah siap dikirim.
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <x-metronic.form-group name="courier_name" label="Kurir / Ekspedisi">
                                <input type="text" name="courier_name" maxlength="120" value="{{ old('courier_name', $order->courier_name) }}" class="form-control form-control-solid" placeholder="Contoh: JNE, SiCepat, pickup sendiri">
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-metronic.form-group name="internal_note" label="Catatan Pengiriman">
                                <textarea name="internal_note" rows="3" class="form-control form-control-solid" placeholder="Catatan internal untuk pengiriman">{{ old('internal_note', $order->internal_note) }}</textarea>
                            </x-metronic.form-group>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="ki-outline ki-delivery fs-5 me-2"></i>Kirim & Konversi Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
