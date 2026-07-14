@php($isEdit = $role->exists)

@if ($errors->any())
    <div class="alert alert-danger">Periksa kembali isian role.</div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.roles.update', $role) : route('admin.roles.store') }}" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Informasi Role">
                <x-metronic.form-group name="name" label="Kode Role" required>
                    <div class="d-flex align-items-center mb-2">
                        <span class="text-muted fs-8">Kode singkat untuk mengenali role di sistem.</span>
                        @include('admin.roles._help-icon', ['text' => 'Kode role adalah identitas internal yang singkat dan unik. Gunakan huruf kecil, angka, atau garis bawah. Contoh: kepala_gudang.'])
                    </div>
                    <input id="name" name="name" value="{{ old('name', $role->name) }}" class="form-control @error('name') is-invalid @enderror" @readonly($role->is_system) required>
                </x-metronic.form-group>
                <x-metronic.form-group name="label" label="Label Role" required>
                    <div class="d-flex align-items-center mb-2">
                        <span class="text-muted fs-8">Nama yang mudah dibaca pengguna.</span>
                        @include('admin.roles._help-icon', ['text' => 'Label role adalah nama yang tampil untuk admin. Pakai bahasa yang mudah dimengerti, misalnya Kepala Gudang atau Kasir.'])
                    </div>
                    <input id="label" name="label" value="{{ old('label', $role->label) }}" class="form-control @error('label') is-invalid @enderror" required>
                </x-metronic.form-group>
                <x-metronic.form-group name="description" label="Deskripsi">
                    <div class="d-flex align-items-center mb-2">
                        <span class="text-muted fs-8">Ringkasan tanggung jawab role.</span>
                        @include('admin.roles._help-icon', ['text' => 'Deskripsi membantu admin lain memahami siapa yang boleh memakai role ini dan tugas apa yang biasanya dilakukan role tersebut.'])
                    </div>
                    <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $role->description) }}</textarea>
                </x-metronic.form-group>
                @if ($role->is_system)
                    <div class="alert alert-info mb-0">Role inti sistem. Kode role dikunci, permission tetap dapat disesuaikan oleh Super Admin.</div>
                @endif
            </x-metronic.card>
        </div>

        <div class="col-lg-8">
            <x-metronic.card title="Matriks Permission">
                @foreach ($permissions as $group => $groupPermissions)
                    <div class="mb-8">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="fw-bold text-gray-900 mb-0 d-flex align-items-center">
                                {{ strtoupper($group) }}
                                @include('admin.roles._help-icon', ['text' => 'Bagian ini berisi daftar hak akses untuk satu area kerja. Pilih hanya akses yang memang diperlukan agar role tidak terlalu luas.'])
                            </h4>
                            <button type="button" class="btn btn-sm btn-light" data-select-permission-group="{{ $group }}" data-bs-toggle="tooltip" title="Centang semua akses pada area ini. Gunakan hanya untuk role yang memang perlu mengelola seluruh area tersebut.">Pilih Modul</button>
                        </div>
                        <div class="row g-3">
                            @foreach ($groupPermissions as $permission)
                                <div class="col-md-6">
                                    <label class="form-check form-check-custom form-check-solid border rounded p-3 h-100">
                                        <input class="form-check-input" data-permission-group="{{ $group }}" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(collect(old('permissions', $selectedPermissions))->contains($permission->id))>
                                        <span class="form-check-label ms-2">
                                            <span class="fw-semibold d-flex align-items-center">
                                                {{ $permission->label ?: $permission->name }}
                                                @include('admin.roles._help-icon', ['text' => $permission->help_text])
                                            </span>
                                            <span class="text-muted fs-8">{{ $permission->name }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="d-flex justify-content-end gap-3">
                    <a href="{{ $isEdit ? route('admin.roles.show', $role) : route('admin.roles.index') }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Simpan Role' : 'Buat Role' }}</button>
                </div>
            </x-metronic.card>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        document.querySelectorAll('[data-select-permission-group]').forEach((button) => {
            button.addEventListener('click', () => {
                const group = button.getAttribute('data-select-permission-group');
                document.querySelectorAll(`[data-permission-group="${group}"]`).forEach((input) => {
                    input.checked = true;
                });
            });
        });
    </script>
@endpush
