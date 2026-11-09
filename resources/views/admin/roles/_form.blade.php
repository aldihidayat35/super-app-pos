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
                @include('admin.roles._permission_matrix', [
                    'permissions' => $permissions,
                    'selectedPermissions' => $selectedPermissions,
                    'disabled' => false,
                ])

                <div class="d-flex justify-content-end gap-3 mt-6 pt-4 border-top">
                    <a href="{{ $isEdit ? route('admin.roles.show', $role) : route('admin.roles.index') }}" class="btn btn-light">
                        <i class="ki-outline ki-cross fs-2 me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ki-outline ki-check fs-2 me-1"></i> {{ $isEdit ? 'Simpan Perubahan Role' : 'Buat Role Baru' }}
                    </button>
                </div>
            </x-metronic.card>
        </div>
    </div>
</form>
