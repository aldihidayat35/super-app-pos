@props(['title', 'description' => null])
<div {{ $attributes->class(['mb-7']) }}>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="fs-2x fw-bold text-gray-900 mb-2">{{ $title }}</h2>
            @if ($description)<p class="text-muted fs-6 mb-0">{{ $description }}</p>@endif
        </div>
        @isset($actions)
            <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
