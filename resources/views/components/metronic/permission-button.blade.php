@props(['permission' => null, 'href' => null, 'type' => 'button', 'variant' => 'primary', 'icon' => null])
@if (!$permission || auth()->user()?->can($permission))
    @if ($href)<a href="{{ $href }}" {{ $attributes->class(["btn btn-{$variant}"]) }}>@if($icon)<i class="{{ $icon }}"></i>@endif{{ $slot }}</a>
    @else<button type="{{ $type }}" {{ $attributes->class(["btn btn-{$variant}"]) }}>@if($icon)<i class="{{ $icon }}"></i>@endif{{ $slot }}</button>@endif
@endif
