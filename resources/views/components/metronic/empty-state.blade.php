@props(['title' => 'Belum ada data', 'description' => 'Data akan tampil di sini setelah tersedia.', 'icon' => 'ki-outline ki-folder'])
<div {{ $attributes->class(['d-flex flex-column align-items-center justify-content-center text-center py-15 w-100']) }}>
    <div class="d-flex align-items-center justify-content-center mb-4">
        <i class="{{ $icon }} fs-3x text-muted"></i>
    </div>
    <h3 class="text-gray-900 fw-bold mb-2">{{ $title }}</h3>
    <p class="text-muted mb-5">{{ $description }}</p>
    {{ $slot }}
</div>
