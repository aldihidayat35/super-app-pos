@props([
    'title' => 'Dashboard',
    'description' => null,
])

<div {{ $attributes->class(['gt-page-header w-100']) }}>
    <div class="gt-page-header__main d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 gap-md-5">
        <div class="gt-page-header__text flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h1 class="gt-page-header__title page-heading text-gray-900 fw-bold fs-3 mb-0 text-break">
                    {{ $title }}
                </h1>
                @isset($help)
                    {{ $help }}
                @endisset
            </div>

            @if (filled(trim((string) $description)))
                <p class="gt-page-header__description text-muted fw-semibold fs-7 mb-0 mt-1">
                    {{ $description }}
                </p>
            @endif
        </div>

        @isset($actions)
            @if (filled(trim((string) $actions)))
                <div class="gt-page-header__actions d-flex flex-wrap align-items-center justify-content-md-end gap-2 flex-shrink-0">
                    {{ $actions }}
                </div>
            @endif
        @endisset
    </div>
</div>
