@extends('layouts.metronic.app')

@section('title', 'Backup Database dan File')
@section('page_title', 'Backup Database dan File')

@section('content')
    <x-metronic.page-title title="Backup Database dan File" description="OPS-01 — Backup terenkripsi, checksum, retention, download berizin, run now, dan instruksi restore tanpa menampilkan secret." />

    <div class="row g-5 mb-5">
        <div class="col-md-3"><x-metronic.card><div class="text-muted fs-7">Status Backup</div><div class="fs-3 fw-bold">{{ $backupEnabled ? 'Aktif' : 'Dry-run' }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card><div class="text-muted fs-7">Disk</div><div class="fs-3 fw-bold">{{ $disk }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card><div class="text-muted fs-7">Path</div><div class="fs-6 fw-bold text-break">{{ $path }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card><div class="text-muted fs-7">Retention</div><div class="fs-3 fw-bold">{{ $retentionDays }} hari</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Daftar Backup">
        <x-slot:toolbar>
            <form method="POST" action="{{ route('admin.system.backups.run') }}">
                @csrf
                <button class="btn btn-sm btn-primary" data-confirm="Jalankan backup sekarang?">
                    <i class="ki-outline ki-cloud-download fs-4"></i>Run Now
                </button>
            </form>
        </x-slot:toolbar>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead>
                    <tr>
                        <th>Nama File</th>
                        <th>Ukuran</th>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th>Checksum SHA-256</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($backups as $backup)
                        <tr>
                            <td class="fw-semibold">{{ $backup['name'] }}</td>
                            <td>{{ $backup['human_size'] }}</td>
                            <td>{{ $backup['last_modified_label'] }}</td>
                            <td><x-metronic.status-badge :status="$backup['status']" /></td>
                            <td><code class="small">{{ $backup['checksum'] }}</code></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-light-primary" href="{{ URL::temporarySignedRoute('admin.system.backups.download', now()->addMinutes(10), ['file' => $backup['encoded']]) }}">Download</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-metronic.empty-state title="Belum ada backup" description="Jalankan backup setelah konfigurasi production siap." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>

    <x-metronic.card title="Instruksi Restore" class="mt-5">
        <ol class="mb-0">
            <li>Download file `.sql.enc` melalui link signed dan simpan di staging.</li>
            <li>Dekripsi menggunakan app key environment yang sama melalui prosedur restore internal.</li>
            <li>Restore ke database staging terlebih dahulu, jalankan smoke test, lalu minta sign-off owner.</li>
            <li>Production restore hanya dilakukan pada maintenance window dengan backup baru sebelum restore.</li>
        </ol>
    </x-metronic.card>
@endsection
