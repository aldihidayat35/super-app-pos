@extends('layouts.metronic.app')

@section('title', 'Jadwal Laporan dan Alert')
@section('page_title', 'Jadwal Laporan dan Alert')

@section('content')
    <x-metronic.page-title title="Jadwal Laporan dan Alert" description="NTF-03 frekuensi, waktu Asia/Jakarta, template, penerima, scope lokasi, last/next run, dan dedupe scheduler." />

    <x-metronic.card title="Tambah Jadwal">
        <form method="POST" action="{{ route('admin.notifications.schedules.store') }}" class="row g-3">
            @csrf
            <div class="col-md-3"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Schedule Key</label><input name="schedule_key" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Frekuensi</label><select name="frequency" class="form-select"><option value="daily">Harian</option></select></div>
            <div class="col-md-2"><label class="form-label">Jam</label><input name="run_time" type="time" value="{{ config('notifications.daily_report_time', '08:00') }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Timezone</label><input name="timezone" value="Asia/Jakarta" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Report</label><select name="report_type" class="form-select"><option value="daily_report">Daily Report</option></select></div>
            <div class="col-md-2"><label class="form-label">Periode</label><select name="report_period" class="form-select"><option value="yesterday">Kemarin</option><option value="today">Hari Ini</option></select></div>
            <div class="col-md-3"><label class="form-label">Template</label><select name="template_id" class="form-select"><option value="">Auto aktif</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }} - {{ $template->channel_type->value }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Lokasi Scope</label><select name="work_location_id" class="form-select"><option value="">Semua lokasi sesuai penerima</option>@foreach($workLocations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Channel</label><select name="channel_types[]" class="form-select" multiple><option value="whatsapp" selected>WhatsApp</option><option value="telegram" selected>Telegram</option></select></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="is_active" class="form-select"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
            <div class="col-12"><button class="btn btn-primary">Simpan Jadwal</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Jadwal" class="mt-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nama</th><th>Waktu</th><th>Report/Periode</th><th>Channel</th><th>Template/Lokasi</th><th>Last/Next Run</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse($schedules as $schedule)
                    <tr>
                        <td>{{ $schedule->name }}<div class="text-muted">{{ $schedule->schedule_key }}</div></td>
                        <td>{{ $schedule->run_time }}<div class="text-muted">{{ $schedule->timezone }}</div></td>
                        <td>{{ $schedule->report_type }}<div class="text-muted">{{ $schedule->report_period }}</div></td>
                        <td>{{ implode(', ', $schedule->channel_types ?? []) }}</td>
                        <td>{{ $schedule->template?->name ?: 'Auto' }}<div class="text-muted">{{ $schedule->workLocation?->name ?: 'Semua lokasi' }}</div></td>
                        <td><div>{{ $schedule->last_run_at?->format('d/m/Y H:i') ?: '-' }}</div><span class="text-muted">{{ $schedule->next_run_at?->format('d/m/Y H:i') ?: 'Akan dihitung saat run' }}</span></td>
                        <td><x-metronic.status-badge :status="$schedule->is_active ? 'active' : 'inactive'" :label="$schedule->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.notifications.schedules.run', $schedule) }}" class="d-inline">@csrf <button class="btn btn-sm btn-light-primary">Run Now</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-metronic.empty-state title="Belum ada jadwal" description="Tambahkan jadwal laporan harian owner atau alert operasional." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $schedules->links() }}
    </x-metronic.card>
@endsection
