@if($isReserveable)
<div class="modal fade" id="reserveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fw-bold">Reserve Stok - {{ $order->number }}</h2>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <i class="ki-outline ki-cross fs-2"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('warehouse.b2b-orders.reserve', $order) }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info mb-4">
                        <i class="ki-outline ki-information fs-4 me-2"></i>
                        Reservation akan mengalokasikan stok untuk pesanan ini. Stok akan di-lock hingga expired atau converted.
                    </div>
                    <div class="row g-4">
                        @foreach($order->items as $item)
                            <div class="col-md-6">
                                <x-metronic.form-group :name="'approved_quantities[' . $item->id . ']'" :label="'Qty disetujui · ' . $item->product_name_snapshot">
                                    <input type="number" step="0.0001" min="0" name="approved_quantities[{{ $item->id }}]" value="{{ old('approved_quantities.' . $item->id, $item->quantity) }}" class="form-control form-control-solid">
                                </x-metronic.form-group>
                            </div>
                        @endforeach
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <x-metronic.form-group name="reservation_expires_at" label="Reservation Expiry">
                                <input type="datetime-local" name="reservation_expires_at" value="{{ old('reservation_expires_at', now()->addHours(24)->format('Y-m-d\TH:i')) }}" class="form-control form-control-solid">
                            </x-metronic.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-metronic.form-group name="shipping_cost_amount" label="Biaya Kirim">
                                <input type="number" step="0.01" min="0" name="shipping_cost_amount" value="{{ old('shipping_cost_amount', $order->shipping_cost_amount) }}" class="form-control form-control-solid">
                            </x-metronic.form-group>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_partial" value="1" @checked(old('allow_partial'))>
                            <span class="form-check-label">Izinkan parsial (allow shortage)</span>
                        </label>
                    </div>
                    <div class="mb-0">
                        <x-metronic.form-group name="internal_note" label="Catatan Internal">
                            <textarea name="internal_note" rows="2" class="form-control form-control-solid" placeholder="Catatan internal untuk tim gudang">{{ old('internal_note') }}</textarea>
                        </x-metronic.form-group>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ki-outline ki-check fs-5 me-2"></i>Reserve Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
