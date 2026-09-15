@extends('layouts.metronic.app')

@php
    use App\Enums\WorkChecklistItemStatus;
    use App\Enums\WorkChecklistStatus;
    $canRecap = auth()->user()->can('work_checklists.view_team') || auth()->user()->can('work_checklists.view_all');
    $canManageTemplates = auth()->user()->can('work_checklists.manage_templates');
    $statusTone = fn ($status) => match ($status instanceof \BackedEnum ? $status->value : $status) {
        'done', 'completed' => 'success', 'blocked' => 'danger', 'not_applicable' => 'secondary', 'pending', 'open' => 'warning', default => 'primary'
    };
@endphp

@section('title', 'Checklist Kerja - '.config('app.name'))
@section('page_title', 'Checklist Kerja')
@section('page_description', 'Pengecekan pekerjaan harian dan mingguan berdasarkan akun, role, serta lokasi tugas.')

@section('page_guide')
    <x-metronic.page-guide id="work-checklists" title="Panduan Checklist Kerja">
        <x-slot:function><p>Mencatat pemeriksaan kerja setiap akun dan menyediakan rekap penyelesaian untuk atasan sesuai lokasi.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih checklist harian atau mingguan yang aktif.</li><li>Isi status setiap poin dan tambahkan catatan bila terkendala atau tidak berlaku.</li><li>Selesaikan checklist setelah semua poin direspons.</li><li>Atasan memantau hasil melalui tab Rekap Tim.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Checklist Saya:</strong> tugas yang sedang aktif.</li><li><strong>Riwayat:</strong> hasil akun sendiri.</li><li><strong>Rekap Tim:</strong> kepatuhan dan kendala bawahan.</li><li><strong>Pengaturan:</strong> versi template untuk periode berikutnya.</li></ul></x-slot:parts>
        <x-slot:warnings><div class="alert alert-warning mb-0">Status Terkendala dan Tidak Berlaku wajib disertai catatan. Koreksi setelah selesai juga wajib memiliki alasan.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Checklist Kerja" description="Selesaikan pemeriksaan rutin dan pantau tindak lanjut pekerjaan dari satu halaman." />

    <div class="card mb-5">
        <div class="card-body py-3">
            <ul class="nav nav-tabs nav-line-tabs border-0 fs-6 fw-semibold">
                <li class="nav-item"><a class="nav-link {{ $tab === 'mine' ? 'active' : '' }}" href="{{ route('work-checklists.index', ['tab' => 'mine']) }}"><i class="ki-outline ki-check-square me-2"></i>Checklist Saya</a></li>
                <li class="nav-item"><a class="nav-link {{ $tab === 'history' ? 'active' : '' }}" href="{{ route('work-checklists.index', ['tab' => 'history']) }}"><i class="ki-outline ki-time me-2"></i>Riwayat</a></li>
                @if($canRecap)<li class="nav-item"><a class="nav-link {{ $tab === 'recap' ? 'active' : '' }}" href="{{ route('work-checklists.index', ['tab' => 'recap']) }}"><i class="ki-outline ki-chart-simple me-2"></i>Rekap Tim</a></li>@endif
                @if($canManageTemplates)<li class="nav-item"><a class="nav-link {{ $tab === 'templates' ? 'active' : '' }}" href="{{ route('work-checklists.index', ['tab' => 'templates']) }}"><i class="ki-outline ki-setting-2 me-2"></i>Pengaturan Template</a></li>@endif
            </ul>
        </div>
    </div>

    @if($tab === 'mine')
        <div class="row g-5">
            @forelse($currentRuns as $run)
                @php
                    $responded = $run->items->where('status', '!=', WorkChecklistItemStatus::PENDING)->count();
                    $total = $run->items->count();
                    $percent = $total === 0 ? 0 : (int) round(($responded / $total) * 100);
                    $late = $run->first_completed_at ? $run->first_completed_at->greaterThan($run->due_at) : now()->greaterThan($run->due_at);
                    $isAttendanceChecklist = $activeAttendance
                        && $run->frequency->value === 'daily'
                        && (int) $run->work_location_id === (int) $activeAttendance->work_location_id
                        && $run->period_start->toDateString() === $activeAttendance->attendance_date->toDateString();
                @endphp
                <div class="col-12">
                    <div class="card border border-gray-300 shadow-none">
                        <div class="card-header border-0 pt-5 align-items-start">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <h2 class="fs-4 fw-bold mb-0">Checklist {{ $run->frequency->label() }}</h2>
                                    <span class="badge badge-light-{{ $statusTone($run->status) }}">{{ $run->status->label() }}</span>
                                    @if($late)<span class="badge badge-light-danger">Terlambat</span>@endif
                                </div>
                                <div class="text-muted fs-7">{{ $run->period_start->translatedFormat('d M Y') }} – {{ $run->period_end->translatedFormat('d M Y') }} · {{ $run->workLocation?->name ?? 'Lingkup global' }}</div>
                                <div class="text-muted fs-8 mt-1">Role: {{ collect($run->role_snapshot)->map(fn ($role) => config("rbac.roles.{$role}.label", str($role)->replace('_', ' ')->title()))->join(', ') }}</div>
                            </div>
                            <div class="text-end"><div class="fs-2 fw-bold text-gray-900">{{ $percent }}%</div><div class="text-muted fs-8">{{ $responded }} dari {{ $total }} direspons</div></div>
                        </div>
                        <div class="card-body pt-3">
                            <div class="progress h-8px mb-5"><div class="progress-bar bg-{{ $percent === 100 ? 'success' : 'primary' }}" style="width: {{ $percent }}%" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div></div>
                            <div class="alert {{ $late ? 'alert-danger' : 'alert-primary' }} d-flex align-items-center py-3 mb-5"><i class="ki-outline ki-time fs-2 me-3"></i><div><strong>Batas waktu:</strong> {{ $run->due_at->translatedFormat('d M Y, H:i') }} WIB. @if($late)Checklist tetap dapat diselesaikan dan akan tercatat terlambat.@endif</div></div>

                            <div class="d-flex flex-column gap-3">
                                @foreach($run->items as $item)
                                    <form method="POST" action="{{ route('work-checklists.items.update', $item) }}" class="border border-gray-300 rounded p-4">
                                        @csrf @method('PATCH')
                                        <div class="row g-3 align-items-end">
                                            <div class="col-xl-5">
                                                <div class="d-flex align-items-start gap-3"><span class="badge badge-light-primary mt-1">{{ $loop->iteration }}</span><div><div class="fw-semibold text-gray-900">{{ $item->label }}</div>@if($item->guidance)<div class="text-muted fs-8 mt-1">{{ $item->guidance }}</div>@endif<div class="text-muted fs-8 mt-1">{{ collect($item->role_snapshot)->map(fn ($role) => config("rbac.roles.{$role}.label", $role))->join(', ') }}</div></div></div>
                                            </div>
                                            <div class="col-xl-2 col-md-4"><label class="form-label fs-8">Status</label><select name="status" class="form-select form-select-sm form-select-solid" @disabled($run->status === WorkChecklistStatus::COMPLETED)>@foreach($itemStatuses as $option)<option value="{{ $option->value }}" @selected($item->status === $option)>{{ $option->label() }}</option>@endforeach</select></div>
                                            <div class="col-xl-4 col-md-6"><label class="form-label fs-8">Catatan</label><input name="note" class="form-control form-control-sm form-control-solid" value="{{ $item->note }}" maxlength="2000" placeholder="Wajib untuk kendala/tidak berlaku" @disabled($run->status === WorkChecklistStatus::COMPLETED)></div>
                                            <div class="col-xl-1 col-md-2"><button class="btn btn-sm btn-light-primary w-100" @disabled($run->status === WorkChecklistStatus::COMPLETED)>Simpan</button></div>
                                        </div>
                                    </form>
                                @endforeach
                            </div>

                            <div class="d-flex justify-content-end mt-5">
                                @if($isAttendanceChecklist)
                                    <form method="POST" action="{{ route('work-checklists.complete-and-check-out', $run) }}">@csrf<button class="btn btn-danger" @disabled($responded !== $total)><i class="ki-outline ki-exit-right fs-5"></i>{{ $run->status === WorkChecklistStatus::COMPLETED ? 'Absen Pulang' : 'Selesaikan Checklist & Absen Pulang' }}</button></form>
                                @elseif($run->status === WorkChecklistStatus::COMPLETED)
                                    <form method="POST" action="{{ route('work-checklists.reopen', $run) }}" class="d-flex flex-wrap gap-2 justify-content-end">@csrf<input name="reason" class="form-control form-control-sm w-auto" required minlength="5" maxlength="1000" placeholder="Alasan koreksi"><button class="btn btn-light-warning"><i class="ki-outline ki-pencil fs-5"></i>Koreksi</button></form>
                                @else
                                    <form method="POST" action="{{ route('work-checklists.complete', $run) }}">@csrf<button class="btn btn-primary" @disabled($responded !== $total)><i class="ki-outline ki-check-circle fs-5"></i>Selesaikan Checklist</button></form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><x-metronic.empty-state title="Belum ada checklist aktif" description="Checklist akan dibuat sesuai role dan lokasi aktif akun Anda." /></div>
            @endforelse
        </div>
    @endif

    @if($tab === 'history')
        <x-metronic.card title="Riwayat Checklist Saya">
            @include('work-checklists.partials.filters', ['targetTab' => 'history', 'showUserRole' => false])
            <div class="table-responsive mt-5"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted text-uppercase fs-8"><th>Periode</th><th>Lingkup</th><th>Progress</th><th>Status</th><th>Waktu Selesai</th><th></th></tr></thead><tbody>
                @forelse($history as $run)
                    @php
                        $answered = $run->items->where('status', '!=', WorkChecklistItemStatus::PENDING)->count();
                    @endphp
                    <tr><td><div class="fw-semibold">{{ $run->frequency->label() }}</div><div class="text-muted fs-8">{{ $run->period_start->format('d/m/Y') }} – {{ $run->period_end->format('d/m/Y') }}</div></td><td>{{ $run->workLocation?->name ?? 'Global' }}</td><td>{{ $answered }}/{{ $run->items->count() }}</td><td><span class="badge badge-light-{{ $statusTone($run->status) }}">{{ $run->status->label() }}</span>@if($run->first_completed_at?->greaterThan($run->due_at)) <span class="badge badge-light-danger">Terlambat</span>@endif</td><td>{{ $run->completed_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td><td class="text-end"><a class="btn btn-sm btn-light-primary" href="{{ route('work-checklists.index', array_merge(request()->query(), ['tab'=>'history','checklist_id'=>$run->id])) }}">Detail</a></td></tr>
                @empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada riwayat" description="Riwayat muncul setelah checklist dibuat." /></td></tr>@endforelse
            </tbody></table></div>{{ $history->links() }}
        </x-metronic.card>
    @endif

    @if($tab === 'recap' && $canRecap)
        @php
            $kpis = [['Wajib',$recapMetrics['expected'],'primary'],['Selesai',$recapMetrics['completed'],'success'],['Tepat Waktu',$recapMetrics['on_time'],'info'],['Terlambat',$recapMetrics['late'],'danger'],['Belum Selesai',$recapMetrics['open'],'warning'],['Terkendala',$recapMetrics['blocked'],'danger']];
        @endphp
        <div class="row g-4 mb-5">@foreach($kpis as [$label,$value,$tone])<div class="col-xl-2 col-md-4 col-6"><div class="card border border-gray-200 shadow-none h-100"><div class="card-body p-4"><div class="text-muted fs-8 text-uppercase fw-bold">{{ $label }}</div><div class="fs-2 fw-bold text-{{ $tone }} mt-2">{{ number_format($value,0,',','.') }}</div></div></div></div>@endforeach</div>
        <div class="row g-4 mb-5"><div class="col-md-6"><div class="card bg-light-primary border-0"><div class="card-body"><div class="text-muted">Tingkat Pengisian</div><div class="fs-2 fw-bold text-primary">{{ number_format($recapMetrics['response_rate'],1,',','.') }}%</div><div class="text-muted fs-8">Poin yang sudah diberi respons.</div></div></div></div><div class="col-md-6"><div class="card bg-light-success border-0"><div class="card-body"><div class="text-muted">Tingkat Penyelesaian</div><div class="fs-2 fw-bold text-success">{{ number_format($recapMetrics['completion_rate'],1,',','.') }}%</div><div class="text-muted fs-8">Poin selesai dari poin yang berlaku.</div></div></div></div></div>
        <x-metronic.card title="Rekap Tim">
            <div class="d-flex justify-content-end mb-3"><a class="btn btn-light-success" href="{{ route('work-checklists.export', request()->except(['tab','recap_page','history_page','checklist_id'])) }}"><i class="ki-outline ki-file-down fs-5"></i>Unduh CSV</a></div>
            @include('work-checklists.partials.filters', ['targetTab' => 'recap', 'showUserRole' => true])
            <div class="table-responsive mt-5"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted text-uppercase fs-8"><th>Akun</th><th>Periode</th><th>Lokasi</th><th>Hasil Poin</th><th>Status</th><th></th></tr></thead><tbody>
                @forelse($recap as $run)<tr><td><div class="fw-semibold">{{ $run->user?->name }}</div><div class="text-muted fs-8">{{ collect($run->role_snapshot)->map(fn($role)=>config("rbac.roles.{$role}.label",$role))->join(', ') }}</div></td><td>{{ $run->frequency->label() }}<div class="text-muted fs-8">{{ $run->period_start->format('d/m/Y') }}</div></td><td>{{ $run->workLocation?->name ?? 'Global' }}</td><td><span class="text-success">{{ $run->items->where('status',WorkChecklistItemStatus::DONE)->count() }} selesai</span> · <span class="text-danger">{{ $run->items->where('status',WorkChecklistItemStatus::BLOCKED)->count() }} kendala</span></td><td><span class="badge badge-light-{{ $statusTone($run->status) }}">{{ $run->status->label() }}</span>@if($run->first_completed_at?->greaterThan($run->due_at))<span class="badge badge-light-danger">Terlambat</span>@endif</td><td class="text-end"><a class="btn btn-sm btn-light-primary" href="{{ route('work-checklists.index', array_merge(request()->query(), ['tab'=>'recap','checklist_id'=>$run->id])) }}">Detail</a></td></tr>@empty<tr><td colspan="6"><x-metronic.empty-state title="Belum ada data rekap" description="Ubah filter atau tunggu checklist akun terbentuk." /></td></tr>@endforelse
            </tbody></table></div>{{ $recap?->links() }}
        </x-metronic.card>
    @endif

    @if($tab === 'templates' && $canManageTemplates)
        @php
            $formTemplate = $editingTemplate;
            $formItems = old('items', $formTemplate?->items->map(fn($i)=>['item_key'=>$i->item_key,'label'=>$i->label,'guidance'=>$i->guidance,'is_required'=>$i->is_required?1:0])->all() ?? [['item_key'=>'','label'=>'','guidance'=>'','is_required'=>1]]);
            $formRoles = old('roles', $formTemplate?->roles->pluck('role_name')->all() ?? []);
        @endphp
        <div class="row g-5">
            <div class="col-xl-5"><x-metronic.card :title="$formTemplate ? 'Buat Versi Baru' : 'Template Baru'">
                <form method="POST" action="{{ $formTemplate ? route('work-checklists.templates.version',$formTemplate) : route('work-checklists.templates.store') }}">@csrf @if($formTemplate)@method('PUT')<input type="hidden" name="source_template_id" value="{{ $formTemplate->id }}">@endif
                    <div class="row g-4">
                        <div class="col-md-5"><label class="form-label">Kode</label><input name="template_key" value="{{ old('template_key',$formTemplate?->template_key) }}" class="form-control form-control-solid" {{ $formTemplate?'readonly':'' }} required></div>
                        <div class="col-md-7"><label class="form-label">Nama</label><input name="name" value="{{ old('name',$formTemplate?->name) }}" class="form-control form-control-solid" required></div>
                        <div class="col-md-4"><label class="form-label">Frekuensi</label><select name="frequency" class="form-select form-select-solid"><option value="daily" @selected(old('frequency',$formTemplate?->frequency?->value)==='daily')>Harian</option><option value="weekly" @selected(old('frequency',$formTemplate?->frequency?->value)==='weekly')>Mingguan</option></select></div>
                        <div class="col-md-4"><label class="form-label">Lingkup</label><select name="scope" class="form-select form-select-solid"><option value="global" @selected(old('scope',$formTemplate?->scope)==='global')>Global</option><option value="location" @selected(old('scope',$formTemplate?->scope)==='location')>Lokasi</option></select></div>
                        <div class="col-md-4"><label class="form-label">Jenis Lokasi</label><select name="location_type" class="form-select form-select-solid"><option value="">—</option><option value="warehouse" @selected(old('location_type',$formTemplate?->location_type)==='warehouse')>Gudang</option><option value="branch" @selected(old('location_type',$formTemplate?->location_type)==='branch')>Toko</option></select></div>
                        <div class="col-12"><label class="form-label">Mulai Berlaku</label><input type="date" name="effective_from" min="{{ now()->addDay()->toDateString() }}" value="{{ old('effective_from',now()->addDay()->toDateString()) }}" class="form-control form-control-solid" required><div class="text-muted fs-8 mt-1">Template mingguan otomatis dimulai pada hari Senin.</div></div>
                        <div class="col-12"><label class="form-label">Role</label><div class="row g-2">@foreach($roles as $roleName=>$meta)<div class="col-md-6"><label class="form-check form-check-custom form-check-solid"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $roleName }}" @checked(in_array($roleName,$formRoles,true))><span class="form-check-label fs-8">{{ $meta['label'] }}</span></label></div>@endforeach</div></div>
                        <div class="col-12"><div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label mb-0">Poin Checklist</label><button type="button" class="btn btn-sm btn-light-primary" data-add-checklist-item>Tambah Poin</button></div><div data-checklist-items class="d-flex flex-column gap-3">@foreach($formItems as $index=>$formItem)<div class="border rounded p-3" data-checklist-item><div class="row g-2"><div class="col-md-5"><input name="items[{{ $index }}][item_key]" value="{{ $formItem['item_key'] }}" class="form-control form-control-sm" placeholder="kode.poin" required></div><div class="col-md-7"><input name="items[{{ $index }}][label]" value="{{ $formItem['label'] }}" class="form-control form-control-sm" placeholder="Uraian pekerjaan" required></div><div class="col-md-10"><input name="items[{{ $index }}][guidance]" value="{{ $formItem['guidance'] }}" class="form-control form-control-sm" placeholder="Panduan opsional"></div><div class="col-md-2"><button type="button" class="btn btn-sm btn-light-danger w-100" data-remove-checklist-item>Hapus</button></div><input type="hidden" name="items[{{ $index }}][is_required]" value="1"></div></div>@endforeach</div></div>
                        <div class="col-12 d-flex justify-content-end gap-2">@if($formTemplate)<a href="{{ route('work-checklists.index',['tab'=>'templates']) }}" class="btn btn-light">Batal</a>@endif<button class="btn btn-primary">{{ $formTemplate?'Jadwalkan Versi':'Buat Template' }}</button></div>
                    </div>
                </form>
            </x-metronic.card></div>
            <div class="col-xl-7"><x-metronic.card title="Template Aktif dan Terjadwal"><div class="d-flex flex-column gap-3">@forelse($templates as $template)<div class="border border-gray-300 rounded p-4"><div class="d-flex justify-content-between gap-3"><div><div class="fw-bold">{{ $template->name }} <span class="badge badge-light-primary">v{{ $template->version }}</span></div><div class="text-muted fs-8 mt-1">{{ $template->frequency->label() }} · {{ $template->scope==='global'?'Global':($template->location_type==='warehouse'?'Gudang':'Toko') }} · Berlaku {{ $template->effective_from->format('d/m/Y') }}{{ $template->effective_until?' s.d. '.$template->effective_until->format('d/m/Y'):'' }}</div><div class="mt-2">@foreach($template->roles as $role)<span class="badge badge-light me-1">{{ config("rbac.roles.{$role->role_name}.label",$role->role_name) }}</span>@endforeach</div><div class="text-muted fs-8 mt-2">{{ $template->items->count() }} poin</div></div><div class="d-flex align-items-start gap-2"><a href="{{ route('work-checklists.index',['tab'=>'templates','edit_template'=>$template->id]) }}" class="btn btn-sm btn-light-primary">Versi Baru</a>@if(!$template->effective_until)<form method="POST" action="{{ route('work-checklists.templates.deactivate',$template) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-light-danger">Nonaktifkan</button></form>@endif</div></div></div>@empty<x-metronic.empty-state title="Belum ada template" description="Buat template pertama untuk memulai checklist." />@endforelse</div></x-metronic.card></div>
        </div>
    @endif

    @if($selected)
        <div class="card mt-5 border border-primary"><div class="card-header"><h3 class="card-title">Detail Checklist: {{ $selected->user?->name }}</h3><div class="card-toolbar"><a href="{{ route('work-checklists.index',array_merge(request()->except('checklist_id'),['tab'=>$tab])) }}" class="btn btn-sm btn-light">Tutup</a></div></div><div class="card-body"><div class="row g-3 mb-4"><div class="col-md-4"><span class="text-muted">Periode</span><div class="fw-semibold">{{ $selected->frequency->label() }}, {{ $selected->period_start->format('d/m/Y') }} – {{ $selected->period_end->format('d/m/Y') }}</div></div><div class="col-md-4"><span class="text-muted">Lokasi</span><div class="fw-semibold">{{ $selected->workLocation?->name ?? 'Global' }}</div></div><div class="col-md-4"><span class="text-muted">Status</span><div><span class="badge badge-light-{{ $statusTone($selected->status) }}">{{ $selected->status->label() }}</span></div></div></div><div class="table-responsive"><table class="table table-row-dashed"><thead><tr><th>Poin</th><th>Status</th><th>Catatan</th><th>Waktu Respons</th></tr></thead><tbody>@foreach($selected->items as $item)<tr><td class="fw-semibold">{{ $item->label }}</td><td><span class="badge badge-light-{{ $statusTone($item->status) }}">{{ $item->status->label() }}</span></td><td>{{ $item->note ?: '—' }}</td><td>{{ $item->responded_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td></tr>@endforeach</tbody></table></div></div></div>
    @endif

    @push('scripts')
        <script>document.addEventListener('DOMContentLoaded',()=>{const box=document.querySelector('[data-checklist-items]');const add=document.querySelector('[data-add-checklist-item]');if(!box||!add)return;const bind=()=>box.querySelectorAll('[data-remove-checklist-item]').forEach(button=>button.onclick=()=>{if(box.children.length>1)button.closest('[data-checklist-item]').remove()});add.addEventListener('click',()=>{const index=Date.now();const row=document.createElement('div');row.className='border rounded p-3';row.dataset.checklistItem='';row.innerHTML=`<div class="row g-2"><div class="col-md-5"><input name="items[${index}][item_key]" class="form-control form-control-sm" placeholder="kode.poin" required></div><div class="col-md-7"><input name="items[${index}][label]" class="form-control form-control-sm" placeholder="Uraian pekerjaan" required></div><div class="col-md-10"><input name="items[${index}][guidance]" class="form-control form-control-sm" placeholder="Panduan opsional"></div><div class="col-md-2"><button type="button" class="btn btn-sm btn-light-danger w-100" data-remove-checklist-item>Hapus</button></div><input type="hidden" name="items[${index}][is_required]" value="1"></div>`;box.appendChild(row);bind()});bind()});</script>
    @endpush
@endsection
