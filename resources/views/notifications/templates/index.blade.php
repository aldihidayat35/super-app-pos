@extends('layouts.metronic.app')

@section('title', 'Template Pesan')
@section('page_title', 'Template Pesan')

@section('content')
    <x-metronic.page-title title="Template Pesan" description="NTF-02 placeholder tervalidasi, preview, channel, fallback, status, versi, dan histori ringkas." />

    @if(session('notification_preview'))
        <div class="alert alert-info"><strong>Preview:</strong><br>{{ session('notification_preview')['subject'] ?? '' }}<pre class="mb-0 mt-2">{{ session('notification_preview')['body'] }}</pre></div>
    @endif

    <x-metronic.card title="Tambah Template">
        <form method="POST" action="{{ route('admin.notifications.templates.store') }}" class="row g-3">
            @csrf
            <div class="col-md-2"><label class="form-label">Key</label><select name="key" class="form-select">@foreach($variableGroups as $key => $variables)<option value="{{ $key }}">{{ $key }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Channel</label><select name="channel_type" class="form-select">@foreach($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Subject</label><input name="subject" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
            <div class="col-md-6"><label class="form-label">Body</label><textarea name="body" rows="6" class="form-control" required>Ringkasan @{{ report_date }} omzet @{{ revenue }}. Detail: @{{ secure_link }}</textarea></div>
            <div class="col-md-3"><label class="form-label">Fallback</label><textarea name="fallback_body" rows="6" class="form-control"></textarea></div>
            <div class="col-md-3"><label class="form-label">Variable Whitelist Tambahan</label><textarea name="allowed_variables" rows="6" class="form-control" placeholder="pisahkan koma"></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Simpan Template</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Template" class="mt-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Template</th><th>Channel</th><th>Variable</th><th>Versi</th><th>Status</th><th>Body</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse($templates as $template)
                    <tr>
                        <td>{{ $template->name }}<div class="text-muted">{{ $template->key }}</div></td>
                        <td>{{ $template->channel_type->label() }}</td>
                        <td><span class="text-muted">{{ implode(', ', $template->allowed_variables ?: ($variableGroups[$template->key] ?? [])) }}</span></td>
                        <td>v{{ $template->version }}</td>
                        <td><x-metronic.status-badge :status="$template->is_active ? 'active' : 'inactive'" :label="$template->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-muted">{{ Str::limit($template->body, 90) }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.notifications.templates.preview', $template) }}" class="d-inline">@csrf <button class="btn btn-sm btn-light-info">Preview</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada template" description="Buat template untuk laporan harian dan alert." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $templates->links() }}
    </x-metronic.card>
@endsection
