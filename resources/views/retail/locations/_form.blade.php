@csrf
@isset($method) @method($method) @endisset
<input type="hidden" name="owner_type" value="branch">

<div class="row g-5">
    <div class="col-md-6">
        <x-metronic.form-group name="branch_id" label="Toko / Cabang">
            <select name="branch_id" id="retail_branch_id_select" class="form-select form-select-solid" required>
                <option value="">Pilih toko / cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $location->branch_id) == $branch->id)>
                        {{ $branch->code }} — {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </x-metronic.form-group>
    </div>

    <div class="col-md-6">
        <x-metronic.form-group name="parent_id" label="Parent Lokasi">
            <select name="parent_id" id="retail_parent_id_select" class="form-select form-select-solid">
                <option value="">Tanpa parent (Root Zona)</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" 
                        data-branch-id="{{ $parent->branch_id }}"
                        @selected(old('parent_id', $location->parent_id) == $parent->id)>
                        {{ $parent->full_code }} — {{ $parent->name }} ({{ $parent->type->label() }})
                    </option>
                @endforeach
            </select>
        </x-metronic.form-group>
    </div>

    <div class="col-md-4">
        <x-metronic.form-group name="type" label="Tipe Lokasi">
            <select name="type" class="form-select form-select-solid" required>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', $location->type?->value ?? $location->type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-metronic.form-group>
    </div>

    <div class="col-md-4">
        <x-metronic.form-group name="code" label="Kode">
            <input name="code" value="{{ old('code', $location->code) }}" class="form-control form-control-solid" placeholder="Contoh: DISPLAY, RAK-01, BIN-01" required>
        </x-metronic.form-group>
    </div>

    <div class="col-md-4">
        <x-metronic.form-group name="name" label="Nama Lokasi">
            <input name="name" value="{{ old('name', $location->name) }}" class="form-control form-control-solid" placeholder="Contoh: Rak Display Depan" required>
        </x-metronic.form-group>
    </div>

    <div class="col-md-4">
        <x-metronic.form-group name="capacity" label="Kapasitas">
            <input name="capacity" value="{{ old('capacity', qty_input($location->capacity)) }}" type="number" step="1" min="0" class="form-control form-control-solid" placeholder="Opsional">
        </x-metronic.form-group>
    </div>

    <div class="col-md-4">
        <x-metronic.form-group name="item_type" label="Jenis Barang">
            <input name="item_type" value="{{ old('item_type', $location->item_type) }}" class="form-control form-control-solid" placeholder="Opsional, misal: Makanan, Minuman">
        </x-metronic.form-group>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <label class="form-check form-switch form-check-custom form-check-solid mb-4">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $location->is_active ?? true))>
            <span class="form-check-label fw-semibold text-gray-800">Aktif</span>
        </label>
    </div>
</div>

<div class="mt-5 d-flex gap-3">
    <button class="btn btn-primary" type="submit">Simpan</button>
    <a href="{{ route('retail.locations.index') }}" class="btn btn-light">Batal</a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const branchSelect = document.getElementById('retail_branch_id_select');
    const parentSelect = document.getElementById('retail_parent_id_select');
    if (!branchSelect || !parentSelect) return;

    const allParentOptions = Array.from(parentSelect.querySelectorAll('option'));

    function filterParents() {
        const selectedBranchId = branchSelect.value;
        const currentSelectedParent = parentSelect.value;
        parentSelect.innerHTML = '';

        allParentOptions.forEach(function (option) {
            if (!option.value) {
                parentSelect.appendChild(option);
                return;
            }

            const optBranchId = option.getAttribute('data-branch-id');
            if (selectedBranchId && optBranchId === selectedBranchId) {
                parentSelect.appendChild(option);
            }
        });

        if (parentSelect.querySelector(`option[value="${currentSelectedParent}"]`)) {
            parentSelect.value = currentSelectedParent;
        } else {
            parentSelect.value = '';
        }
    }

    branchSelect.addEventListener('change', filterParents);
    filterParents();
});
</script>
@endpush
