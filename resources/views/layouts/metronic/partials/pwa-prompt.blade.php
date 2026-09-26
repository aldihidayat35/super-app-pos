@auth
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="pwaInstallBottomSheet" aria-labelledby="pwaInstallBottomSheetLabel" style="height: auto; border-top-left-radius: 1.5rem; border-top-right-radius: 1.5rem;">
    <div class="offcanvas-header pb-2">
        <h5 class="offcanvas-title" id="pwaInstallBottomSheetLabel">
            <i class="ki-duotone ki-devices fs-1 text-primary me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
            Install Aplikasi
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body small pt-0 pb-5">
        
        <!-- Android / Desktop Prompt -->
        <div id="pwa-install-android" class="d-none">
            <p class="mb-4 text-muted">Install aplikasi GudangToko di perangkat Anda untuk akses lebih cepat, fitur layar penuh, dan pengalaman yang lebih baik layaknya aplikasi native.</p>
            <div class="d-flex flex-column gap-2">
                <button id="btn-install-pwa" class="btn btn-primary w-100">
                    <i class="ki-duotone ki-down-square fs-2"><span class="path1"></span><span class="path2"></span></i>
                    Install Sekarang
                </button>
                <button type="button" class="btn btn-light w-100" data-bs-dismiss="offcanvas" id="btn-dismiss-pwa">Lain Kali</button>
            </div>
        </div>

        <!-- iOS Prompt -->
        <div id="pwa-install-ios" class="d-none">
            <p class="mb-4 text-muted">Untuk menginstal GudangToko di iOS Anda agar tampil *full-screen*:</p>
            <div class="d-flex flex-column gap-3 mb-4">
                <div class="d-flex align-items-center">
                    <span class="badge badge-light-primary fw-bold me-3">1</span>
                    <span>Tekan tombol <strong>Share</strong> (Bagikan) <span class="fs-4 ms-1">⎙</span> di menu bawah browser Safari.</span>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge badge-light-primary fw-bold me-3">2</span>
                    <span>Gulir ke bawah dan pilih <strong>"Add to Home Screen"</strong> (Tambahkan ke Layar Utama).</span>
                </div>
            </div>
            <button type="button" class="btn btn-light w-100" data-bs-dismiss="offcanvas" id="btn-dismiss-pwa-ios">Mengerti</button>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let deferredPrompt;
    const pwaOffcanvasElement = document.getElementById('pwaInstallBottomSheet');
    if (!pwaOffcanvasElement) return;

    // Use Bootstrap offcanvas API
    let pwaOffcanvas;
    if (typeof bootstrap !== 'undefined') {
        pwaOffcanvas = new bootstrap.Offcanvas(pwaOffcanvasElement);
    }

    const hasDismissed = localStorage.getItem('pwa_install_dismissed');
    const hasShownThisSession = sessionStorage.getItem('pwa_install_shown');

    // 1. Desktop & Android Chrome (beforeinstallprompt)
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        
        if (!hasDismissed && !hasShownThisSession && pwaOffcanvas) {
            const androidDiv = document.getElementById('pwa-install-android');
            if(androidDiv) androidDiv.classList.remove('d-none');
            
            sessionStorage.setItem('pwa_install_shown', 'true');
            // Show bottom sheet after a short delay so it doesn't block immediate UI
            setTimeout(() => {
                pwaOffcanvas.show();
            }, 3000);
        }
    });

    const btnInstall = document.getElementById('btn-install-pwa');
    if (btnInstall) {
        btnInstall.addEventListener('click', async () => {
            if (pwaOffcanvas) pwaOffcanvas.hide();
            localStorage.setItem('pwa_install_dismissed', 'true'); // don't show again if they click install
            
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to the install prompt: ${outcome}`);
                deferredPrompt = null;
            }
        });
    }

    // 2. iOS Safari detection
    const isIos = () => {
        const userAgent = window.navigator.userAgent.toLowerCase();
        return /iphone|ipad|ipod/.test(userAgent) || (userAgent.includes("mac") && "ontouchend" in document);
    };

    const isIosPwaInstallable = () => {
        // If it's iOS and NOT already in standalone mode
        return isIos() && !window.navigator.standalone;
    };

    if (isIosPwaInstallable()) {
        if (!hasDismissed && !hasShownThisSession && pwaOffcanvas) {
            const iosDiv = document.getElementById('pwa-install-ios');
            if(iosDiv) iosDiv.classList.remove('d-none');
            
            sessionStorage.setItem('pwa_install_shown', 'true');
            setTimeout(() => {
                pwaOffcanvas.show();
            }, 3000);
        }
    }

    // Handle dismiss buttons
    const btnDismiss = document.getElementById('btn-dismiss-pwa');
    if (btnDismiss) {
        btnDismiss.addEventListener('click', () => {
            localStorage.setItem('pwa_install_dismissed', 'true');
        });
    }
    const btnDismissIos = document.getElementById('btn-dismiss-pwa-ios');
    if (btnDismissIos) {
        btnDismissIos.addEventListener('click', () => {
            localStorage.setItem('pwa_install_dismissed', 'true');
        });
    }
});

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then(registration => {
            console.log('SW registered: ', registration);
        }).catch(registrationError => {
            console.log('SW registration failed: ', registrationError);
        });
    });
}
</script>
@endauth
