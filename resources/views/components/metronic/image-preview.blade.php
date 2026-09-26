@props(['url', 'title' => 'Preview', 'size' => '35px', 'classes' => 'object-fit-cover rounded', 'style' => ''])

<img 
    src="{{ $url }}" 
    alt="{{ $title }}" 
    class="{{ $classes }} cursor-pointer" 
    style="@if($size) width: {{ $size }}; height: {{ $size }}; @endif {{ $style }}"
    data-bs-toggle="modal" 
    data-bs-target="#globalImagePreviewModal" 
    onclick="document.getElementById('globalPreviewImage').src=this.src; document.getElementById('globalPreviewTitle').innerText=this.alt;" 
    title="Klik untuk memperbesar">
