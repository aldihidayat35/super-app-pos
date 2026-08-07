@extends('layouts.metronic.app')

@section('title', 'Permintaan Pembelian - ' . config('app.name'))
@section('page_title', 'Permintaan Pembelian')

@section('page_guide')
    <x-metronic.page-guide id="purchasing-request-index" title="Panduan Permintaan Pembelian">
        <x-slot:function><p>Purchase Request adalah permintaan internal agar perusahaan membeli barang untuk kebutuhan gudang. Dokumen ini berbeda dari pesanan pelanggan B2B.</p></x-slot:function>
        <x-slot:workflow><ol><li>Staf internal mengajukan kebutuhan barang.</li><li>Pengguna dengan izin approval meninjau permintaan.</li><li>Purchasing memilih supplier dan membuat Purchase Order.</li></ol></x-slot:workflow>
        <x-slot:warnings><p>Rekomendasi stok minimum hanya menjadi bahan review. Jumlah pembelian tetap harus ditentukan berdasarkan kebutuhan operasional.</p></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <div class="row g-5">
        @can('create', \App\Models\PurchaseRequest::class)
            <div class="col-xl-4">
                <x-metronic.card title="Buat Permintaan Internal">
                    <div class="text-muted fs-7 mb-5">Ajukan kebutuhan pembelian untuk gudang yang berada dalam akses lokasi kerja Anda.</div>
                    <form method="POST" action="{{ route('purchasing.requests.store') }}">
                        @csrf
                        <x-metronic.form-group name="warehouse_id" label="Gudang" required help="Gudang yang membutuhkan barang dan akan menjadi tujuan penerimaan barang.">
                            <select name="warehouse_id" class="form-select form-select-solid" required>
                                <option value="">Pilih gudang</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->code }} - {{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </x-metronic.form-group>

                        <x-metronic.form-group name="priority" label="Prioritas" required help="Rendah: dapat dijadwalkan. Normal: kebutuhan rutin. Tinggi: perlu segera diproses. Mendesak: berisiko mengganggu operasional.">
                            <select name="priority" class="form-select form-select-solid" required>
                                <option value="low" @selected(old('priority') === 'low')>Rendah</option>
                                <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                                <option value="high" @selected(old('priority') === 'high')>Tinggi</option>
                                <option value="urgent" @selected(old('priority') === 'urgent')>Mendesak</option>
                            </select>
                        </x-metronic.form-group>

                        <x-metronic.form-group name="reason" label="Alasan Permintaan" required help="Jelaskan mengapa barang perlu dibeli, misalnya stok menipis, kebutuhan operasional, atau permintaan khusus.">
                            <textarea name="reason" rows="3" class="form-control form-control-solid" required>{{ old('reason') }}</textarea>
                        </x-metronic.form-group>

                        <div class="border rounded p-4 mb-5">
                            <div class="fw-bold mb-1">Produk yang Dibutuhkan</div>
                            <div class="text-muted fs-8 mb-4">Barang yang akan diajukan untuk dibeli dari supplier.</div>
                            <x-metronic.form-group name="items.0.product_id" label="Produk" required help="Pilih barang yang dibutuhkan gudang.">
                                <select name="items[0][product_id]" class="form-select form-select-solid" required>
                                    <option value="">Pilih produk</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected(old('items.0.product_id') == $product->id)>{{ $product->sku }} - {{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </x-metronic.form-group>
                            <input type="hidden" name="items[0][unit_id]" value="{{ old('items.0.unit_id') }}">
                            <x-metronic.form-group name="items.0.quantity" label="Jumlah Dibutuhkan" required help="Jumlah barang yang diminta untuk dibeli. Satuan dasar produk digunakan jika satuan tidak ditentukan.">
                                <input name="items[0][quantity]" value="{{ old('items.0.quantity') }}" type="number" step="0.0001" min="0.0001" class="form-control form-control-solid" required>
                            </x-metronic.form-group>
                            <x-metronic.form-group name="items.0.reason" label="Catatan Item" help="Catatan khusus untuk produk ini, bila diperlukan.">
                                <input name="items[0][reason]" value="{{ old('items.0.reason') }}" class="form-control form-control-solid">
                            </x-metronic.form-group>
                        </div>

                        <button class="btn btn-primary w-100">
                            <i class="ki-outline ki-send fs-5 me-2"></i>Ajukan Permintaan
                        </button>
                    </form>
                </x-metronic.card>
            </div>
        @endcan

        <div class="@can('create', \App\Models\PurchaseRequest::class) col-xl-8 @else col-12 @endcan">
            <x-metronic.card title="Rekomendasi dari Stok Minimum">
                <div class="alert alert-light-primary d-flex align-items-start mb-4">
                    <i class="ki-outline ki-information-5 fs-2 text-primary me-3"></i>
                    <div>
                        <div class="fw-semibold">Rekomendasi untuk kebutuhan internal gudang</div>
                        <div class="text-muted fs-7">Produk berikut perlu ditinjau untuk pembelian karena stok tersedia sudah mendekati atau berada di bawah batas minimum. Daftar ini bukan pesanan pelanggan B2B dan tidak otomatis menentukan jumlah pembelian.</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead>
                            <tr class="text-muted fw-bold text-uppercase fs-7">
                                <th>Produk</th>
                                <th>Lokasi Stok</th>
                                <th class="text-end">Stok Fisik</th>
                                <th class="text-end">Stok Tersedia</th>
                                <th class="text-end">Batas Minimum</th>
                                <th>Status Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recommendations as $stock)
                                @php
                                    $available = (float) $stock->quantity_on_hand - (float) $stock->quantity_reserved - (float) $stock->quantity_damaged;
                                @endphp
                                <tr>
                                    <td><span class="fw-semibold">{{ $stock->product?->name }}</span><div class="text-muted fs-8">{{ $stock->product?->sku }} / {{ $stock->product?->baseUnit?->name }}</div></td>
                                    <td>{{ $stock->workLocation?->name }}<div class="text-muted fs-8">{{ $stock->warehouseLocation?->full_code ?: 'Tanpa lokasi rak/bin' }}</div></td>
                                    <td class="text-end">{{ qty($stock->quantity_on_hand) }}</td>
                                    <td class="text-end fw-semibold text-danger">{{ qty($available) }}</td>
                                    <td class="text-end">{{ qty($stock->product?->minimum_stock) }}</td>
                                    <td><span class="badge badge-light-warning">Perlu review purchasing</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-metronic.empty-state title="Tidak ada rekomendasi" description="Produk akan muncul saat stok tersedia mencapai atau berada di bawah batas minimum." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            <x-metronic.card title="Daftar Permintaan" class="mt-5">
                <form method="GET" class="row g-3 mb-5">
                    <div class="col-sm-8 col-md-5">
                        <label class="form-label fs-7">Status Permintaan</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="">Semua status</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-4 col-md-3 d-flex align-items-end"><button class="btn btn-light-primary w-100">Terapkan Filter</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Dokumen</th><th>Gudang</th><th>Pemohon</th><th>Prioritas</th><th>Status</th><th>Item</th><th class="text-end">Tindakan</th></tr></thead>
                        <tbody>
                            @forelse ($requests as $purchaseRequest)
                                <tr>
                                    <td><a href="{{ route('purchasing.requests.show', $purchaseRequest) }}" class="fw-bold text-primary">{{ $purchaseRequest->number }}</a><div class="text-muted fs-8">{{ $purchaseRequest->submitted_at?->format('d/m/Y H:i') }}</div></td>
                                    <td>{{ $purchaseRequest->warehouse?->name }}</td>
                                    <td>{{ $purchaseRequest->requester?->name }}</td>
                                    <td>{{ ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Mendesak'][$purchaseRequest->priority] ?? ucfirst($purchaseRequest->priority) }}</td>
                                    <td><x-metronic.status-badge :status="$purchaseRequest->status" /></td>
                                    <td>{{ $purchaseRequest->items->count() }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('purchasing.requests.show', $purchaseRequest) }}" class="btn btn-sm btn-light-primary">Lihat Proses</a>
                                        @if ($purchaseRequest->convertedPurchaseOrder)
                                            @can('view', $purchaseRequest->convertedPurchaseOrder)
                                                <a href="{{ route('purchasing.purchase-orders.show', $purchaseRequest->convertedPurchaseOrder) }}" class="btn btn-sm btn-light-success">Lihat PO</a>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-metronic.empty-state title="Belum ada permintaan" description="Permintaan manual yang diajukan akan tampil di sini." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $requests->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection
