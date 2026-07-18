@csrf
@isset($method) @method($method) @endisset
<div class="row g-5">
    <div class="col-md-6">
        <x-metronic.form-group name="warehouse_id" label="Gudang">
            <select name="warehouse_id" class="form-select form-select-solid" required>
                <option value="">Pilih gudang</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $location->warehouse_id) == $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                @endforeach
            </select>
        </x-metronic.form-group>
    </div>
    <div class="col-md-6">
        <x-metronic.form-group name="parent_id" label="Parent Lokasi">
            <select name="parent_id" class="form-select form-select-solid">
                <option value="">Tanpa parent</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected(old('parent_id', $location->parent_id) == $parent->id)>{{ $parent->full_code }} — {{ $parent->name }}</option>
                @endforeach
            </select>
        </x-metronic.form-group>
    </div>
    <div class="col-md-4">
        <x-metronic.form-group name="type" label="Tipe">
            <select name="type" class="form-select form-select-solid" required>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', $location->type?->value ?? $location->type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-metronic.form-group>
    </div>
    <div class="col-md-4"><x-metronic.form-group name="code" label="Kode"><input name="code" value="{{ old('code', $location->code) }}" class="form-control form-control-solid" required></x-metronic.form-group></div>
    <div class="col-md-4"><x-metronic.form-group name="name" label="Nama"><input name="name" value="{{ old('name', $location->name) }}" class="form-control form-control-solid" required></x-metronic.form-group></div>
    <div class="col-md-4"><x-metronic.form-group name="capacity" label="Kapasitas"><input name="capacity" value="{{ old('capacity', qty_input($location->capacity)) }}" type="number" step="1" min="0" class="form-control form-control-solid"></x-metronic.form-group></div>
    <div class="col-md-4"><x-metronic.form-group name="item_type" label="Jenis Barang"><input name="item_type" value="{{ old('item_type', $location->item_type) }}" class="form-control form-control-solid" placeholder="Opsional"></x-metronic.form-group></div>
    <div class="col-md-4 d-flex align-items-end"><label class="form-check form-switch form-check-custom form-check-solid mb-4"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $location->is_active ?? true))><span class="form-check-label">Aktif</span></label></div>
</div>
<div class="mt-5 d-flex gap-3">
    <button class="btn btn-primary" type="submit">Simpan</button>
    <a href="{{ route('warehouse.locations.index') }}" class="btn btn-light">Batal</a>
</div>
