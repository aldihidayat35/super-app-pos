@props(['url', 'title' => 'Preview', 'size' => '35px', 'classes' => 'object-fit-cover rounded', 'style' => ''])

@php
    $fallbackUrl = '/assets/media/products/default-product.png';
    $resolvedUrl = !empty($url) ? $url : $fallbackUrl;
@endphp

<img 
    src="{{ $resolvedUrl }}" 
    alt="{{ $title }}" 
    class="{{ $classes }} cursor-pointer" 
    style="@if($size) width: {{ $size }}; height: {{ $size }}; @endif {{ $style }}"
    data-bs-toggle="modal" 
    data-bs-target="#globalImagePreviewModal" 
    onclick="const targetImg=document.getElementById('globalPreviewImage'); if(targetImg){ targetImg.src=this.src; } const targetTitle=document.getElementById('globalPreviewTitle'); if(targetTitle){ targetTitle.innerText=this.alt; }" 
    onerror="this.onerror=null; this.src='{{ $fallbackUrl }}';"
    loading="lazy"
    title="Klik untuk memperbesar">
