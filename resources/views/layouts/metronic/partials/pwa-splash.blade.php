{{-- PWA Animated Opening Splash Screen KPM --}}
<style>
#pwa-splash-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    height: 100dvh;
    background-color: #ffffff;
    z-index: 2147483647; /* Highest possible layer */
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    opacity: 1;
    visibility: visible;
    transition: opacity 0.55s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.55s ease;
}

#pwa-splash-overlay.pwa-splash-hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.pwa-splash-container {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

#pwa-splash-video {
    width: 100%;
    height: 100%;
    max-width: 100vw;
    max-height: 100vh;
    max-height: 100dvh;
    object-fit: contain;
    background-color: #ffffff;
}

.pwa-splash-actions {
    position: absolute;
    top: env(safe-area-inset-top, 20px);
    right: env(safe-area-inset-right, 20px);
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 16px;
    margin-right: 16px;
}

.pwa-splash-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 999px;
    background: rgba(16, 42, 75, 0.08);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #102a4b;
    border: 1px solid rgba(16, 42, 75, 0.15);
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
    line-height: 1;
}

.pwa-splash-btn:hover {
    background: rgba(16, 42, 75, 0.18);
    border-color: rgba(16, 42, 75, 0.3);
    color: #0b1f38;
}

.pwa-splash-sound-btn {
    width: 36px;
    height: 36px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(16, 42, 75, 0.08);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #102a4b;
    border: 1px solid rgba(16, 42, 75, 0.15);
    cursor: pointer;
    transition: all 0.2s ease;
}

.pwa-splash-sound-btn:hover {
    background: rgba(16, 42, 75, 0.18);
}

.pwa-splash-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 3.5px;
    background: rgba(16, 42, 75, 0.08);
    overflow: hidden;
    z-index: 10;
}

.pwa-splash-progress-bar {
    width: 0%;
    height: 100%;
    background: linear-gradient(90deg, #1B84FF, #0056b3);
    transition: width 0.1s linear;
}
</style>

<div id="pwa-splash-overlay" aria-hidden="true">
    <div class="pwa-splash-container">
        {{-- Tombol Aksi: Suara & Lewati --}}
        <div class="pwa-splash-actions">
            <button type="button" id="pwa-splash-sound-btn" class="pwa-splash-sound-btn" title="Nyalakan / Matikan Suara" aria-label="Pengaturan suara">
                <i class="ki-outline ki-sound-off fs-5" id="pwa-splash-sound-icon"></i>
            </button>
            <button type="button" id="pwa-splash-skip-btn" class="pwa-splash-btn">
                <span>Lewati</span>
                <i class="ki-outline ki-arrow-right fs-6"></i>
            </button>
        </div>

        {{-- Video Opening Animasi KPM --}}
        <video id="pwa-splash-video"
               playsinline
               muted
               autoplay
               preload="auto"
               poster="{{ asset('assets/media/pwa/splash-poster.jpg') }}">
            <source src="{{ asset('assets/media/pwa/splash-intro.mp4') }}" type="video/mp4">
        </video>

        {{-- Progress Bar Durasi --}}
        <div class="pwa-splash-progress">
            <div id="pwa-splash-progress-bar" class="pwa-splash-progress-bar"></div>
        </div>
    </div>
</div>

<script>
(function() {
    // 1. Cek apakah pengguna sudah melihat splash screen pada sesi ini
    const SPLASH_SESSION_KEY = 'kpm_pwa_splash_seen_v1';
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const overlay = document.getElementById('pwa-splash-overlay');
    const video = document.getElementById('pwa-splash-video');
    const skipBtn = document.getElementById('pwa-splash-skip-btn');
    const soundBtn = document.getElementById('pwa-splash-sound-btn');
    const soundIcon = document.getElementById('pwa-splash-sound-icon');
    const progressBar = document.getElementById('pwa-splash-progress-bar');

    if (!overlay || !video) return;

    // Jika sudah pernah diputar pada sesi ini, langsung sembunyikan tanpa delay
    if (sessionStorage.getItem(SPLASH_SESSION_KEY) === 'true') {
        overlay.style.display = 'none';
        return;
    }

    let isDismissed = false;

    function dismissSplash() {
        if (isDismissed) return;
        isDismissed = true;

        try {
            sessionStorage.setItem(SPLASH_SESSION_KEY, 'true');
        } catch(e) {}

        overlay.classList.add('pwa-splash-hidden');

        setTimeout(function() {
            try {
                video.pause();
                video.removeAttribute('src');
                video.load();
            } catch(e) {}
            if (overlay && overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 580);
    }

    // Tombol Lewati
    if (skipBtn) {
        skipBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dismissSplash();
        });
    }

    // Klik di sembarang area layar splash untuk interaksi suara atau lewati jika video hampir selesai
    overlay.addEventListener('click', function(e) {
        if (e.target.closest('#pwa-splash-sound-btn')) return;
        if (e.target.closest('#pwa-splash-skip-btn')) return;
        // Jika belum ada suara, un-mute saat user tap layar
        if (video.muted) {
            toggleSound();
        }
    });

    // Toggle Suara
    function toggleSound() {
        if (!video) return;
        if (video.muted) {
            video.muted = false;
            if (soundIcon) {
                soundIcon.className = 'ki-outline ki-sound fs-5 text-primary';
            }
        } else {
            video.muted = true;
            if (soundIcon) {
                soundIcon.className = 'ki-outline ki-sound-off fs-5';
            }
        }
    }

    if (soundBtn) {
        soundBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSound();
        });
    }

    // Update Progress Bar
    video.addEventListener('timeupdate', function() {
        if (video.duration && progressBar) {
            const pct = (video.currentTime / video.duration) * 100;
            progressBar.style.width = pct + '%';
        }
    });

    // Saat video selesai diputar -> otomatis masuk ke aplikasi
    video.addEventListener('ended', function() {
        dismissSplash();
    });

    // Jika terjadi error saat memuat video -> langsung masuk agar tidak memblokir user
    video.addEventListener('error', function() {
        dismissSplash();
    });

    // Jalankan video
    const playPromise = video.play();
    if (playPromise !== undefined) {
        playPromise.catch(function(err) {
            console.warn('[PWA Splash] Autoplay butuh interaksi atau dicegah:', err);
            // Tetap berikan batas waktu agar layar tidak terjebak
            setTimeout(dismissSplash, 2500);
        });
    }

    // Batas waktu keamanan maksimal (Safety timeout 9.5 detik)
    setTimeout(function() {
        if (!isDismissed) {
            dismissSplash();
        }
    }, 9500);

    // Ekspos fungsi global untuk memutar ulang splash jika diperlukan (misal untuk demonstrasi/preview)
    window.previewPwaSplash = function() {
        sessionStorage.removeItem(SPLASH_SESSION_KEY);
        window.location.reload();
    };
})();
</script>
