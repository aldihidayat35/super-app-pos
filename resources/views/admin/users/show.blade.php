@extends('layouts.metronic.app')

@section('title', 'Detail Pengguna - ' . config('app.name'))
@section('page_title', 'Detail Pengguna')

@section('page_guide')
    <x-metronic.page-guide id="admin-user-show" title="Panduan Dashboard Pengguna">
        <x-slot:function><p>Halaman ini menjadi pusat informasi akun, hak akses, lokasi kerja, relasi operasional, approval, aktivitas, dan keamanan pengguna.</p></x-slot:function>
        <x-slot:parts><ul><li><strong>Kartu ringkasan:</strong> jumlah role, permission, lokasi aktif, dan waktu login terakhir.</li><li><strong>Tab:</strong> mengelompokkan informasi agar mudah diperiksa.</li><li><strong>Tombol aksi:</strong> membuka halaman terkait dengan pengguna ini sudah dipilih.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Perubahan role dan lokasi kerja dapat langsung memengaruhi menu, cakupan data, serta transaksi yang boleh dilakukan pengguna.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Periksa status akun dan login terakhir.</li><li>Tinjau role serta lokasi kerja.</li><li>Gunakan tombol aksi pada tab yang relevan untuk melakukan perubahan.</li></ol></x-slot:operation>
        <x-slot:warnings><p>Jangan memberi role atau lokasi lebih luas dari kebutuhan kerja. Aktivitas dan permission hanya tampil bila akun Anda memiliki hak audit/RBAC.</p></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-light"><i class="ki-outline ki-arrow-left fs-5 me-1"></i>Daftar Pengguna</a>
    @if($access['security_manage'])
        <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">
            @csrf
            <button type="submit" class="btn btn-light-warning" data-confirm="Kirim tautan reset password kepada pengguna ini?">
                <i class="ki-outline ki-key fs-5 me-1"></i>Reset Password
            </button>
        </form>
    @endif
    @can('update', $user)
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary"><i class="ki-outline ki-pencil fs-5 me-1"></i>Edit Pengguna</a>
    @endcan
@endsection

@section('content')
    <div class="card mb-6 overflow-hidden">
        <div class="card-body p-5 p-lg-8">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-6">
                <div class="d-flex align-items-center gap-5">
                    <div class="symbol symbol-70px symbol-lg-90px flex-shrink-0">
                        @if($user->avatar_path)
                            <img src="{{ asset('storage/'.$user->avatar_path) }}" alt="Avatar {{ $user->name }}" class="object-fit-cover">
                        @else
                            <span class="symbol-label bg-light-primary text-primary fs-2x fw-bold">{{ str($user->name)->substr(0, 2)->upper() }}</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h1 class="fs-2 fw-bold text-gray-900 mb-0 text-break">{{ $user->name }}</h1>
                            <x-metronic.status-badge :status="$user->is_active ? 'active' : 'inactive'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" />
                            @if(auth()->user()?->is($user))<span class="badge badge-light-info">Akun Anda</span>@endif
                        </div>
                        <div class="text-muted fw-semibold mb-3">{{ '@'.$user->username }}</div>
                        <div class="d-flex flex-wrap gap-4 text-gray-700 fs-7">
                            <span><i class="ki-outline ki-sms fs-5 text-primary me-1"></i>{{ $user->email }}</span>
                            <span><i class="ki-outline ki-phone fs-5 text-primary me-1"></i>{{ $user->phone_number ?: 'WhatsApp belum diisi' }}</span>
                            <span><i class="ki-outline ki-geolocation fs-5 text-primary me-1"></i>{{ $defaultLocation?->name ?: 'Lokasi utama belum ditentukan' }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if($user->phone_number)
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', $user->phone_number) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light-success"><i class="ki-outline ki-whatsapp fs-5 me-1"></i>WhatsApp</a>
                    @endif
                    @if($access['locations_manage'])
                        <a href="{{ route('admin.users.locations.edit', $user) }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-geolocation fs-5 me-1"></i>Atur Lokasi</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-6">
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between gap-3">
            <div><div class="text-muted fw-semibold mb-2">Role</div><div class="fs-2x fw-bold text-gray-900">{{ $metrics['roles'] }}</div><div class="text-muted fs-8">Peran yang ditugaskan</div></div>
            <span class="symbol symbol-50px"><span class="symbol-label bg-light-primary"><i class="ki-outline ki-shield-tick fs-2x text-primary"></i></span></span>
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between gap-3">
            <div><div class="text-muted fw-semibold mb-2">Permission Efektif</div><div class="fs-2x fw-bold text-gray-900">{{ $metrics['permissions'] ?? '—' }}</div><div class="text-muted fs-8">Dihitung dari seluruh role</div></div>
            <span class="symbol symbol-50px"><span class="symbol-label bg-light-success"><i class="ki-outline ki-lock-2 fs-2x text-success"></i></span></span>
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between gap-3">
            <div><div class="text-muted fw-semibold mb-2">Lokasi Aktif</div><div class="fs-2x fw-bold text-gray-900">{{ $metrics['active_locations'] }}</div><div class="text-muted fs-8">Gudang/cabang yang dapat diakses</div></div>
            <span class="symbol symbol-50px"><span class="symbol-label bg-light-warning"><i class="ki-outline ki-geolocation fs-2x text-warning"></i></span></span>
        </div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between gap-3">
            <div><div class="text-muted fw-semibold mb-2">Login Terakhir</div><div class="fs-5 fw-bold text-gray-900">{{ $user->last_login_at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') ?: 'Belum pernah' }}</div><div class="text-muted fs-8">{{ $user->last_login_at?->diffForHumans() ?: 'Belum ada sesi berhasil' }}</div></div>
            <span class="symbol symbol-50px"><span class="symbol-label bg-light-info"><i class="ki-outline ki-login fs-2x text-info"></i></span></span>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-2 px-4 px-lg-7">
            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-semibold flex-nowrap overflow-auto w-100" role="tablist">
                @foreach([
                    ['user-summary', 'user-summary-pane', 'ki-profile-circle', 'Ringkasan'],
                    ['user-roles', 'user-roles-pane', 'ki-shield-tick', 'Role & Permission'],
                    ['user-locations', 'user-locations-pane', 'ki-geolocation', 'Lokasi Kerja'],
                    ['user-relations', 'user-relations-pane', 'ki-people', 'Relasi Operasional'],
                    ['user-approvals', 'user-approvals-pane', 'ki-check-circle', 'Approval'],
                    ['user-activities', 'user-activities-pane', 'ki-time', 'Aktivitas'],
                    ['user-security', 'user-security-pane', 'ki-security-check', 'Keamanan'],
                ] as [$tabId, $paneId, $icon, $label])
                    <li class="nav-item flex-shrink-0" role="presentation">
                        <button class="nav-link text-active-primary py-4 px-3 px-lg-4 {{ $loop->first ? 'active' : '' }}" id="{{ $tabId }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $paneId }}" type="button" role="tab" aria-controls="{{ $paneId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"><i class="ki-outline {{ $icon }} fs-4 me-2"></i>{{ $label }}</button>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body p-4 p-lg-7">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="user-summary-pane" role="tabpanel" aria-labelledby="user-summary-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6"><div><h2 class="fs-3 fw-bold mb-1">Profil Pengguna</h2><div class="text-muted">Identitas akun dan informasi kontak utama.</div></div>@can('update', $user)<a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-pencil fs-5 me-1"></i>Ubah Profil</a>@endcan</div>
                    <div class="row g-6">
                        <div class="col-lg-6"><div class="border border-gray-300 rounded p-5 h-100"><h3 class="fs-5 fw-bold mb-5"><i class="ki-outline ki-profile-circle fs-3 text-primary me-2"></i>Identitas Akun</h3><div class="row g-4">
                            @foreach([['Nama Lengkap', $user->name], ['Username', $user->username], ['Email', $user->email], ['Nomor WhatsApp', $user->phone_number]] as [$label, $value])<div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">{{ $label }}</div><div class="fw-semibold text-gray-800 text-break">{{ $value ?: '—' }}</div></div>@endforeach
                        </div></div></div>
                        <div class="col-lg-6"><div class="border border-gray-300 rounded p-5 h-100"><h3 class="fs-5 fw-bold mb-5"><i class="ki-outline ki-information-5 fs-3 text-success me-2"></i>Status Akun</h3><div class="row g-4">
                            <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Status</div><x-metronic.status-badge :status="$user->is_active ? 'active' : 'inactive'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" /></div>
                            <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Verifikasi Email</div><span class="badge {{ $user->email_verified_at ? 'badge-light-success' : 'badge-light-warning' }}">{{ $user->email_verified_at ? 'Terverifikasi' : 'Belum terverifikasi' }}</span></div>
                            <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Dibuat</div><div class="fw-semibold">{{ $user->created_at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') ?: '—' }}</div></div>
                            <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Terakhir Diubah</div><div class="fw-semibold">{{ $user->updated_at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i') ?: '—' }}</div></div>
                        </div></div></div>
                    </div>
                </div>

                <div class="tab-pane fade" id="user-roles-pane" role="tabpanel" aria-labelledby="user-roles-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5"><div><h2 class="fs-3 fw-bold mb-1">Role & Permission Efektif</h2><div class="text-muted">Peran pengguna dan gabungan izin yang diperoleh dari seluruh role.</div></div><div class="d-flex flex-wrap gap-2">@if($access['roles'])<a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-eye fs-5 me-1"></i>Daftar Role</a>@endif @can('update', $user)<a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-pencil fs-5 me-1"></i>Atur Role Pengguna</a>@endcan</div></div>
                    <div class="row g-5 mb-7">
                        @forelse($user->roles as $role)
                            <div class="col-md-6"><div class="border border-gray-300 rounded p-5 h-100 d-flex align-items-start justify-content-between gap-3"><div><div class="fw-bold fs-5 text-capitalize">{{ $role->label ?: str($role->name)->replace('_', ' ') }}</div><div class="text-muted fs-7 mt-1">{{ $role->description ?: 'Tidak ada deskripsi role.' }}</div>@if($access['permissions'])<div class="text-muted fs-8 mt-2">{{ $role->permissions->count() }} permission langsung</div>@endif</div>@if($access['roles'])<a href="{{ route('admin.roles.show', $role) }}" class="btn btn-sm btn-icon btn-light-primary" title="Detail role"><i class="ki-outline ki-arrow-right fs-4"></i></a>@endif</div></div>
                        @empty
                            <div class="col-12"><x-metronic.empty-state title="Belum ada role" description="Gunakan tombol Atur Role Pengguna untuk menentukan tanggung jawab akun ini." icon="ki-outline ki-shield-tick" /></div>
                        @endforelse
                    </div>
                    @if(!$access['permissions'])
                        <x-metronic.empty-state title="Detail permission dibatasi" description="Akun Anda tidak memiliki permission untuk melihat matriks izin efektif." icon="ki-outline ki-lock" />
                    @else
                        <h3 class="fs-5 fw-bold mb-4">Permission Efektif</h3><div class="d-flex flex-wrap gap-2">@forelse($effectivePermissions as $permission)<span class="badge badge-light-primary py-2 px-3">{{ $permission->name }}</span>@empty<span class="text-muted">Belum ada permission efektif.</span>@endforelse</div>
                    @endif
                </div>

                <div class="tab-pane fade" id="user-locations-pane" role="tabpanel" aria-labelledby="user-locations-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5"><div><h2 class="fs-3 fw-bold mb-1">Penugasan Lokasi Kerja</h2><div class="text-muted">Gudang dan cabang yang membatasi cakupan data operasional pengguna.</div></div>@if($access['locations_manage'])<a href="{{ route('admin.users.locations.edit', $user) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-geolocation fs-5 me-1"></i>Atur Lokasi Kerja</a>@endif</div>
                    <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Lokasi</th><th>Tipe</th><th>Masa Berlaku</th><th>Status</th><th>Prioritas</th></tr></thead><tbody>
                    @forelse($user->workLocations as $location)
                        <tr><td><div class="fw-bold">{{ $location->name }}</div><div class="text-muted fs-8">{{ $location->code }}</div></td><td>{{ $location->typeLabel() }}</td><td>{{ $location->pivot?->effective_from ?: 'Tanpa batas awal' }}<div class="text-muted fs-8">sampai {{ $location->pivot?->effective_until ?: 'seterusnya' }}</div></td><td><x-metronic.status-badge :status="$location->pivot?->is_active ? 'active' : 'inactive'" :label="$location->pivot?->is_active ? 'Aktif' : 'Nonaktif'" /></td><td>@if($location->pivot?->is_default)<span class="badge badge-light-success">Lokasi Utama</span>@else<span class="text-muted">Tambahan</span>@endif</td></tr>
                    @empty
                        <tr><td colspan="5"><x-metronic.empty-state title="Belum ada lokasi kerja" description="Tentukan lokasi agar akses transaksi pengguna memiliki cakupan yang jelas." icon="ki-outline ki-geolocation" /></td></tr>
                    @endforelse
                    </tbody></table></div>
                </div>

                <div class="tab-pane fade" id="user-relations-pane" role="tabpanel" aria-labelledby="user-relations-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5"><div><h2 class="fs-3 fw-bold mb-1">Relasi Operasional</h2><div class="text-muted">Keterhubungan akun dengan data karyawan dan pelanggan B2B.</div></div><div class="d-flex flex-wrap gap-2">@if($access['attendance'])<a href="{{ route('attendance.employees.index') }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-eye fs-5 me-1"></i>Daftar Karyawan</a>@endif @if($access['attendance_manage'])@if($employee)<a href="{{ route('attendance.employees.edit', $employee) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-pencil fs-5 me-1"></i>Edit Data Karyawan</a>@else<a href="{{ route('attendance.employees.create', ['user_id' => $user->id, 'work_location_id' => $defaultLocation?->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Buat Data Karyawan</a>@endif @endif</div></div>
                    @if(!$access['attendance'] && !$access['customers'])
                        <x-metronic.empty-state title="Akses relasi dibatasi" description="Akun Anda tidak memiliki permission ke modul kehadiran atau pelanggan." icon="ki-outline ki-lock" />
                    @else
                        <div class="row g-6">
                            @if($access['attendance'])<div class="col-lg-6"><div class="border border-gray-300 rounded p-5 h-100"><h3 class="fs-5 fw-bold mb-5"><i class="ki-outline ki-badge fs-3 text-primary me-2"></i>Data Karyawan</h3>@if($employee)<div class="row g-4">@foreach([['NIK Internal', $employee->employee_no], ['Nama Karyawan', $employee->name], ['Posisi', $employee->position], ['Lokasi', $employee->workLocation?->name], ['Tanggal Masuk', $employee->joined_at?->translatedFormat('d M Y')], ['Status', $employee->status->label()]] as [$label, $value])<div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">{{ $label }}</div><div class="fw-semibold">{{ $value ?: '—' }}</div></div>@endforeach</div>@else<x-metronic.empty-state title="Belum terhubung ke data karyawan" description="Buat data karyawan agar akun dapat digunakan pada jadwal dan kehadiran." icon="ki-outline ki-badge" />@endif</div></div>@endif
                            @if($access['customers'])<div class="col-lg-6"><div class="border border-gray-300 rounded p-5 h-100"><h3 class="fs-5 fw-bold mb-5"><i class="ki-outline ki-shop fs-3 text-success me-2"></i>Pelanggan B2B Terkait</h3>@forelse($user->customers as $customer)<div class="d-flex align-items-center justify-content-between gap-3 border-bottom pb-3 mb-3"><div><div class="fw-bold">{{ $customer->business_name }}</div><div class="text-muted fs-8">{{ $customer->code }} · {{ str($customer->pivot?->role)->replace('_', ' ')->title() }}</div></div><a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-light-primary">Detail</a></div>@empty<x-metronic.empty-state title="Tidak terkait pelanggan B2B" description="Akun internal biasanya tidak memiliki relasi pelanggan." icon="ki-outline ki-shop" />@endforelse</div></div>@endif
                        </div>
                    @endif
                </div>

                <div class="tab-pane fade" id="user-approvals-pane" role="tabpanel" aria-labelledby="user-approvals-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5"><div><h2 class="fs-3 fw-bold mb-1">Approval yang Diajukan</h2><div class="text-muted">Permintaan persetujuan sensitif yang dibuat oleh pengguna ini.</div></div>@if($access['approvals'])<a href="{{ route('approvals.index', ['requester_user_id' => $user->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-eye fs-5 me-1"></i>Lihat Semua Approval</a>@endif</div>
                    @if(!$access['approvals'])<x-metronic.empty-state title="Akses approval dibatasi" description="Akun Anda tidak memiliki permission untuk melihat approval." icon="ki-outline ki-lock" />@else<div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Jenis</th><th>Modul</th><th>Lokasi</th><th>Risiko</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>@forelse($approvalRequests as $approval)<tr><td class="fw-bold text-capitalize">{{ str($approval->approval_type)->replace('_', ' ') }}</td><td>{{ $approval->module }}</td><td>{{ $approval->workLocation?->name ?: 'Global' }}</td><td><span class="badge {{ in_array($approval->risk_level, ['high', 'critical'], true) ? 'badge-light-danger' : 'badge-light-info' }} text-capitalize">{{ $approval->risk_level }}</span></td><td><x-metronic.status-badge :status="$approval->current_status->value" :label="$approval->current_status->label()" /></td><td class="text-end"><a href="{{ route('approvals.show', $approval) }}" class="btn btn-sm btn-light-primary">Detail</a></td></tr>@empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada approval" description="Permintaan approval pengguna akan tampil setelah transaksi sensitif diajukan." icon="ki-outline ki-check-circle" /></td></tr>@endforelse</tbody></table></div>@endif
                </div>

                <div class="tab-pane fade" id="user-activities-pane" role="tabpanel" aria-labelledby="user-activities-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5"><div><h2 class="fs-3 fw-bold mb-1">Aktivitas Terakhir</h2><div class="text-muted">Jejak aktivitas aplikasi yang dilakukan oleh pengguna.</div></div>@if($access['audit'])<a href="{{ route('audit-logs.index', ['actor_user_id' => $user->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-eye fs-5 me-1"></i>Buka Audit Lengkap</a>@endif</div>
                    @if(!$access['audit'])<x-metronic.empty-state title="Akses aktivitas dibatasi" description="Akun Anda tidak memiliki permission audit." icon="ki-outline ki-lock" />@else<div class="timeline-label">@forelse($recentActivities as $activity)<div class="timeline-item"><div class="timeline-label fw-semibold text-gray-600 fs-7">{{ $activity->created_at?->timezone(config('app.timezone'))->format('H:i') }}</div><div class="timeline-badge"><i class="ki-outline ki-right fs-3 text-primary"></i></div><div class="timeline-content ps-3"><div class="fw-bold text-gray-800">{{ $activity->description }}</div><div class="text-muted fs-7">{{ $activity->created_at?->timezone(config('app.timezone'))->translatedFormat('d M Y') }}</div></div></div>@empty<x-metronic.empty-state title="Belum ada aktivitas" description="Aktivitas penting pengguna akan tampil setelah tercatat oleh sistem." icon="ki-outline ki-time" />@endforelse</div>@endif
                </div>

                <div class="tab-pane fade" id="user-security-pane" role="tabpanel" aria-labelledby="user-security-tab" tabindex="0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5"><div><h2 class="fs-3 fw-bold mb-1">Keamanan Akun</h2><div class="text-muted">Status autentikasi dan tindakan administratif terhadap akun.</div></div><div class="d-flex flex-wrap gap-2">@if($access['security_manage'])<form method="POST" action="{{ route('admin.users.password-reset', $user) }}">@csrf<button type="submit" class="btn btn-sm btn-light-warning" data-confirm="Kirim tautan reset password kepada pengguna ini?"><i class="ki-outline ki-key fs-5 me-1"></i>Kirim Reset Password</button></form>@endif @can('update', $user)<a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-pencil fs-5 me-1"></i>Ubah Akun</a>@endcan</div></div>
                    <div class="row g-5 mb-6"><div class="col-md-4"><div class="border border-gray-300 rounded p-5 h-100"><div class="text-muted fs-8 text-uppercase fw-bold mb-2">Email</div><div class="fw-semibold text-break">{{ $user->email }}</div><span class="badge mt-3 {{ $user->email_verified_at ? 'badge-light-success' : 'badge-light-warning' }}">{{ $user->email_verified_at ? 'Sudah diverifikasi' : 'Belum diverifikasi' }}</span></div></div><div class="col-md-4"><div class="border border-gray-300 rounded p-5 h-100"><div class="text-muted fs-8 text-uppercase fw-bold mb-2">Login Terakhir</div><div class="fw-semibold">{{ $user->last_login_at?->timezone(config('app.timezone'))->translatedFormat('d M Y, H:i:s') ?: 'Belum pernah login' }}</div></div></div><div class="col-md-4"><div class="border border-gray-300 rounded p-5 h-100"><div class="text-muted fs-8 text-uppercase fw-bold mb-2">Status Akses</div><x-metronic.status-badge :status="$user->is_active ? 'active' : 'inactive'" :label="$user->is_active ? 'Dapat Login' : 'Login Ditolak'" /></div></div></div>
                    @can('update', $user)
                        @if($user->is_active && !auth()->user()?->is($user))<div class="alert alert-light-danger d-flex flex-wrap align-items-center justify-content-between gap-3 mb-0"><div><div class="fw-bold">Nonaktifkan akses pengguna</div><div class="text-muted">Akun tidak dapat login, tetapi histori transaksi tetap tersimpan.</div></div><form method="POST" action="{{ route('admin.users.deactivate', $user) }}">@csrf @method('PATCH')<button type="submit" class="btn btn-sm btn-danger" data-confirm="Nonaktifkan pengguna ini? Histori transaksi tetap disimpan."><i class="ki-outline ki-cross-circle fs-5 me-1"></i>Nonaktifkan</button></form></div>@endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection
