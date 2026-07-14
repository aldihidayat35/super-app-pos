<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h2>{{ $payload['definitions'][0] ?? 'Laporan GudangToko' }}</h2>
    <p class="muted">Report: {{ $export->report_type }} · Dibuat: {{ now('Asia/Jakarta')->format('d/m/Y H:i') }}</p>
    <table>
        <thead><tr>@foreach($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($payload['rows'] as $row)
                <tr>@foreach($headings as $heading)<td>{{ is_array($row[$heading] ?? null) ? json_encode($row[$heading], JSON_UNESCAPED_UNICODE) : ($row[$heading] ?? '') }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
