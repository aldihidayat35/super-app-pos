@props(['name', 'label', 'required' => false, 'help' => null])
<div {{ $attributes->class(['fv-row mb-7']) }}>
    <label for="{{ $name }}" class="form-label fw-semibold {{ $required ? 'required' : '' }}">{{ $label }}</label>
    {{ $slot }}
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
