<?php
/** @var \Illuminate\View\ComponentAttributeBag $attributes */
/** @var array|null $before */
/** @var array|null $after */

$diffs = [];

if (is_array($before) && is_array($after)) {
    $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));
    foreach ($allKeys as $key) {
        $beforeVal = $before[$key] ?? null;
        $afterVal = $after[$key] ?? null;

        $beforeStr = is_array($beforeVal) ? json_encode($beforeVal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : ($beforeVal === null ? '<span class="text-muted">null</span>' : htmlspecialchars((string) $beforeVal));
        $afterStr = is_array($afterVal) ? json_encode($afterVal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : ($afterVal === null ? '<span class="text-muted">null</span>' : htmlspecialchars((string) $afterVal));

        $changed = (string) $beforeVal !== (string) $afterVal;
        $added = !array_key_exists($key, $before);
        $removed = !array_key_exists($key, $after);

        $diffs[] = [
            'key' => $key,
            'before' => $beforeStr,
            'after' => $afterStr,
            'changed' => $changed,
            'added' => $added,
            'removed' => $removed,
        ];
    }
}
?>

<div {{ $attributes->class(['diff-view']) }}>
    @if (empty($diffs))
        <div class="text-muted small fst-italic p-2">Tidak ada perbedaan</div>
    @else
        <table class="diff-table table table-sm table-borderless mb-0">
            <thead>
                <tr class="text-muted small text-uppercase fw-semibold">
                    <th class="ps-3 py-2" style="width:22%">Field</th>
                    <th class="py-2" style="width:39%">Sebelum</th>
                    <th class="pe-3 py-2" style="width:39%">Sesudah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($diffs as $d)
                    <tr class="diff-row {{ $d['changed'] || $d['added'] || $d['removed'] ? 'row-highlight' : '' }}">
                        <td class="ps-3 align-top">
                            <span class="field-label">{{ htmlspecialchars($d['key']) }}</span>
                            @if($d['added'])
                                <span class="badge bg-success-subtle text-success fs-7 ms-1">baru</span>
                            @endif
                            @if($d['removed'])
                                <span class="badge bg-danger-subtle text-danger fs-7 ms-1">hapus</span>
                            @endif
                            @if($d['changed'] && !$d['added'] && !$d['removed'])
                                <span class="badge bg-warning-subtle text-warning fs-7 ms-1">ubah</span>
                            @endif
                        </td>
                        <td class="align-top">
                            <code class="diff-before d-block small">{{ $d['before'] }}</code>
                        </td>
                        <td class="pe-3 align-top">
                            @if($d['removed'])
                                <code class="diff-after-removed d-block small"><s>{{ $d['after'] }}</s></code>
                            @elseif($d['changed'])
                                <code class="diff-after d-block small">{{ $d['after'] }}</code>
                            @elseif($d['added'])
                                <code class="diff-after-added d-block small">{{ $d['after'] }}</code>
                            @else
                                <code class="diff-after-same d-block small">{{ $d['after'] }}</code>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<style>
.diff-table th {
    border-bottom: 2px solid var(--bs-border-color);
}

.diff-row:hover td {
    background-color: var(--bs-gray-100);
}

[data-bs-theme="dark"] .diff-row:hover td {
    background-color: var(--bs-gray-800);
}

.field-label {
    font-weight: 600;
    color: var(--bs-primary);
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.8rem;
}

.diff-before,
.diff-after,
.diff-after-same {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.8rem;
    line-height: 1.5;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    white-space: pre-wrap;
    word-break: break-all;
}

.diff-before {
    background-color: var(--bs-gray-100);
    color: var(--bs-secondary);
}

.diff-after {
    background-color: var(--bs-success-subtle);
    color: var(--bs-success-emphasis);
    font-weight: 600;
}

.diff-after-removed {
    background-color: var(--bs-danger-subtle);
    color: var(--bs-danger-emphasis);
    text-decoration: line-through;
}

.diff-after-added {
    background-color: var(--bs-success-subtle);
    color: var(--bs-success-emphasis);
    font-weight: 600;
}

.diff-after-same {
    color: var(--bs-secondary);
    opacity: 0.6;
}

.row-highlight {
    background-color: rgba(var(--bs-success-rgb), 0.05);
}

[data-bs-theme="dark"] .diff-before {
    background-color: var(--bs-gray-800);
    color: var(--bs-gray-500);
}

[data-bs-theme="dark"] .diff-after {
    background-color: rgba(var(--bs-success-rgb), 0.15);
    color: var(--bs-success);
}

[data-bs-theme="dark"] .diff-after-removed {
    background-color: rgba(var(--bs-danger-rgb), 0.15);
    color: var(--bs-danger);
}

[data-bs-theme="dark"] .diff-after-added {
    background-color: rgba(var(--bs-success-rgb), 0.15);
    color: var(--bs-success);
}
</style>
