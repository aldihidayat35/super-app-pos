@props(['title' => 'Belum ada data', 'description' => 'Data akan tampil di sini setelah tersedia.', 'icon' => 'ki-outline ki-folder'])
<div {{ $attributes->class(['text-center py-15']) }}><i class="{{ $icon }} fs-3x text-muted"></i><h3 class="text-gray-900 fw-bold mt-5 mb-2">{{ $title }}</h3><p class="text-muted mb-5">{{ $description }}</p>{{ $slot }}</div>
