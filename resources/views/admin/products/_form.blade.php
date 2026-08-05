@php
    $isEdit = $product->exists;
    $oldUnits = old('units', $unitRows ?: []);
    $oldBarcodes = old('barcodes', $barcodeRows ?: [['code' => '', 'type' => 'barcode']]);

    if ($oldUnits === [] && $units->isNotEmpty()) {
        $oldUnits = [[
            'unit_id' => $product->base_unit_id ?: $units->first()->id,
            'conversion_factor' => 1,
            'name' => '',
            'is_sellable' => true,
            'is_active' => true,
        ]];
    }
@endphp

<form method="POST" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.products.update', $product) : route('admin.products.store') }}" novalidate>
    @csrf
    @if($isEdit) @method('PUT') @endif

    <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab_info">Informasi</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_units">Satuan</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_barcode">Barcode</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_photo">Foto</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_stock">Pengaturan Stok</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab_pricing">Harga Dasar</a></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab_info">
            <x-metronic.card title="Informasi Produk">
                <div class="row">
                    <div class="col-md-4"><x-metronic.form-group name="sku" label="SKU/Kode"><input name="sku" value="{{ old('sku', $product->sku) }}" class="form-control @error('sku') is-invalid @enderror" placeholder="Kosongkan untuk otomatis" @readonly($product->has_transactions)></x-metronic.form-group></div>
                    <div class="col-md-8"><x-metronic.form-group name="name" label="Nama Produk" required><input name="name" value="{{ old('name', $product->name) }}" class="form-control @error('name') is-invalid @enderror" required></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="category_id" label="Kategori" required><select name="category_id" class="form-select @error('category_id') is-invalid @enderror"><option value="">Pilih kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>@endforeach</select></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="subcategory_id" label="Subkategori"><select name="subcategory_id" class="form-select"><option value="">Tidak ada</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) old('subcategory_id', $product->subcategory_id) === $category->id)>{{ $category->name }}</option>@endforeach</select></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="brand_id" label="Merek"><select name="brand_id" class="form-select"><option value="">Tanpa merek</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((int) old('brand_id', $product->brand_id) === $brand->id)>{{ $brand->name }}</option>@endforeach</select></x-metronic.form-group></div>
                    <div class="col-md-3"><x-metronic.form-group name="model" label="Model"><input name="model" value="{{ old('model', $product->model) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-3"><x-metronic.form-group name="size" label="Ukuran"><input name="size" value="{{ old('size', $product->size) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-3"><x-metronic.form-group name="color" label="Warna"><input name="color" value="{{ old('color', $product->color) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-3"><x-metronic.form-group name="material" label="Material"><input name="material" value="{{ old('material', $product->material) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="status" label="Status" required><select name="status" class="form-select">@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', $product->status?->value ?? 'active') === $value)>{{ $label }}</option>@endforeach</select></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="base_unit_id" label="Satuan Dasar" required><select name="base_unit_id" class="form-select @error('base_unit_id') is-invalid @enderror">@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((int) old('base_unit_id', $product->base_unit_id) === $unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</select></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="default_warehouse_id" label="Lokasi Default"><select name="default_warehouse_id" class="form-select"><option value="">Belum ditentukan</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((int) old('default_warehouse_id', $product->default_warehouse_id) === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></x-metronic.form-group></div>
                </div>
                <x-metronic.form-group name="description" label="Deskripsi"><textarea name="description" rows="3" class="form-control">{{ old('description', $product->description) }}</textarea></x-metronic.form-group>
            </x-metronic.card>
        </div>

        <div class="tab-pane fade" id="tab_units">
            <x-metronic.card title="Satuan dan Konversi">
                <div class="alert bg-light-primary border border-primary border-dashed d-flex align-items-start p-5 mb-6">
                    <i class="ki-outline ki-information-5 fs-2 text-primary me-4 mt-1"></i>
                    <div>
                        <div class="fw-bold text-gray-900 mb-1">Cara mengatur satuan produk</div>
                        <div class="text-gray-700">
                            Satuan dasar wajib menggunakan faktor <strong>1</strong>. Tambahkan satuan turunan jika produk juga dijual dalam kemasan lain,
                            misalnya <strong>1 Pack = 12 Pcs</strong>.
                        </div>
                    </div>
                </div>

                @if($units->isEmpty())
                    <div class="alert alert-warning d-flex align-items-center mb-6">
                        <i class="ki-outline ki-information-5 fs-2 me-3"></i>
                        <span>Belum ada satuan aktif. Tambahkan atau aktifkan satuan pada menu Master Produk terlebih dahulu.</span>
                    </div>
                @endif

                <div id="unitsContainer" class="d-flex flex-column gap-4">
                    @foreach($oldUnits as $index => $row)
                        <div class="product-unit-row unit-row border border-gray-300 border-dashed rounded p-5" data-index="{{ $index }}">
                            <div class="d-flex align-items-center justify-content-between mb-5">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge badge-light-primary fs-7" data-unit-row-number>Satuan #{{ $loop->iteration }}</span>
                                    <span class="text-muted fs-7">Atur satuan dan faktor konversinya</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-remove-unit" title="Hapus satuan" aria-label="Hapus satuan">
                                    <i class="ki-outline ki-trash fs-2"></i>
                                </button>
                            </div>

                            <div class="row g-5 align-items-end">
                                <div class="col-12 col-lg-4">
                                    <label class="form-label fw-semibold required">Pilih Satuan</label>
                                    <select name="units[{{ $index }}][unit_id]" class="form-select form-select-solid @error("units.$index.unit_id") is-invalid @enderror" data-control="native" data-unit-select required>
                                        <option value="">Pilih satuan</option>
                                        @foreach($units as $unit)
                                            <option value="{{ $unit->id }}" @selected((int) ($row['unit_id'] ?? 0) === $unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>
                                        @endforeach
                                    </select>
                                    @error("units.$index.unit_id")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 col-md-6 col-lg-3">
                                    <label class="form-label fw-semibold required">Faktor ke Satuan Dasar</label>
                                    <input type="number" step="0.000001" min="0.000001" name="units[{{ $index }}][conversion_factor]" value="{{ $row['conversion_factor'] ?? 1 }}" class="form-control form-control-solid @error("units.$index.conversion_factor") is-invalid @enderror" required>
                                    @error("units.$index.conversion_factor")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 col-md-6 col-lg-3">
                                    <label class="form-label fw-semibold">Label Tampilan</label>
                                    <input name="units[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" class="form-control form-control-solid" placeholder="Contoh: pcs atau pack">
                                </div>
                                <div class="col-12 col-lg-2">
                                    <div class="product-unit-switch bg-light rounded px-4">
                                        <input type="hidden" name="units[{{ $index }}][is_sellable]" value="0">
                                        <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                                            <input class="form-check-input" type="checkbox" name="units[{{ $index }}][is_sellable]" value="1" @checked($row['is_sellable'] ?? true)>
                                            <span class="form-check-label fw-semibold text-gray-700">Bisa dijual</span>
                                        </label>
                                        <input type="hidden" name="units[{{ $index }}][is_active]" value="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mt-5">
                    <div class="text-muted fs-7">Setiap satuan hanya boleh dipilih satu kali untuk produk yang sama.</div>
                    <button type="button" class="btn btn-sm btn-light-primary" id="addUnitBtn" @disabled($units->isEmpty())>
                        <i class="ki-outline ki-plus fs-4"></i>
                        Tambah Satuan
                    </button>
                </div>
            </x-metronic.card>
        </div>

        <div class="tab-pane fade" id="tab_barcode">
            <x-metronic.card title="Barcode dan QR">
                @foreach($oldBarcodes as $index => $barcode)
                    <div class="row mb-3"><input type="hidden" name="barcodes[{{ $index }}][id]" value="{{ $barcode['id'] ?? '' }}"><div class="col-md-8"><x-metronic.form-group name="barcodes.{{ $index }}.code" label="Kode Barcode/QR"><input name="barcodes[{{ $index }}][code]" value="{{ $barcode['code'] ?? '' }}" class="form-control"></x-metronic.form-group></div><div class="col-md-4"><x-metronic.form-group name="barcodes.{{ $index }}.type" label="Tipe"><select name="barcodes[{{ $index }}][type]" class="form-select"><option value="barcode" @selected(($barcode['type'] ?? 'barcode') === 'barcode')>Barcode</option><option value="qr" @selected(($barcode['type'] ?? '') === 'qr')>QR</option></select></x-metronic.form-group></div></div>
                @endforeach
                <div class="form-text">Tambahkan baris barcode baru lewat edit setelah produk tersimpan jika butuh banyak barcode.</div>
            </x-metronic.card>
        </div>

        <div class="tab-pane fade" id="tab_photo">
            <x-metronic.card title="Foto Produk">
                <div class="mb-4">
                    <label class="form-label fw-semibold">Unggah Foto Baru (maksimal 3 foto total)</label>
                    <input type="file" name="photos[]" accept="image/*" class="form-control" multiple id="photoUpload" {{ $product->images->count() >= 3 ? 'disabled' : '' }}>
                    <div class="form-text">Pilih beberapa foto sekaligus. Maksimal 4 MB per foto. Total maksimal 3 foto.</div>
                    @if($product->images->count() >= 3)
                        <div class="alert alert-warning mt-2">Batas maksimal 3 foto telah tercapai. Hapus foto lama untuk menambah baru.</div>
                    @endif
                </div>

                @if($product->images->isNotEmpty() || $product->main_image_path)
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Foto Tersimpan ({{ $product->images->count() }} foto)</label>
                        <div class="row g-3" id="existingPhotos">
                            @if($product->main_image_path && $product->images->where('path', $product->main_image_path)->isEmpty())
                                <div class="col-md-3" data-image-id="main" data-is-primary="1" data-path="{{ $product->main_image_path }}">
                                    <div class="card h-100 border-primary border-2">
                                        <img src="{{ asset('storage/' . $product->main_image_path) }}" class="card-img-top" alt="{{ $product->name }}" style="height: 150px; object-fit: cover;">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge badge-light-primary primary-photo-badge"><i class="ki-outline ki-star fs-7 me-1"></i>Foto Utama</span>
                                                <button type="button" class="btn btn-sm btn-danger btn-remove-photo" data-path="{{ $product->main_image_path }}" {{ $product->images->count() === 0 ? 'disabled' : '' }} title="Hapus foto">
                                                    <i class="ki-outline ki-trash fs-6"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @foreach($product->images as $image)
                                <div class="col-md-3" data-image-id="{{ $image->id }}" data-is-primary="{{ $image->is_primary ? '1' : '0' }}" data-path="{{ $image->path }}" data-primary-url="{{ route('admin.products.images.primary', [$product, $image]) }}">
                                    <div class="card h-100 {{ $image->is_primary ? 'border-primary border-2' : '' }}">
                                        <img src="{{ asset('storage/' . $image->path) }}" class="card-img-top" alt="{{ $image->alt_text ?? $product->name }}" style="height: 150px; object-fit: cover;">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                @if($image->is_primary)
                                                    <span class="badge badge-light-primary primary-photo-badge"><i class="ki-outline ki-star fs-7 me-1"></i>Foto Utama</span>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-light-primary btn-set-primary" data-id="{{ $image->id }}" data-path="{{ $image->path }}" title="Jadikan foto utama">
                                                        <i class="ki-outline ki-star fs-6 me-1"></i>Jadikan Utama
                                                    </button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-danger btn-remove-photo" data-id="{{ $image->id }}" data-path="{{ $image->path }}" title="Hapus foto">
                                                    <i class="ki-outline ki-trash fs-6"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div id="newPhotoPreview" class="row g-3"></div>
                <input type="hidden" name="remove_photos" id="removePhotos" value="">
            </x-metronic.card>
        </div>

        <div class="tab-pane fade" id="tab_stock">
            <x-metronic.card title="Pengaturan Stok">
                <div class="alert bg-light-info border border-info border-dashed d-flex align-items-start p-5 mb-6">
                    <i class="ki-outline ki-information-5 fs-2 text-info me-4 mt-1"></i>
                    <div class="text-gray-700">
                        Nilai di bawah merupakan batas operasional master produk. Saldo stok dan mutasi tidak dibuat dari form ini;
                        keduanya tercatat otomatis ketika ada penerimaan, transfer, penjualan, retur, atau stok opname.
                    </div>
                </div>
                <div class="row g-5">
                    <div class="col-md-4"><x-metronic.form-group name="minimum_order" label="Minimum Order"><input type="number" step="1" min="0" name="minimum_order" value="{{ old('minimum_order', qty_input($product->minimum_order ?? 0)) }}" class="form-control form-control-solid"></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="minimum_stock" label="Minimum Stock"><input type="number" step="1" min="0" name="minimum_stock" value="{{ old('minimum_stock', qty_input($product->minimum_stock ?? 0)) }}" class="form-control form-control-solid"></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="safety_stock" label="Safety Stock"><input type="number" step="1" min="0" name="safety_stock" value="{{ old('safety_stock', qty_input($product->safety_stock ?? 0)) }}" class="form-control form-control-solid"></x-metronic.form-group></div>
                    <div class="col-md-6"><x-metronic.form-group name="weight" label="Berat"><input type="number" step="0.0001" min="0" name="weight" value="{{ old('weight', $product->weight) }}" class="form-control form-control-solid"><div class="form-text">Gunakan satuan kilogram.</div></x-metronic.form-group></div>
                    <div class="col-md-6"><x-metronic.form-group name="volume" label="Volume"><input type="number" step="0.0001" min="0" name="volume" value="{{ old('volume', $product->volume) }}" class="form-control form-control-solid"><div class="form-text">Gunakan satuan meter kubik.</div></x-metronic.form-group></div>
                </div>
            </x-metronic.card>
        </div>

        <div class="tab-pane fade" id="tab_pricing">
            <x-metronic.card title="Harga Dasar Produk">
                <div class="alert bg-light-warning border border-warning border-dashed d-flex align-items-start p-5 mb-6">
                    <i class="ki-outline ki-information-5 fs-2 text-warning me-4 mt-1"></i>
                    <div class="text-gray-700">
                        HPP dan harga minimum disimpan pada master produk. Harga jual per channel, cabang, price ring, periode,
                        dan minimum kuantitas dikelola melalui modul Harga agar histori serta approval tetap tercatat.
                    </div>
                </div>
                <div class="row g-5">
                    <div class="col-md-6">
                        <x-metronic.form-group name="cost_price" label="HPP Saat Ini" help="Harga pokok awal. Setelah penerimaan barang, HPP dapat diperbarui otomatis menggunakan moving weighted average.">
                            <div class="input-group input-group-solid">
                                <span class="input-group-text">Rp</span>
                                <input type="number" step="0.01" min="0" name="cost_price" value="{{ old('cost_price', $product->cost_price ?? 0) }}" class="form-control form-control-solid @error('cost_price') is-invalid @enderror">
                            </div>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-6">
                        <x-metronic.form-group name="minimum_price" label="Harga Jual Minimum" help="Batas harga terendah sebelum transaksi membutuhkan penolakan atau approval sesuai aturan harga.">
                            <div class="input-group input-group-solid">
                                <span class="input-group-text">Rp</span>
                                <input type="number" step="0.01" min="0" name="minimum_price" value="{{ old('minimum_price', $product->minimum_price ?? 0) }}" class="form-control form-control-solid @error('minimum_price') is-invalid @enderror">
                            </div>
                        </x-metronic.form-group>
                    </div>
                </div>

                @if($isEdit && auth()->user()?->can('prices.view'))
                    <div class="d-flex justify-content-end border-top pt-5">
                        <a href="{{ route('pricing.product-prices.index', ['product_id' => $product->id]) }}" class="btn btn-light-primary">
                            <i class="ki-outline ki-price-tag fs-4 me-2"></i>Kelola Harga Jual dan Price Ring
                        </a>
                    </div>
                @endif
            </x-metronic.card>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-3 mt-6"><a href="{{ $isEdit ? route('admin.products.show', $product) : route('admin.products.index') }}" class="btn btn-light">Batal</a><button class="btn btn-primary">{{ $isEdit ? 'Simpan Produk' : 'Buat Produk' }}</button></div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const requestedTab = window.location.hash
        ? document.querySelector(`a[data-bs-toggle="tab"][href="${window.location.hash}"]`)
        : null;

    if (requestedTab && window.bootstrap?.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(requestedTab).show();
    }

    // ========== UNIT MANAGEMENT ==========
    let unitIndex = {{ count($oldUnits) }};
    const availableUnits = @json($units->map(fn ($unit) => ['id' => $unit->id, 'text' => $unit->name.' ('.$unit->symbol.')'])->values());
    const unitsContainer = document.getElementById('unitsContainer');
    const addUnitBtn = document.getElementById('addUnitBtn');

    function createUnitRow(index) {
        const row = document.createElement('div');
        row.className = 'product-unit-row unit-row border border-gray-300 border-dashed rounded p-5';
        row.dataset.index = index;

        row.innerHTML = `
            <div class="d-flex align-items-center justify-content-between mb-5">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge badge-light-primary fs-7" data-unit-row-number></span>
                    <span class="text-muted fs-7">Atur satuan dan faktor konversinya</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-light-danger btn-remove-unit" title="Hapus satuan" aria-label="Hapus satuan">
                    <i class="ki-outline ki-trash fs-2"></i>
                </button>
            </div>
            <div class="row g-5 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label fw-semibold required">Pilih Satuan</label>
                    <select name="units[${index}][unit_id]" class="form-select form-select-solid" data-control="native" data-unit-select required>
                        <option value="">Pilih satuan</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-semibold required">Faktor ke Satuan Dasar</label>
                    <input type="number" step="0.000001" min="0.000001" name="units[${index}][conversion_factor]" value="1" class="form-control form-control-solid" required>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">Label Tampilan</label>
                    <input name="units[${index}][name]" value="" class="form-control form-control-solid" placeholder="Contoh: pcs atau pack">
                </div>
                <div class="col-12 col-lg-2">
                    <div class="product-unit-switch bg-light rounded px-4">
                        <input type="hidden" name="units[${index}][is_sellable]" value="0">
                        <label class="form-check form-switch form-check-custom form-check-solid mb-0">
                            <input class="form-check-input" type="checkbox" name="units[${index}][is_sellable]" value="1" checked>
                            <span class="form-check-label fw-semibold text-gray-700">Bisa dijual</span>
                        </label>
                        <input type="hidden" name="units[${index}][is_active]" value="1">
                    </div>
                </div>
            </div>
        `;

        const select = row.querySelector('[data-unit-select]');
        availableUnits.forEach((unit) => select.add(new Option(unit.text, String(unit.id))));

        return row;
    }

    function refreshUnitRows() {
        const rows = Array.from(unitsContainer.querySelectorAll('.unit-row'));

        rows.forEach((row, position) => {
            const number = row.querySelector('[data-unit-row-number]');
            if (number) number.textContent = `Satuan #${position + 1}`;

            const removeButton = row.querySelector('.btn-remove-unit');
            if (removeButton) {
                removeButton.disabled = rows.length === 1;
                removeButton.title = rows.length === 1 ? 'Minimal satu satuan harus tersedia' : 'Hapus satuan';
            }
        });
    }

    // Tambah satuan baru
    if (addUnitBtn) {
        addUnitBtn.addEventListener('click', function() {
            const row = createUnitRow(unitIndex++);
            unitsContainer.appendChild(row);
            refreshUnitRows();
        });
    }

    // Hapus satuan
    unitsContainer.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-unit')) {
            const row = e.target.closest('.unit-row');
            if (row && unitsContainer.querySelectorAll('.unit-row').length > 1) {
                row.remove();
                refreshUnitRows();
            }
        }
    });

    refreshUnitRows();

    // ========== PHOTO MANAGEMENT ==========
    const photoUpload = document.getElementById('photoUpload');
    const newPhotoPreview = document.getElementById('newPhotoPreview');
    const removePhotosInput = document.getElementById('removePhotos');
    const existingPhotos = document.getElementById('existingPhotos');

    let uploadedFiles = [];
    let removePaths = [];

    if (photoUpload) {
        photoUpload.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);

            files.forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const id = 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                        uploadedFiles.push({ id, file, src: event.target.result });
                        renderNewPhotoPreview(id, file.name, event.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    }

    function renderNewPhotoPreview(id, name, src) {
        const div = document.createElement('div');
        div.className = 'col-md-3';
        div.id = 'preview-' + id;
        div.innerHTML = `
            <div class="card h-100">
                <img src="${src}" class="card-img-top" alt="${name}" style="height: 150px; object-fit: cover;">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted text-truncate" style="max-width: 100px;">${name}</small>
                        <button type="button" class="btn btn-sm btn-danger btn-remove-new" data-id="${id}">
                            <i class="ki-outline ki-trash fs-6"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        newPhotoPreview.appendChild(div);

        div.querySelector('.btn-remove-new').addEventListener('click', function() {
            const fileIndex = uploadedFiles.findIndex(f => f.id === id);
            if (fileIndex > -1) {
                uploadedFiles.splice(fileIndex, 1);
            }
            div.remove();
        });
    }

    // Hapus foto disimpan bersama form; foto utama yang dihapus akan diganti otomatis oleh backend.
    if (existingPhotos) {
        existingPhotos.addEventListener('click', async function(e) {
            const removeButton = e.target.closest('.btn-remove-photo');
            if (removeButton) {
                const card = removeButton.closest('[data-image-id]');
                const path = removeButton.dataset.path;
                const confirmed = window.Swal
                    ? (await window.Swal.fire({ title: 'Hapus foto?', text: 'Perubahan diterapkan setelah form produk disimpan.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' })).isConfirmed
                    : window.confirm('Hapus foto ini?');
                if (confirmed) {
                    card.remove();
                    removePaths.push(path);
                    removePhotosInput.value = [...new Set(removePaths)].join(',');
                }
                return;
            }

            const primaryButton = e.target.closest('.btn-set-primary');
            if (!primaryButton) return;
            const card = primaryButton.closest('[data-image-id]');
            const url = card.dataset.primaryUrl;
            if (!url) return;

            primaryButton.disabled = true;
            primaryButton.setAttribute('data-kt-indicator', 'on');
            try {
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Foto utama tidak dapat diperbarui.');

                existingPhotos.querySelectorAll('[data-image-id]').forEach(photo => {
                    const isSelected = photo === card;
                    photo.dataset.isPrimary = isSelected ? '1' : '0';
                    photo.querySelector('.card')?.classList.toggle('border-primary', isSelected);
                    photo.querySelector('.card')?.classList.toggle('border-2', isSelected);
                    photo.querySelector('.primary-photo-badge')?.remove();
                    const actions = photo.querySelector('.card-body .d-flex');
                    const oldButton = actions?.querySelector('.btn-set-primary');
                    if (isSelected) {
                        oldButton?.remove();
                        actions?.insertAdjacentHTML('afterbegin', '<span class="badge badge-light-primary primary-photo-badge"><i class="ki-outline ki-star fs-7 me-1"></i>Foto Utama</span>');
                    } else if (!oldButton && photo.dataset.primaryUrl) {
                        actions?.insertAdjacentHTML('afterbegin', '<button type="button" class="btn btn-sm btn-light-primary btn-set-primary" title="Jadikan foto utama"><i class="ki-outline ki-star fs-6 me-1"></i>Jadikan Utama</button>');
                    }
                });
                existingPhotos.prepend(card);
                window.Swal?.fire({ icon: 'success', title: 'Berhasil', text: payload.message, timer: 1800, showConfirmButton: false });
            } catch (error) {
                primaryButton.disabled = false;
                primaryButton.removeAttribute('data-kt-indicator');
                window.Swal ? window.Swal.fire('Gagal', error.message, 'error') : alert(error.message);
            }
        });
    }

});
</script>
