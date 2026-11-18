{{-- PWA Service Worker Registration & Update Notification --}}
<div id="pwa-update-banner" class="position-fixed bottom-0 end-0 p-4" style="z-index: 9999; display: none;">
    <div class="card shadow-lg border border-primary bg-body" style="max-width: 360px;">
        <div class="card-body p-4 d-flex align-items-center gap-3">
            <div class="symbol symbol-40px symbol-light-primary flex-shrink-0">
                <span class="symbol-label">
                    <i class="ki-outline ki-arrows-circle fs-2 text-primary"></i>
                </span>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="fw-bold fs-7 text-gray-900">Pembaruan Tersedia</div>
                <div class="text-muted fs-8 text-truncate">Versi terbaru GudangToko siap dimuat.</div>
            </div>
            <button type="button" id="btn-reload-pwa" class="btn btn-sm btn-primary fw-semibold px-3 py-2 flex-shrink-0">
                Perbarui
            </button>
        </div>
    </div>
</div>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then((registration) => {
                    // 1. Cek update berkala
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        if (!newWorker) return;

                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // Tampilkan notifikasi pembaruan jika controller lama masih aktif
                                showPwaUpdateBanner(newWorker);
                            }
                        });
                    });

                    // 2. Jika sudah ada worker yang waiting saat halaman dimuat
                    if (registration.waiting && navigator.serviceWorker.controller) {
                        showPwaUpdateBanner(registration.waiting);
                    }
                })
                .catch((error) => {
                    console.warn('[PWA] Service Worker gagal didaftarkan:', error);
                });

            // Reload otomatis saat Service Worker baru mengambil alih kontrol
            let refreshing = false;
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                if (!refreshing) {
                    refreshing = true;
                    window.location.reload();
                }
            });
        });

        function showPwaUpdateBanner(waitingWorker) {
            const banner = document.getElementById('pwa-update-banner');
            const btnReload = document.getElementById('btn-reload-pwa');
            if (banner && btnReload) {
                banner.style.display = 'block';
                btnReload.onclick = () => {
                    btnReload.disabled = true;
                    btnReload.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';
                    waitingWorker.postMessage({ type: 'SKIP_WAITING' });
                };
            }
        }
    }
</script>
