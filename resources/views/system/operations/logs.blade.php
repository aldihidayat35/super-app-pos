@extends('layouts.metronic.app')

@section('title', 'Log Aplikasi dan Queue')
@section('page_title', 'Log Aplikasi dan Queue')

@section('content')
    <x-metronic.page-title title="Log Aplikasi dan Queue" description="OPS-02 — Error aplikasi, failed jobs, scheduler, integrasi, filter level, retry job, dan resolve marker tersanitasi." />

    <div class="row g-5 mb-5">
        <div class="col-md-4"><x-metronic.card><div class="text-muted fs-7">Scheduler Heartbeat</div><div class="fs-5 fw-bold">{{ $schedulerHeartbeat ?: 'Belum ada heartbeat' }}</div></x-metronic.card></div>
        <div class="col-md-4"><x-metronic.card><div class="text-muted fs-7">Failed Jobs</div><div class="fs-3 fw-bold">{{ count($failedJobs) }}</div></x-metronic.card></div>
        <div class="col-md-4"><x-metronic.card><div class="text-muted fs-7">Log Ditampilkan</div><div class="fs-3 fw-bold">{{ count($lines) }}</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Filter Log">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select name="level" class="form-select">
                    <option value="">Semua level</option>
                    @foreach (['error', 'warning', 'info', 'debug'] as $item)
                        <option value="{{ $item }}" @selected($level === $item)>{{ ucfirst($item) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-light-primary">Filter</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Failed Jobs" class="mt-5">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>ID</th><th>Queue</th><th>Gagal Pada</th><th>Exception</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($failedJobs as $job)
                        <tr>
                            <td>{{ $job->id }}</td>
                            <td>{{ $job->connection }} / {{ $job->queue }}</td>
                            <td>{{ $job->failed_at }}</td>
                            <td><pre class="small mb-0">{{ $job->exception }}</pre></td>
                            <td class="text-end"><form method="POST" action="{{ route('admin.system.logs.failed-jobs.retry', $job->id) }}">@csrf<button class="btn btn-sm btn-light-warning">Retry</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-metronic.empty-state title="Tidak ada failed job" description="Queue berjalan bersih." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>

    <x-metronic.card title="Log Terbaru" class="mt-5">
        <form method="POST" action="{{ route('admin.system.logs.resolve') }}" class="row g-3 mb-5">
            @csrf
            <div class="col-md-8"><input name="note" class="form-control" placeholder="Catatan resolve marker, contoh: Error pembayaran sudah ditangani."></div>
            <div class="col-md-2"><button class="btn btn-light-success w-100">Resolve Marker</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr><th>#</th><th>Level</th><th>Pesan Tersanitasi</th></tr></thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr><td>{{ $line['line'] }}</td><td><x-metronic.status-badge :status="$line['level']" /></td><td><pre class="small mb-0">{{ $line['message'] }}</pre></td></tr>
                    @empty
                        <tr><td colspan="3"><x-metronic.empty-state title="Belum ada log" description="File log aplikasi belum tersedia." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
@endsection
