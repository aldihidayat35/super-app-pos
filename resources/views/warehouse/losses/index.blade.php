@extends('layouts.metronic.app')

@section('title', 'Barang Rusak, Hilang, dan Kerugian - ' . config('app.name'))
@section('page_title', 'Barang Rusak, Hilang, dan Kerugian')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-losses" title="Panduan Barang Rusak, Hilang, dan Kerugian">
        <x-slot:function><p>Mencatat barang yang rusak, hilang, kedaluwarsa, atau dikeluarkan sebagai kerugian dengan bukti dan jejak perubahan stok.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih lokasi kerja, zona/rak/bin, dan produk.</li><li>Pilih jenis kejadian serta dampaknya ke stok.</li><li>Masukkan jumlah dan bukti.</li><li>Sistem mengambil HPP produk dan menghitung nilai kerugian.</li><li>Kerugian besar menunggu persetujuan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Pindahkan ke Stok Rusak:</strong> barang masih ada secara fisik, tetapi tidak dapat digunakan atau dijual.</li><li><strong>Keluarkan dari Stok sebagai Kerugian:</strong> barang hilang, dibuang, atau tidak lagi berada dalam persediaan.</li><li><strong>HPP Saat Dicatat:</strong> biaya modal produk ketika kejadian disimpan, diambil otomatis dari master produk.</li><li><strong>Stok Tersedia:</strong> stok yang belum dialokasikan dan tidak rusak.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Setelah disetujui, sistem membuat perubahan stok append-only melalui InventoryService. Histori tidak dapat diedit.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Pastikan lokasi fisik benar.</li><li>Pilih produk dengan stok tersedia.</li><li>Periksa nilai kerugian.</li><li>Lampirkan bukti dan simpan.</li><li>Jika menunggu persetujuan, approver memeriksa sebelum stok berubah.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0">Jangan memilih “keluarkan” bila barang masih berada di gudang. Gunakan Stok Rusak agar jumlah fisik tetap dapat direkonsiliasi.</div></x-slot:warnings>
        <x-slot:example><p>Barang pecah 3 unit tetapi masih ada di bin: pilih “Pindahkan ke Stok Rusak”. Barang hilang 2 unit: pilih “Keluarkan dari Stok sebagai Kerugian”.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Barang Rusak, Hilang, dan Kerugian" description="Catat kejadian, bukti, HPP historis, dan dampak stok secara aman." />

    @if($errors->has('loss'))<div class="alert alert-danger">{{ $errors->first('loss') }}</div>@endif

    <div class="row g-6">
        <div class="col-xl-4">
            <x-metronic.card title="Catat Kejadian">
                <form method="POST" action="{{ route('warehouse.losses.store') }}" enctype="multipart/form-data" id="loss-form">@csrf
                    <x-metronic.form-group name="work_location_id" label="Lokasi Kerja" required>
                        <select id="loss-work-location" name="work_location_id" class="form-select form-select-solid" data-control="select2" required>
                            <option value="">Pilih lokasi kerja</option>
                            @foreach($workLocations as $location)<option value="{{ $location->id }}" @selected(old('work_location_id') == $location->id)>{{ $location->code }} — {{ $location->name }}</option>@endforeach
                        </select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="warehouse_location_id" label="Zona/Rak/Bin">
                        <select id="loss-bin" name="warehouse_location_id" class="form-select form-select-solid" data-control="select2" data-allow-clear="true" disabled>
                            <option value="">Tanpa zona/rak/bin khusus</option>
                            @if($selectedWarehouseLocation)<option value="{{ $selectedWarehouseLocation->id }}" selected>{{ $selectedWarehouseLocation->full_code }} — {{ $selectedWarehouseLocation->name }}</option>@endif
                        </select>
                    </x-metronic.form-group>
                    <x-metronic.form-group name="product_id" label="Produk" required>
                        <select id="loss-product" name="product_id" class="form-select form-select-solid" data-control="select2" disabled required>
                            <option value="">Pilih lokasi terlebih dahulu</option>
                            @if($selectedProduct)<option value="{{ $selectedProduct->id }}" selected data-cost="{{ $selectedProduct->cost_price }}">{{ $selectedProduct->sku }} — {{ $selectedProduct->name }}</option>@endif
                        </select>
                        <div id="loss-stock-info" class="form-text">Stok tersedia akan tampil setelah produk dipilih.</div>
                    </x-metronic.form-group>
                    <div class="row g-3">
                        <div class="col-md-6"><x-metronic.form-group name="loss_type" label="Jenis Kejadian" required><select name="loss_type" class="form-select form-select-solid" data-control="native"><option value="broken">Pecah</option><option value="lost">Hilang</option><option value="expired">Kedaluwarsa</option><option value="opname_variance">Selisih Opname</option><option value="damage">Rusak</option><option value="other">Lainnya</option></select></x-metronic.form-group></div>
                        <div class="col-md-6"><x-metronic.form-group name="disposition" label="Dampak ke Stok" required><select name="disposition" class="form-select form-select-solid" data-control="native"><option value="damage">Pindahkan ke Stok Rusak</option><option value="issue">Keluarkan dari Stok sebagai Kerugian</option></select></x-metronic.form-group></div>
                    </div>
                    <div class="form-text mb-4">Stok Rusak berarti barang masih ada secara fisik. Keluarkan sebagai Kerugian berarti barang tidak lagi berada dalam persediaan.</div>
                    <x-metronic.form-group name="quantity" label="Jumlah Barang Terdampak" required><input id="loss-quantity" type="number" step="1" min="1" name="quantity" value="{{ old('quantity') }}" class="form-control form-control-solid" required><div class="form-text">Masukkan jumlah barang yang rusak, hilang, atau terdampak.</div></x-metronic.form-group>
                    <x-metronic.form-group name="unit_cost_snapshot" label="HPP Saat Dicatat"><div class="input-group"><span class="input-group-text">Rp</span><input id="loss-cost-display" type="text" class="form-control form-control-solid" value="0" readonly></div><input id="loss-cost" type="hidden" name="unit_cost_snapshot" value="{{ old('unit_cost_snapshot', 0) }}"><div class="form-text">Diambil otomatis dari HPP produk. Nilai server tetap menjadi sumber kebenaran.</div></x-metronic.form-group>
                    <div class="rounded border border-dashed border-primary bg-light-primary p-4 mb-5"><div class="text-muted fs-7">Perkiraan Nilai Kerugian</div><div id="loss-value" class="fs-2 fw-bold text-primary">Rp0</div><div class="fs-8 text-muted">Jumlah barang terdampak × HPP saat dicatat.</div></div>
                    <x-metronic.form-group name="reason" label="Penyebab dan Catatan"><textarea name="reason" class="form-control form-control-solid" rows="3">{{ old('reason') }}</textarea></x-metronic.form-group>
                    <x-metronic.form-group name="evidence" label="Bukti Pendukung"><input type="file" name="evidence" class="form-control form-control-solid" accept=".jpg,.jpeg,.png,.pdf"><div class="form-text">Foto atau PDF, maksimum 4 MB.</div></x-metronic.form-group>
                    <button class="btn btn-primary w-100">Simpan Kejadian</button>
                </form>
            </x-metronic.card>
        </div>

        <div class="col-xl-8">
            <x-metronic.card title="Riwayat Kerugian">
                <form method="GET" class="row g-3 mb-5">
                    <div class="col-md-4"><select name="loss_type" class="form-select form-select-solid"><option value="">Semua jenis</option><option value="broken" @selected(($filters['loss_type'] ?? '') === 'broken')>Pecah</option><option value="lost" @selected(($filters['loss_type'] ?? '') === 'lost')>Hilang</option><option value="expired" @selected(($filters['loss_type'] ?? '') === 'expired')>Kedaluwarsa</option><option value="damage" @selected(($filters['loss_type'] ?? '') === 'damage')>Rusak</option></select></div>
                    <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option><option value="pending_approval" @selected(($filters['status'] ?? '') === 'pending_approval')>Menunggu Persetujuan</option><option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Disetujui</option></select></div>
                    <div class="col-md-4"><button class="btn btn-light-primary w-100">Terapkan Filter</button></div>
                </form>
                <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Dokumen</th><th>Produk dan Lokasi</th><th>Dampak</th><th class="text-end">Jumlah</th><th class="text-end">Nilai Kerugian <i class="ki-outline ki-information-5 fs-7" data-bs-toggle="tooltip" title="Jumlah barang terdampak dikalikan HPP produk ketika kejadian dicatat."></i></th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
                    @forelse($losses as $loss)
                        @php
                            $key = $loss->product_id.'|'.$loss->work_location_id.'|'.($loss->warehouse_location_id ?? 'null');
                            $currentAvailable = $availability->get($key, '0.0000');
                            $insufficient = \App\Support\Decimal::compare($currentAvailable, (string) $loss->quantity) < 0;
                        @endphp
                        <tr>
                            <td class="fw-bold">{{ $loss->number }}<div class="text-muted fs-8">{{ $loss->reported_at?->format('d/m/Y H:i') }}</div></td>
                            <td>
                                {{ $loss->product?->name }}
                                <div class="text-muted">{{ $loss->workLocation?->name }} / {{ $loss->warehouseLocation?->full_code ?: 'Tanpa bin' }}</div>
                                @if($loss->status === \App\Enums\InventoryLossStatus::PENDING_APPROVAL)
                                    <div class="fs-8 {{ $insufficient ? 'text-danger fw-bold' : 'text-success' }}">Stok tersedia saat ini: {{ qty($currentAvailable) }}</div>
                                @endif
                            </td>
                            <td>{{ $loss->disposition === 'issue' ? 'Keluar sebagai kerugian' : 'Pindah ke stok rusak' }}</td>
                            <td class="text-end">{{ qty($loss->quantity) }}</td>
                            <td class="text-end fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($loss->loss_value) }}</td>
                            <td><x-metronic.status-badge :status="$loss->status" /></td>
                            <td class="text-end">
                                @can('approve', $loss)
                                    @if($insufficient)
                                        <span class="badge badge-light-danger d-block mb-2">Stok tidak cukup</span>
                                        <button type="button" class="btn btn-sm btn-light-danger" disabled>Persetujuan Diblokir</button>
                                    @else
                                        <form id="approve-loss-{{ $loss->id }}" method="POST" action="{{ route('warehouse.losses.approve', $loss) }}">
                                            @csrf
                                            <button type="button" class="btn btn-sm btn-success" data-confirm data-confirm-form="approve-loss-{{ $loss->id }}" data-confirm-title="Setujui kerugian?" data-confirm-text="Tindakan ini akan membuat perubahan stok.">Setujui</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-metronic.empty-state title="Belum ada catatan kerugian" description="Kejadian yang disimpan akan tampil di sini." /></td></tr>
                    @endforelse
                </tbody></table></div>
                {{ $losses->links() }}
            </x-metronic.card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const $ = window.jQuery;
    if (typeof $?.fn?.select2 !== 'function') return;
    const endpoint = @json(route('warehouse.losses.options'));
    const work = $('#loss-work-location');
    const bin = $('#loss-bin');
    const product = $('#loss-product');
    const quantity = document.getElementById('loss-quantity');
    const cost = document.getElementById('loss-cost');
    const costDisplay = document.getElementById('loss-cost-display');
    const value = document.getElementById('loss-value');
    const stockInfo = document.getElementById('loss-stock-info');
    const initialBin = @json(old('warehouse_location_id'));
    const initialProduct = @json(old('product_id'));
    let selectedProductData = null;
    let loadingBins = false;

    const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(number || 0));
    const refreshValue = () => { value.textContent = formatRupiah(Number(quantity.value || 0) * Number(cost.value || 0)); };
    const reset = (select, label, disabled = true) => select.empty().append(new Option(label, '', true, true)).prop('disabled', disabled).trigger('change.select2');
    const populate = (select, results, label, selectedId, allowEmpty = false) => {
        select.empty().append(new Option(label, '', !selectedId, !selectedId));
        results.forEach(item => select.append(new Option(item.text, item.id, String(item.id) === String(selectedId || ''), String(item.id) === String(selectedId || ''))));
        select.prop('disabled', results.length === 0 && !allowEmpty).trigger('change.select2');
    };
    const get = async (params) => {
        const response = await window.appFetch(`${endpoint}?${new URLSearchParams(params)}`);
        if (!response.ok) throw new Error('Data pilihan tidak dapat dimuat.');
        return response.json();
    };
    const loadProducts = async (selectedId = null) => {
        reset(product, 'Memuat produk...'); selectedProductData = null; cost.value = 0; costDisplay.value = '0'; refreshValue();
        if (!work.val()) return reset(product, 'Pilih lokasi kerja terlebih dahulu');
        try {
            const data = await get({ type: 'products', work_location_id: work.val(), warehouse_location_id: bin.val() || '' });
            product.data('items', data.results); populate(product, data.results, 'Pilih produk', selectedId);
            if (selectedId) product.trigger('change');
        } catch (error) { reset(product, 'Gagal memuat produk'); stockInfo.textContent = error.message; }
    };
    const loadBins = async (selectedId = null, selectedProductId = null) => {
        reset(bin, 'Memuat zona/rak/bin...');
        if (!work.val()) return reset(bin, 'Pilih lokasi kerja terlebih dahulu');
        try {
            const data = await get({ type: 'locations', work_location_id: work.val() });
            loadingBins = true;
            populate(bin, data.results, 'Tanpa zona/rak/bin khusus', selectedId, true);
            loadingBins = false;
            await loadProducts(selectedProductId);
        } catch (error) { loadingBins = false; reset(bin, 'Gagal memuat zona/rak/bin'); stockInfo.textContent = error.message; }
    };
    work.on('change.loss', () => loadBins());
    bin.on('change.loss', () => { if (!loadingBins) loadProducts(); });
    product.on('change.loss', () => {
        selectedProductData = (product.data('items') || []).find(item => String(item.id) === String(product.val()));
        cost.value = selectedProductData?.cost_price || 0;
        costDisplay.value = new Intl.NumberFormat('id-ID').format(Number(cost.value));
        stockInfo.textContent = selectedProductData ? `Stok tersedia: ${selectedProductData.available} ${selectedProductData.unit}` : 'Stok tersedia akan tampil setelah produk dipilih.';
        refreshValue();
    });
    quantity.addEventListener('input', refreshValue);
    if (work.val()) loadBins(initialBin, initialProduct); else { reset(bin, 'Pilih lokasi kerja terlebih dahulu'); reset(product, 'Pilih lokasi kerja terlebih dahulu'); }
});
</script>
@endpush
