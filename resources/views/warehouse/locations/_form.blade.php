@csrf
@isset($method) @method($method) @endisset

@php
    $currentOwnerType = old('owner_type', $ownerType ?? ($location->branch_id ? 'branch' : 'warehouse'));
@endphp

<div class="row g-5">
    <div class="col-12">
        <label class="form-label required fw-bold mb-3">Tipe Tempat / Lokasi Kerja</label>
        <div class="d-flex gap-6">
            <label class="form-check form-check-custom form-check-solid">
                <input class="form-check-input" type="radio" name="owner_type" value="warehouse" id="owner_type_warehouse" @checked($currentOwnerType === 'warehouse')>
                <span class="form-check-label fw-semibold text-gray-800">
                    <i class="ki-outline ki-home-2 me-1 text-primary"></i> Gudang
                </span>
            </label>
            <label class="form-check form-check-custom form-check-solid">
                <input class="form-check-input" type="radio" name="owner_type" value="branch" id="owner_type_branch" @checked($currentOwnerType === 'branch')>
                <span class="form-check-label fw-semibold text-gray-800">
                    <i class="ki-outline ki-shop me-1 text-success"></i> Toko / Cabang
                </span>
            </label>
        </div>
    </div>

    <div class="col-md-6" id="warehouse_select_container">
        <x-metronic.form-group name="warehouse_id" label="Pilih Gudang">
            <select name="warehouse_id" id="warehouse_id_select" class="form-select form-select-solid">
                <option value="">Pilih gudang</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" data-code="{{ $warehouse->code }}" @selected(old('warehouse_id', $location->warehouse_id) == $warehouse->id)>
                        {{ $warehouse->code }} — {{ $warehouse->name }}
                    </option>
                @endforeach
            </select>
        </x-metronic.form-group>
    </div>

    <div class="col-md-6" id="branch_select_container" style="display: none;">
        <x-metronic.form-group name="branch_id" label="Pilih Toko / Cabang">
            <select name="branch_id" id="branch_id_select" class="form-select form-select-solid">
                <option value="">Pilih toko / cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" data-code="{{ $branch->code }}" @selected(old('branch_id', $location->branch_id) == $branch->id)>
                        {{ $branch->code }} — {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </x-metronic.form-group>
    </div>

    <div class="col-md-6">
        <x-metronic.form-group name="parent_id" label="Parent Lokasi">
            <select name="parent_id" id="parent_id_select" class="form-select form-select-solid">
                <option value="">Tanpa parent (Root)</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" 
                        data-warehouse-id="{{ $parent->warehouse_id }}" 
                        data-branch-id="{{ $parent->branch_id }}"
                        @selected(old('parent_id', $location->parent_id) == $parent->id)>
                        {{ $parent->full_code }} — {{ $parent->name }} ({{ $parent->type->label() }})
                    </option>
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
    <div class="col-md-4"><x-metronic.form-group name="code" label="Kode"><input name="code" value="{{ old('code', $location->code) }}" class="form-control form-control-solid" placeholder="Contoh: ZONA-A, RAK-01, BIN-01" required></x-metronic.form-group></div>
    <div class="col-md-4"><x-metronic.form-group name="name" label="Nama"><input name="name" value="{{ old('name', $location->name) }}" class="form-control form-control-solid" placeholder="Nama identifikasi lokasi" required></x-metronic.form-group></div>
    <div class="col-md-4"><x-metronic.form-group name="capacity" label="Kapasitas"><input name="capacity" value="{{ old('capacity', qty_input($location->capacity)) }}" type="number" step="1" min="0" class="form-control form-control-solid" placeholder="Opsional"></x-metronic.form-group></div>
    <div class="col-md-4"><x-metronic.form-group name="item_type" label="Jenis Barang"><input name="item_type" value="{{ old('item_type', $location->item_type) }}" class="form-control form-control-solid" placeholder="Opsional, misal: Makanan, Minuman"></x-metronic.form-group></div>
    <div class="col-md-4 d-flex align-items-end"><label class="form-check form-switch form-check-custom form-check-solid mb-4"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $location->is_active ?? true))><span class="form-check-label">Aktif</span></label></div>
</div>
<div class="mt-5 d-flex gap-3">
    <button class="btn btn-primary" type="submit">Simpan</button>
    <a href="{{ url()->previous() ?: route('warehouse.locations.index') }}" class="btn btn-light">Batal</a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const warehouseRadio = document.getElementById('owner_type_warehouse');
    const branchRadio = document.getElementById('owner_type_branch');
    const warehouseContainer = document.getElementById('warehouse_select_container');
    const branchContainer = document.getElementById('branch_select_container');
    const warehouseSelect = document.getElementById('warehouse_id_select');
    const branchSelect = document.getElementById('branch_id_select');
    const parentSelect = document.getElementById('parent_id_select');
    const allParentOptions = Array.from(parentSelect.querySelectorAll('option'));

    function updateVisibilityAndParents() {
        const isBranch = branchRadio.checked;

        if (isBranch) {
            warehouseContainer.style.display = 'none';
            branchContainer.style.display = 'block';
            warehouseSelect.required = false;
            branchSelect.required = true;
            filterParents('branch', branchSelect.value);
        } else {
            warehouseContainer.style.display = 'block';
            branchContainer.style.display = 'none';
            warehouseSelect.required = true;
            branchSelect.required = false;
            filterParents('warehouse', warehouseSelect.value);
        }
    }

    function filterParents(type, selectedOwnerId) {
        const currentSelectedParent = parentSelect.value;
        parentSelect.innerHTML = '';

        allParentOptions.forEach(function (option) {
            if (!option.value) {
                // Root option "Tanpa parent" always included
                parentSelect.appendChild(option);
                return;
            }

            const optWarehouseId = option.getAttribute('data-warehouse-id');
            const optBranchId = option.getAttribute('data-branch-id');

            if (type === 'warehouse' && selectedOwnerId && optWarehouseId === selectedOwnerId) {
                parentSelect.appendChild(option);
            } else if (type === 'branch' && selectedOwnerId && optBranchId === selectedOwnerId) {
                parentSelect.appendChild(option);
            }
        });

        // Restore previously selected parent if still valid in DOM
        if (parentSelect.querySelector(`option[value="${currentSelectedParent}"]`)) {
            parentSelect.value = currentSelectedParent;
        } else {
            parentSelect.value = '';
        }
    }

    if (warehouseRadio && branchRadio) {
        warehouseRadio.addEventListener('change', updateVisibilityAndParents);
        branchRadio.addEventListener('change', updateVisibilityAndParents);
    }
    if (warehouseSelect) {
        warehouseSelect.addEventListener('change', updateVisibilityAndParents);
    }
    if (branchSelect) {
        branchSelect.addEventListener('change', updateVisibilityAndParents);
    }

    updateVisibilityAndParents();
});
</script>
@endpush
