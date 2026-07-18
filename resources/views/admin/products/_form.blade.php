@php
    $isEdit = $product->exists;
    $oldUnits = old('units', $unitRows ?: []);
    $oldBarcodes = old('barcodes', $barcodeRows ?: [['code' => '', 'type' => 'barcode']]);
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
                <div class="alert alert-info">Satuan dasar wajib memiliki faktor 1. Faktor yang sudah terkunci transaksi tidak dapat diubah.</div>
                @foreach($oldUnits as $index => $row)
                    <div class="row align-items-end mb-3">
                        <div class="col-md-4"><x-metronic.form-group name="units.{{ $index }}.unit_id" label="Satuan"><select name="units[{{ $index }}][unit_id]" class="form-select">@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((int) ($row['unit_id'] ?? 0) === $unit->id)>{{ $unit->name }}</option>@endforeach</select></x-metronic.form-group></div>
                        <div class="col-md-3"><x-metronic.form-group name="units.{{ $index }}.conversion_factor" label="Faktor ke Dasar"><input type="number" step="0.000001" min="0.000001" name="units[{{ $index }}][conversion_factor]" value="{{ $row['conversion_factor'] ?? 1 }}" class="form-control"></x-metronic.form-group></div>
                        <div class="col-md-3"><x-metronic.form-group name="units.{{ $index }}.name" label="Label"><input name="units[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" class="form-control"></x-metronic.form-group></div>
                        <div class="col-md-2"><input type="hidden" name="units[{{ $index }}][is_sellable]" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="units[{{ $index }}][is_sellable]" value="1" @checked($row['is_sellable'] ?? true)> Bisa dijual</label><input type="hidden" name="units[{{ $index }}][is_active]" value="1"></div>
                    </div>
                @endforeach
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
                <x-metronic.form-group name="main_image" label="Foto Utama"><input type="file" name="main_image" accept="image/*" class="form-control @error('main_image') is-invalid @enderror"><div class="form-text">Maksimal 4 MB. Foto tersimpan di filesystem public.</div></x-metronic.form-group>
                @if($product->main_image_path)<div class="text-muted">Foto saat ini: {{ $product->main_image_path }}</div>@endif
            </x-metronic.card>
        </div>

        <div class="tab-pane fade" id="tab_stock">
            <x-metronic.card title="Pengaturan Stok">
                <div class="row">
                    <div class="col-md-4"><x-metronic.form-group name="minimum_order" label="Minimum Order"><input type="number" step="1" min="0" name="minimum_order" value="{{ old('minimum_order', qty_input($product->minimum_order ?? 0)) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="minimum_stock" label="Minimum Stock"><input type="number" step="1" min="0" name="minimum_stock" value="{{ old('minimum_stock', qty_input($product->minimum_stock ?? 0)) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-4"><x-metronic.form-group name="safety_stock" label="Safety Stock"><input type="number" step="1" min="0" name="safety_stock" value="{{ old('safety_stock', qty_input($product->safety_stock ?? 0)) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-3"><x-metronic.form-group name="weight" label="Berat"><input type="number" step="0.0001" min="0" name="weight" value="{{ old('weight', $product->weight) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-3"><x-metronic.form-group name="volume" label="Volume"><input type="number" step="0.0001" min="0" name="volume" value="{{ old('volume', $product->volume) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-3"><x-metronic.form-group name="cost_price" label="HPP"><input type="number" step="0.01" min="0" name="cost_price" value="{{ old('cost_price', $product->cost_price ?? 0) }}" class="form-control"></x-metronic.form-group></div>
                    <div class="col-md-3"><x-metronic.form-group name="minimum_price" label="Harga Minimum"><input type="number" step="0.01" min="0" name="minimum_price" value="{{ old('minimum_price', $product->minimum_price ?? 0) }}" class="form-control"></x-metronic.form-group></div>
                </div>
            </x-metronic.card>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-3 mt-6"><a href="{{ $isEdit ? route('admin.products.show', $product) : route('admin.products.index') }}" class="btn btn-light">Batal</a><button class="btn btn-primary">{{ $isEdit ? 'Simpan Produk' : 'Buat Produk' }}</button></div>
</form>
