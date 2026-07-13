@props(['status', 'label' => null])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $text = $label ?? (method_exists($status, 'label') ? $status->label() : ucfirst(str_replace('_', ' ', $value)));
    $color = match ($value) { 'active', 'approved', 'completed', 'paid', 'ok' => 'success', 'draft', 'pending', 'submitted' => 'warning', 'rejected', 'cancelled', 'voided', 'error' => 'danger', default => 'secondary' };
@endphp
<span {{ $attributes->class(["badge badge-light-{$color}"]) }}>{{ $text }}</span>
