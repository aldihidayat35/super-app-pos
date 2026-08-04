@props([
    'title' => '',
    'value' => '0',
    'icon' => 'ki-outline ki-information-2',
    'color' => 'primary',
    'description' => null,
    'tooltip' => null,
    'href' => null,
    'trend' => null,
])

@php
    $palette = [
        'success' => ['bg' => 'bg-light-success', 'text' => 'text-success', 'border' => 'border-success'],
        'warning' => ['bg' => 'bg-light-warning', 'text' => 'text-warning', 'border' => 'border-warning'],
        'danger' => ['bg' => 'bg-light-danger', 'text' => 'text-danger', 'border' => 'border-danger'],
        'info' => ['bg' => 'bg-light-info', 'text' => 'text-info', 'border' => 'border-info'],
        'primary' => ['bg' => 'bg-light-primary', 'text' => 'text-primary', 'border' => 'border-primary'],
    ];
    $c = $palette[$color] ?? $palette['primary'];
@endphp

<a
    @if($href) href="{{ $href }}" @else href="#" @endif
    {{ $attributes->class(['card', 'kpi-card', 'hover-elevate-up', 'border', 'border-transparent', 'border-hover-' . $color])->merge(['class' => 'shadow-none']) }}
    style="text-decoration: none;"
>
    <div class="card-body p-5">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <span class="symbol symbol-45px {{ $c['bg'] }}">
                <i class="{{ $icon }} fs-2 {{ $c['text'] }}"></i>
            </span>
            @if($trend !== null)
                <span class="badge badge-sm {{ $trend['direction'] === 'up' ? 'badge-light-success' : ($trend['direction'] === 'down' ? 'badge-light-danger' : 'badge-light-secondary') }} fw-bold">
                    <i class="ki-outline {{ $trend['direction'] === 'up' ? 'ki-arrow-up' : ($trend['direction'] === 'down' ? 'ki-arrow-down' : 'ki-minus') }} fs-7"></i>
                    {{ $trend['value'] }}
                </span>
            @endif
        </div>
        <div class="fs-2 fw-bold text-gray-900 mb-1">{{ $value }}</div>
        <div class="fw-semibold text-gray-700 fs-6">{{ $title }}</div>
        @if($description)
            <div class="text-muted fs-7 mt-1">{{ $description }}</div>
        @endif
    </div>
    @if($tooltip)
        <span class="position-absolute top-0 end-0 mt-3 me-3" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $tooltip }}">
            <i class="ki-outline ki-information-2 fs-6 text-muted"></i>
        </span>
    @endif
</a>
