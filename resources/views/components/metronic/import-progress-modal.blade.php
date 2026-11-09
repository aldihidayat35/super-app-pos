@props([
    'id' => 'import-progress-modal',
    'title' => 'Sedang Memproses Data...',
    'subtitle' => 'Mohon menunggu, sistem sedang membaca dan memproses berkas spreadsheet Anda.',
    'icon' => 'ki-file-sheet',
])

<div class="modal fade" id="{{ $id }}" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="z-index: 1090;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="modal-body p-8 text-center">
                {{-- Animated Icon Container --}}
                <div class="d-inline-flex align-items-center justify-content-center bg-light-primary text-primary rounded-circle mb-5 p-4 position-relative" style="width: 84px; height: 84px;">
                    <i class="ki-outline {{ $icon }} fs-2tx text-primary" id="{{ $id }}-icon"></i>
                    <div class="spinner-border spinner-border-sm text-primary position-absolute bottom-0 end-0 m-1" role="status"></div>
                </div>

                {{-- Title & Subtitle --}}
                <h4 class="fw-bolder text-gray-900 mb-2 fs-4" id="{{ $id }}-title">{{ $title }}</h4>
                <p class="text-gray-600 fs-7 mb-6" id="{{ $id }}-subtitle">{{ $subtitle }}</p>

                {{-- Progress Bar Container --}}
                <div class="position-relative mb-3">
                    <div class="progress h-20px w-100 bg-light-secondary rounded-pill overflow-hidden shadow-xs border border-gray-200">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                             role="progressbar" 
                             id="{{ $id }}-bar"
                             style="width: 8%; transition: width 0.35s ease;" 
                             aria-valuenow="8" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                        </div>
                    </div>
                </div>

                {{-- Status Text & Percentage Indicator --}}
                <div class="d-flex justify-content-between align-items-center mb-6 px-1">
                    <span class="fs-7 fw-semibold text-gray-700 text-truncate pe-2 text-start" id="{{ $id }}-stage-text">
                        <span class="spinner-grow spinner-grow-sm text-primary me-2 align-middle"></span>
                        <span id="{{ $id }}-stage-label">Mengunggah dan membaca berkas...</span>
                    </span>
                    <span class="fs-6 fw-bolder text-primary font-monospace flex-shrink-0" id="{{ $id }}-percent-label">8%</span>
                </div>

                {{-- Reassuring Warning Alert --}}
                <div class="alert alert-warning border border-warning border-opacity-50 bg-light-warning d-flex align-items-center p-4 rounded-3 text-start mb-0">
                    <i class="ki-outline ki-information-2 fs-2hx text-warning me-3 flex-shrink-0"></i>
                    <div class="fs-8 text-gray-800 lh-sm">
                        Proses ini memerlukan waktu beberapa detik terutama jika berkas memiliki ratusan/ribuan baris data. 
                        <strong>Jangan menutup atau merefresh halaman ini</strong> sampai proses selesai.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
window.startImportProgress = function (options) {
    const modalId = options.modalId || 'import-progress-modal';
    const modalEl = document.getElementById(modalId);
    if (!modalEl) return null;

    const titleEl = document.getElementById(modalId + '-title');
    const subtitleEl = document.getElementById(modalId + '-subtitle');
    const barEl = document.getElementById(modalId + '-bar');
    const stageLabelEl = document.getElementById(modalId + '-stage-label');
    const percentLabelEl = document.getElementById(modalId + '-percent-label');
    const iconEl = document.getElementById(modalId + '-icon');

    if (options.title && titleEl) titleEl.textContent = options.title;
    if (options.subtitle && subtitleEl) subtitleEl.textContent = options.subtitle;
    if (options.icon && iconEl) {
        iconEl.className = 'ki-outline ' + options.icon + ' fs-2tx text-primary';
    }

    const stages = options.stages || [
        { percent: 15, text: 'Mengunggah dan membaca berkas spreadsheet...' },
        { percent: 35, text: 'Membaca baris data dan memeriksa kolom...' },
        { percent: 65, text: 'Memvalidasi data dan keunikan kode...' },
        { percent: 85, text: 'Menyiapkan hasil verifikasi...' },
        { percent: 95, text: 'Hampir selesai, memuat tampilan...' },
    ];

    let currentPercent = 5;
    let stageIndex = 0;

    function updateProgress(pct, text) {
        currentPercent = Math.min(98, Math.max(currentPercent, pct));
        if (barEl) {
            barEl.style.width = currentPercent + '%';
            barEl.setAttribute('aria-valuenow', currentPercent);
        }
        if (percentLabelEl) {
            percentLabelEl.textContent = Math.round(currentPercent) + '%';
        }
        if (text && stageLabelEl) {
            stageLabelEl.textContent = text;
        }
    }

    // Tampilkan modal
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl, {
            backdrop: 'static',
            keyboard: false
        });
        bsModal.show();
    } else {
        $(modalEl).modal({ backdrop: 'static', keyboard: false }).modal('show');
    }

    updateProgress(8, stages[0]?.text || 'Memulai proses...');

    // Animasi bertahap
    const intervalTime = options.interval || 750;
    const timer = setInterval(function () {
        if (stageIndex < stages.length) {
            const targetStage = stages[stageIndex];
            if (currentPercent < targetStage.percent) {
                const step = Math.max(1, Math.round((targetStage.percent - currentPercent) / 3));
                updateProgress(currentPercent + step, targetStage.text);
            } else {
                stageIndex++;
                if (stageIndex < stages.length) {
                    updateProgress(currentPercent + 2, stages[stageIndex].text);
                }
            }
        } else {
            if (currentPercent < 98) {
                updateProgress(currentPercent + 0.5, stages[stages.length - 1]?.text || 'Menyelesaikan proses...');
            }
        }
    }, intervalTime);

    window.addEventListener('beforeunload', function (e) {
        e.preventDefault();
        e.returnValue = 'Proses import sedang berjalan di server. Apakah Anda yakin ingin membatalkan?';
        return e.returnValue;
    });

    return {
        finish: function () {
            clearInterval(timer);
            updateProgress(100, 'Selesai!');
        }
    };
};
</script>
@endpush
@endonce

