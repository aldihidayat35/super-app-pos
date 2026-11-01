@extends('layouts.metronic.app')
@section('title', $currentReport['label'].' - '.config('app.name'))
@section('hide_toolbar', '1')
@section('content')
    @php
        $userSummary = $report['summary'] ?? [];
        $roleBars = collect(data_get($report, 'charts.roles', []))->map(fn (array $row): array => [
            'label' => str((string) ($row['label'] ?? '-'))->replace('_', ' ')->title()->toString(),
            'value' => (string) ($row['value'] ?? 0),
            'numeric' => (int) ($row['value'] ?? 0),
        ])->all();
        $userRows = collect($report['rows'] ?? [])->map(fn (array $row): array => [
            (string) ($row['name'] ?? '-'),
            (string) ($row['username'] ?? $row['email'] ?? '-'),
            !empty($row['is_active']) ? 'Aktif' : 'Nonaktif',
            !empty($row['last_login_at']) ? \Illuminate\Support\Carbon::parse($row['last_login_at'])->translatedFormat('d M Y H:i') : 'Belum pernah',
        ])->all();
        $assignmentRows = collect(data_get($report, 'sections.assignments', []))->map(fn (array $row): array => [
            (string) ($row['user'] ?? '-'),
            (string) ($row['location'] ?? '-'),
            ucfirst((string) ($row['type'] ?? '-')),
            !empty($row['is_active']) ? 'Aktif' : 'Nonaktif',
        ])->all();
        $dashboard = [
            'theme' => 'users', 'icon' => 'ki-outline ki-profile-user', 'eyebrow' => 'ADMINISTRASI IDENTITAS',
            'title' => 'Laporan Pengguna', 'description' => 'Kesehatan akun, role, akses, login, dan penempatan lokasi kerja.',
            'summary_title' => 'Ringkasan Pengguna', 'summary_subtitle' => 'Tidak menampilkan password, token, session, atau kredensial pengguna',
            'kpis' => [
                ['label' => 'Total Pengguna', 'value' => (string) data_get($userSummary, 'total_users', 0), 'note' => 'Seluruh akun'],
                ['label' => 'Pengguna Aktif', 'value' => (string) data_get($userSummary, 'active_users', 0), 'note' => 'Dapat masuk aplikasi'],
                ['label' => 'Pengguna Nonaktif', 'value' => (string) data_get($userSummary, 'inactive_users', 0), 'note' => 'Akses dihentikan'],
                ['label' => 'Tanpa Role', 'value' => (string) data_get($userSummary, 'without_role', 0), 'note' => 'Perlu diperiksa'],
            ],
            'panels' => [
                ['title' => 'Kesehatan Akses', 'subtitle' => 'Akun yang membutuhkan tindak lanjut', 'detail' => 'activity', 'metrics' => [
                    ['label' => 'Belum Pernah Login', 'value' => (string) data_get($userSummary, 'never_logged_in', 0)],
                    ['label' => 'Tanpa Role', 'value' => (string) data_get($userSummary, 'without_role', 0)],
                    ['label' => 'Tanpa Lokasi', 'value' => (string) data_get($userSummary, 'without_location', 0)],
                ], 'columns' => ['Nama', 'Login', 'Status', 'Login Terakhir'], 'rows' => $userRows],
                ['title' => 'Distribusi Role', 'subtitle' => 'Sebaran pengguna berdasarkan role', 'detail' => 'roles', 'bars' => $roleBars],
                ['title' => 'Penempatan Lokasi', 'subtitle' => 'Penugasan lokasi kerja yang aktif', 'detail' => 'locations', 'columns' => ['Pengguna', 'Lokasi', 'Tipe', 'Status'], 'rows' => $assignmentRows],
            ],
            'links' => ['activity' => 'Aktivitas & Login', 'roles' => 'Role & Permission', 'locations' => 'Penempatan Lokasi', 'audit' => 'Audit Akun'],
        ];
    @endphp
    @include('reports.profiles.partials.profile-overview-dashboard')
@endsection
