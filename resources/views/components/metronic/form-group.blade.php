@props(['name', 'label', 'required' => false, 'help' => null, 'tooltip' => null])
<div {{ $attributes->class(['fv-row mb-7']) }}>
    <label for="{{ $name }}" class="form-label fw-semibold {{ $required ? 'required' : '' }}">
        {{ $label }}
        @if ($tooltip)
            <i class="ki-outline ki-information-5 text-gray-500 fs-7 ms-1 align-middle cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $tooltip }}"></i>
        @endif
    </label>
    {{ $slot }}
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
