@php($isEdit = $role->exists)

@if ($errors->any())
    <div class="alert alert-danger">Periksa kembali isian role.</div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.roles.update', $role) : route('admin.roles.store') }}" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="row g-6 mb-6">
        <div class="col-lg-12">
            <x-metronic.card title="Informasi Peran (Role)">
                <div class="row">
                    <div class="col-md-6">
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
                    </div>
                    <div class="col-md-6">
                        <x-metronic.form-group name="description" label="Deskripsi">
                            <div class="d-flex align-items-center mb-2">
                                <span class="text-muted fs-8">Ringkasan tanggung jawab role.</span>
                                @include('admin.roles._help-icon', ['text' => 'Deskripsi membantu admin lain memahami siapa yang boleh memakai role ini dan tugas apa yang biasanya dilakukan role tersebut.'])
                            </div>
                            <textarea id="description" name="description" rows="5" class="form-control @error('description') is-invalid @enderror">{{ old('description', $role->description) }}</textarea>
                        </x-metronic.form-group>
                        
                        @if ($role->is_system)
                            <div class="alert alert-info d-flex align-items-center p-4 mb-0 mt-4">
                                <i class="ki-outline ki-information-5 fs-2x text-info me-4"></i>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-info">Sistem Default</h4>
                                    <span>Role inti sistem. Kode role dikunci, namun permission tetap dapat disesuaikan.</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </x-metronic.card>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-lg-12">
            <x-metronic.card title="Matriks Hak Akses (Permissions)">
                <div class="row">
                    @foreach ($permissions as $group => $groupPermissions)
                        <div class="col-md-4 mb-8">
                            <div class="card card-bordered h-100 shadow-sm">
                                <div class="card-header bg-light-primary min-h-40px px-4 py-2 border-bottom-0 d-flex justify-content-between align-items-center">
                                    <h4 class="card-title fw-bold text-primary m-0 d-flex align-items-center fs-5">
                                        <i class="ki-outline ki-abstract-26 fs-3 me-2"></i>
                                        {{ strtoupper($group) }}
                                        @include('admin.roles._help-icon', ['text' => 'Bagian ini berisi daftar hak akses untuk satu area kerja. Pilih hanya akses yang memang diperlukan agar role tidak terlalu luas.'])
                                    </h4>
                                    <button type="button" class="btn btn-sm btn-icon btn-light-primary" data-select-permission-group="{{ $group }}" data-bs-toggle="tooltip" title="Pilih Semua Modul Ini"><i class="ki-outline ki-check-square fs-2"></i></button>
                                </div>
                                <div class="card-body p-4">
                                    <div class="d-flex flex-column gap-3">
                                        @foreach ($groupPermissions as $permission)
                                            <label class="form-check form-check-custom form-check-solid border border-dashed rounded p-3">
                                                <input class="form-check-input" data-permission-group="{{ $group }}" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(collect(old('permissions', $selectedPermissions))->contains($permission->id))>
                                                <span class="form-check-label ms-2 d-flex flex-column">
                                                    <span class="fw-bold text-gray-800 d-flex align-items-center">
                                                        {{ $permission->label ?: $permission->name }}
                                                        @include('admin.roles._help-icon', ['text' => $permission->help_text])
                                                    </span>
                                                    <span class="text-muted fs-8">{{ $permission->description ?? $permission->name }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4">
                    <a href="{{ $isEdit ? route('admin.roles.show', $role) : route('admin.roles.index') }}" class="btn btn-light"><i class="ki-outline ki-cross fs-2"></i> Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="ki-outline ki-check fs-2"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Buat Role Baru' }}</button>
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
