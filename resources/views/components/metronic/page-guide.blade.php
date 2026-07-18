@props(['id', 'title'])

@php
    $modalId = 'page-guide-' . \Illuminate\Support\Str::slug($id);
    $accordionId = $modalId . '-sections';
    $sections = [
        ['key' => 'function', 'title' => 'Fungsi Halaman', 'icon' => 'ki-information-5'],
        ['key' => 'workflow', 'title' => 'Cara Kerja', 'icon' => 'ki-route'],
        ['key' => 'parts', 'title' => 'Penjelasan Setiap Bagian', 'icon' => 'ki-element-11'],
        ['key' => 'impacts', 'title' => 'Dampak terhadap Data atau Halaman Lain', 'icon' => 'ki-arrows-circle'],
        ['key' => 'operation', 'title' => 'Cara Mengoperasikan', 'icon' => 'ki-check-square'],
        ['key' => 'warnings', 'title' => 'Perhatian dan Kesalahan yang Harus Dihindari', 'icon' => 'ki-shield-cross'],
        ['key' => 'example', 'title' => 'Contoh Penggunaan', 'icon' => 'ki-book-open'],
    ];
@endphp

<button type="button"
        class="btn btn-icon btn-sm btn-light-primary rounded-circle ms-2 page-guide-trigger"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalId }}"
        title="Lihat panduan penggunaan halaman"
        aria-label="Lihat panduan penggunaan halaman {{ $title }}"
        aria-haspopup="dialog">
    <i class="ki-outline ki-question-2 fs-6" aria-hidden="true"></i>
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="badge badge-light-primary mb-2">Panduan Halaman</span>
                    <h2 class="modal-title fw-bold" id="{{ $modalId }}-title">{{ $title }}</h2>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" aria-label="Tutup panduan">
                    <i class="ki-outline ki-cross fs-1" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body bg-light">
                <div class="accordion" id="{{ $accordionId }}">
                    @foreach ($sections as $index => $section)
                        @php($slot = ${$section['key']})
                        <div class="accordion-item border border-gray-300 mb-3 rounded overflow-hidden">
                            <h3 class="accordion-header" id="{{ $modalId }}-heading-{{ $section['key'] }}">
                                <button class="accordion-button fw-semibold {{ $index === 0 ? '' : 'collapsed' }}"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $modalId }}-section-{{ $section['key'] }}"
                                        aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                                        aria-controls="{{ $modalId }}-section-{{ $section['key'] }}">
                                    <i class="ki-outline {{ $section['icon'] }} fs-3 text-primary me-3" aria-hidden="true"></i>
                                    {{ $section['title'] }}
                                </button>
                            </h3>
                            <div id="{{ $modalId }}-section-{{ $section['key'] }}"
                                 class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                                 aria-labelledby="{{ $modalId }}-heading-{{ $section['key'] }}"
                                 data-bs-parent="#{{ $accordionId }}">
                                <div class="accordion-body text-gray-700 lh-lg page-guide-content">{{ $slot }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <span class="text-muted fs-7 me-auto">Panduan mengikuti fungsi yang tersedia pada halaman ini.</span>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Saya Mengerti</button>
            </div>
        </div>
    </div>
</div>
