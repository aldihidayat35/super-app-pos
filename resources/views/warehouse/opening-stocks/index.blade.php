@extends('layouts.metronic.app')

@section('title', 'Pendataan Stok Lokasi - ' . config('app.name'))
@section('page_title', 'Pendataan Stok Lokasi')

@section('toolbar_actions')
    @can('opening_stocks.create')
        <a href="{{ route('warehouse.opening-stocks.import.index') }}" class="btn btn-light-primary me-2">
            <i class="ki-outline ki-file-up fs-2 me-1"></i> Import Excel
        </a>
    @endcan
    <a href="{{ route('warehouse.opening-stocks.export', ['work_location_id' => $selectedLocationId]) }}" class="btn btn-light-success">
        <i class="ki-outline ki-file-down fs-2 me-1"></i> Export CSV
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="opening-stock" title="Panduan Pendataan Stok Lokasi">
        <x-slot:function><p>Mencatat jumlah fisik sekarang untuk produk yang sudah ada di database sebagai alternatif import Excel.</p></x-slot:function>
        <x-slot:workflow><p>Pilih lokasi, tandai produk yang telah dihitung, isi jumlah fisik, simpan draft, lalu ajukan. Draft staf diperiksa kepala lokasi; kepala dapat memposting draftnya sendiri.</p></x-slot:workflow>
        <x-slot:warnings><div class="alert alert-warning mb-0">Jumlah yang dimasukkan adalah saldo akhir, bukan jumlah tambahan. Jika sistem 20 dan fisik 27, mutasinya hanya +7.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    @error('opening_stock')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <div class="row g-5 mb-6">
        <div class="col-md-3"><x-metronic.card title="Produk Aktif"><div class="fs-2 fw-bold">{{ $activeProductCount }}</div><div class="text-muted">Produk dalam database</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Sudah Diposting"><div class="fs-2 fw-bold text-success">{{ $completedProductCount }}</div><div class="text-muted">Baris pendataan selesai</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Draft"><div class="fs-2 fw-bold text-warning">{{ $draftCount }}</div><div class="text-muted">Belum diajukan</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Menunggu"><div class="fs-2 fw-bold text-primary">{{ $pendingCount }}</div><div class="text-muted">Perlu diperiksa</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Pilih Lokasi dan Produk" class="mb-6">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3"><label class="form-label">Gudang atau toko</label><select name="work_location_id" class="form-select" onchange="this.form.submit()">@foreach($locations as $location)<option value="{{ $location->id }}" @selected($selectedLocationId === $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-lg-3"><label class="form-label">Cari produk</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="SKU atau nama produk"></div>
            <div class="col-lg-2"><label class="form-label">Kategori</label><select name="category_id" class="form-select"><option value="">Semua</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int)request('category_id') === $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="col-lg-2"><label class="form-label">Riwayat</label><select name="count_status" class="form-select"><option value="">Semua</option><option value="not_posted" @selected(request('count_status')==='not_posted')>Belum pernah diposting</option><option value="posted" @selected(request('count_status')==='posted')>Sudah pernah diposting</option></select></div>
            <div class="col-lg-2"><button class="btn btn-light-primary">Terapkan</button></div>
        </form>
        <div class="alert {{ $access?->isCurrentlyActive() ? 'alert-success' : 'alert-warning' }} mt-4 mb-0">
            @if($access?->isCurrentlyActive()) Akses pendataan aktif sampai {{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB. @else Lokasi terkunci. Super Admin harus mengaktifkan Akses Koreksi Stok untuk lokasi ini. @endif
        </div>
    </x-metronic.card>

    @if($access?->isCurrentlyActive() && auth()->user()->can('opening_stocks.create'))
        <x-metronic.card title="Input Stok Sekarang" class="mb-6">
            @if($bins->isEmpty())
                <div class="alert alert-warning mb-0">Lokasi ini belum mempunyai rak atau lokasi penyimpanan aktif. Lengkapi lokasi penyimpanan sebelum melakukan pendataan.</div>
            @else
                <form method="POST" action="{{ route('warehouse.opening-stocks.store') }}" id="opening-stock-form">@csrf
                    <input type="hidden" name="work_location_id" value="{{ $selectedLocationId }}">
                    <div class="alert alert-info">Cari dan pilih banyak produk sekaligus. Semua produk terpilih akan disimpan dalam satu dokumen. Jumlah 0 tetap dapat dicatat.</div>
                    <div class="row g-4 align-items-end mb-5">
                        <div class="col-lg-7">
                            <label class="form-label fw-semibold">Pilih banyak produk</label>
                            <select id="opening-product-picker" class="form-select" multiple data-control="select2" data-close-on-select="false" data-placeholder="Ketik SKU atau nama produk">
                                @foreach($products as $product)
                                    @php($productStocks = $stockByProduct->get($product->id, collect()))
                                    <option value="{{ $product->id }}"
                                        @selected(array_key_exists((string)$product->id, old('items', [])))
                                        data-sku="{{ $product->sku }}"
                                        data-name="{{ $product->name }}"
                                        data-unit="{{ $product->baseUnit?->symbol ?? $product->baseUnit?->name }}"
                                        data-cost="{{ $product->cost_price }}"
                                        data-old-bin="{{ old('items.'.$product->id.'.warehouse_location_id') }}"
                                        data-old-physical="{{ old('items.'.$product->id.'.physical_qty') }}"
                                        data-old-note="{{ old('items.'.$product->id.'.note') }}"
                                        data-stocks='@json($productStocks->mapWithKeys(fn($stock) => [(string)$stock->warehouse_location_id => (string)$stock->quantity_on_hand]))'>
                                        {{ $product->sku }} — {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="d-flex gap-2 mt-2"><button type="button" class="btn btn-sm btn-light-primary" id="select-all-products">Pilih Semua Hasil Filter ({{ $products->count() }})</button><button type="button" class="btn btn-sm btn-light" id="clear-products">Kosongkan Pilihan</button></div>
                        </div>
                        <div class="col-lg-5">
                            <label class="form-label fw-semibold">Terapkan rak ke semua produk</label>
                            <div class="input-group"><select id="bulk-bin" class="form-select" data-searchable-fallback="true">@foreach($bins as $bin)<option value="{{ $bin->id }}">{{ $bin->full_code }} - {{ $bin->name }}</option>@endforeach</select><button type="button" class="btn btn-light-primary" id="apply-bulk-bin">Terapkan</button></div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3"><div class="fw-semibold">Produk yang akan dicatat</div><span class="badge badge-light-primary" id="selected-product-count">0 produk</span></div>
                    <div class="table-responsive"><table class="table table-row-bordered align-middle"><thead><tr><th>Produk</th><th>Rak / tempat simpan</th><th class="text-end">Jumlah di Sistem</th><th style="min-width:170px">Jumlah Fisik Sekarang</th><th>Catatan</th><th></th></tr></thead><tbody id="opening-product-rows">
                        <tr id="opening-empty-row"><td colspan="6" class="text-center text-muted py-8">Pilih produk melalui dropdown pencarian di atas.</td></tr>
                    </tbody></table></div>
                    <div class="mt-5"><label class="form-label">Catatan dokumen</label><textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Pendataan awal toko sebelum mulai operasional"></textarea></div>
                    <button class="btn btn-primary mt-4" id="save-opening-stock" disabled>Simpan Draft Massal</button>
                </form>
            @endif
        </x-metronic.card>
    @endif

    <x-metronic.card title="Draft, Persetujuan, dan Riwayat">
        <div class="d-flex justify-content-end mb-4"><a href="{{ route('warehouse.opening-stocks.export', ['work_location_id'=>$selectedLocationId]) }}" class="btn btn-light-success">Export CSV</a></div>
        <div class="table-responsive"><table class="table table-row-bordered align-middle"><thead><tr><th>Nomor</th><th>Lokasi</th><th>Status</th><th>Pembuat</th><th>Produk</th><th>Waktu</th><th></th></tr></thead><tbody>
            @forelse($documents as $document)<tr><td class="fw-bold">{{ $document->number }}</td><td>{{ $document->workLocation?->name }}</td><td><span class="badge badge-light-primary">{{ ['draft'=>'Draft','pending_approval'=>'Menunggu Persetujuan','completed'=>'Selesai','rejected'=>'Ditolak','cancelled'=>'Dibatalkan'][$document->status] ?? $document->status }}</span></td><td>{{ $document->creator?->name }}</td><td>{{ $document->items->count() }}</td><td>{{ $document->created_at?->format('d/m/Y H:i') }}</td><td><a href="{{ route('warehouse.opening-stocks.show', $document) }}" class="btn btn-sm btn-light-primary">Detail</a></td></tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-10">Belum ada pendataan stok untuk lokasi ini.</td></tr>@endforelse
        </tbody></table></div>{{ $documents->links() }}
    </x-metronic.card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const picker = document.getElementById('opening-product-picker');
    const body = document.getElementById('opening-product-rows');
    if (!picker || !body) return;
    const binOptions = @json($bins->map(fn($bin) => ['id' => $bin->id, 'label' => $bin->full_code.' - '.$bin->name])->values());
    const count = document.getElementById('selected-product-count');
    const save = document.getElementById('save-opening-stock');
    const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 });

    function selectedOptions() { return Array.from(picker.selectedOptions); }
    function updateRow(row) {
        const option = picker.querySelector('option[value="' + CSS.escape(row.dataset.productId) + '"]');
        const stocks = JSON.parse(option?.dataset.stocks || '{}');
        const bin = row.querySelector('.bin-select');
        const current = Number(stocks[bin.value] || 0);
        const physical = row.querySelector('.physical-input');
        row.querySelector('.system-qty').textContent = number.format(current);
        row.querySelector('.difference-preview').textContent = 'Selisih: ' + (physical.value === '' ? '-' : number.format(Number(physical.value) - current));
    }
    function createRow(option) {
        const row = document.createElement('tr'); row.className = 'opening-row'; row.dataset.productId = option.value;
        row.innerHTML = '<td><strong class="product-sku"></strong><br><span class="product-name"></span><br><small class="text-muted product-meta"></small><input type="hidden" class="product-id"></td><td><select class="form-select bin-select"></select></td><td class="text-end fw-semibold system-qty">0</td><td><input type="number" step="0.0001" min="0" class="form-control physical-input" required><div class="small text-muted mt-1 difference-preview">Selisih: -</div></td><td><input class="form-control note-input" placeholder="Opsional"></td><td><button type="button" class="btn btn-sm btn-light-danger remove-product" aria-label="Hapus produk">×</button></td>';
        row.querySelector('.product-sku').textContent = option.dataset.sku || '';
        row.querySelector('.product-name').textContent = option.dataset.name || '';
        row.querySelector('.product-meta').textContent = (option.dataset.unit || '') + ' · HPP Rp ' + new Intl.NumberFormat('id-ID').format(Number(option.dataset.cost || 0));
        row.querySelector('.product-id').name = 'items[' + option.value + '][product_id]'; row.querySelector('.product-id').value = option.value;
        const bin = row.querySelector('.bin-select'); bin.name = 'items[' + option.value + '][warehouse_location_id]';
        binOptions.forEach(function (item) { const entry = document.createElement('option'); entry.value = item.id; entry.textContent = item.label; bin.appendChild(entry); });
        const physical = row.querySelector('.physical-input'); physical.name = 'items[' + option.value + '][physical_qty]';
        const note = row.querySelector('.note-input'); note.name = 'items[' + option.value + '][note]';
        if (option.dataset.oldBin) bin.value = option.dataset.oldBin;
        if (option.dataset.oldPhysical !== undefined && option.dataset.oldPhysical !== '') physical.value = option.dataset.oldPhysical;
        if (option.dataset.oldNote) note.value = option.dataset.oldNote;
        bin.addEventListener('change', function () { updateRow(row); }); physical.addEventListener('input', function () { updateRow(row); });
        row.querySelector('.remove-product').addEventListener('click', function () { option.selected = false; window.$?.(picker).trigger('change'); render(); });
        updateRow(row); return row;
    }
    function render() {
        const selected = selectedOptions(); body.innerHTML = '';
        if (!selected.length) { const empty = document.createElement('tr'); empty.innerHTML = '<td colspan="6" class="text-center text-muted py-8">Pilih produk melalui dropdown pencarian di atas.</td>'; body.appendChild(empty); }
        selected.forEach(function (option) { body.appendChild(createRow(option)); });
        count.textContent = selected.length + ' produk'; save.disabled = selected.length === 0;
        window.GudangTokoSelect2?.initialize(body);
    }
    picker.addEventListener('change', render);
    document.getElementById('select-all-products')?.addEventListener('click', function () { Array.from(picker.options).forEach(function (option) { option.selected = true; }); window.$?.(picker).trigger('change'); render(); });
    document.getElementById('clear-products')?.addEventListener('click', function () { Array.from(picker.options).forEach(function (option) { option.selected = false; }); window.$?.(picker).trigger('change'); render(); });
    document.getElementById('apply-bulk-bin')?.addEventListener('click', function () { const value = document.getElementById('bulk-bin').value; body.querySelectorAll('.bin-select').forEach(function (select) { select.value = value; window.$?.(select).trigger('change'); select.closest('tr') && updateRow(select.closest('tr')); }); });
    render();
});
</script>
@endpush
