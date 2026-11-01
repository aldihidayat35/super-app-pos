@extends('layouts.metronic.app')
@section('title', $currentReport['label'].' - '.config('app.name'))
@section('hide_toolbar', '1')
@section('content')
    @php
        $systemSummary = $report['summary'] ?? [];
        $auditRows = collect($report['rows'] ?? [])->map(fn (array $row): array => [
            !empty($row['occurred_at']) ? \Illuminate\Support\Carbon::parse($row['occurred_at'])->translatedFormat('d M H:i') : '-',
            (string) ($row['module'] ?? '-'),
            (string) ($row['event'] ?? '-'),
            strtoupper((string) ($row['severity'] ?? 'info')),
        ])->all();
        $securityRows = collect($report['rows'] ?? [])->filter(fn (array $row): bool => (string) ($row['module'] ?? '') === 'security')->map(fn (array $row): array => [
            !empty($row['occurred_at']) ? \Illuminate\Support\Carbon::parse($row['occurred_at'])->translatedFormat('d M H:i') : '-',
            (string) ($row['event'] ?? '-'),
            (string) ($row['subject_type'] ?? '-'),
            strtoupper((string) ($row['severity'] ?? 'info')),
        ])->values()->all();
        $dashboard = [
            'theme' => 'system', 'icon' => 'ki-outline ki-shield-tick', 'eyebrow' => 'SYSTEM CONTROL CENTER',
            'title' => 'Laporan Super Admin', 'description' => 'Audit, keamanan, anomali, approval, integrasi, dan kesehatan data aplikasi.',
            'summary_title' => 'Kesehatan Sistem', 'summary_subtitle' => 'Pemantauan lintas modul untuk periode aktif',
            'kpis' => [
                ['label' => 'Audit Event', 'value' => (string) data_get($systemSummary, 'audit_events', 0), 'note' => 'Aktivitas tercatat'],
                ['label' => 'Anomali Terbuka', 'value' => (string) data_get($systemSummary, 'anomaly_open', 0), 'note' => 'Perlu investigasi'],
                ['label' => 'Approval Menunggu', 'value' => (string) data_get($systemSummary, 'approval_pending', 0), 'note' => 'Belum diputuskan'],
                ['label' => 'Security Event', 'value' => (string) data_get($systemSummary, 'security_events', 0), 'note' => 'Periode aktif'],
            ],
            'panels' => [
                ['title' => 'Audit Trail Terbaru', 'subtitle' => 'Perubahan dan aksi penting aplikasi', 'detail' => 'audit', 'columns' => ['Waktu', 'Modul', 'Event', 'Severity'], 'rows' => $auditRows],
                ['title' => 'Keamanan & Akses', 'subtitle' => 'Kejadian dari modul keamanan', 'detail' => 'security', 'metrics' => [
                    ['label' => 'Security Event', 'value' => (string) data_get($systemSummary, 'security_events', 0)],
                    ['label' => 'Anomali', 'value' => (string) data_get($systemSummary, 'anomaly_open', 0)],
                    ['label' => 'Approval', 'value' => (string) data_get($systemSummary, 'approval_pending', 0)],
                ], 'columns' => ['Waktu', 'Event', 'Subjek', 'Severity'], 'rows' => $securityRows],
                ['title' => 'Prioritas Kontrol', 'subtitle' => 'Distribusi item pengawasan sistem', 'detail' => 'controls', 'bars' => [
                    ['label' => 'Audit Event', 'value' => (string) data_get($systemSummary, 'audit_events', 0), 'numeric' => (int) data_get($systemSummary, 'audit_events', 0)],
                    ['label' => 'Anomali Terbuka', 'value' => (string) data_get($systemSummary, 'anomaly_open', 0), 'numeric' => (int) data_get($systemSummary, 'anomaly_open', 0)],
                    ['label' => 'Approval Menunggu', 'value' => (string) data_get($systemSummary, 'approval_pending', 0), 'numeric' => (int) data_get($systemSummary, 'approval_pending', 0)],
                    ['label' => 'Security Event', 'value' => (string) data_get($systemSummary, 'security_events', 0), 'numeric' => (int) data_get($systemSummary, 'security_events', 0)],
                ]],
            ],
            'links' => ['audit' => 'Audit Trail', 'security' => 'Keamanan & Akses', 'data-health' => 'Kesehatan Data', 'notifications' => 'Notifikasi', 'controls' => 'Anomali & Approval', 'exports' => 'Riwayat Ekspor', 'business' => 'Seluruh Bisnis'],
        ];
    @endphp
    @include('reports.profiles.partials.profile-overview-dashboard')
@endsection
