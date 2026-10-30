/**
 * GudangToko — SPA Smooth Page Router
 * Transisi perpindahan halaman instan tanpa refresh putih satu layar penuh.
 * Memperbarui area toolbar dan konten utama, menyinkronkan status menu sidebar,
 * serta memberikan animasi slide-in dari kanan ke kiri dengan progress bar tipis.
 */

let activeAbortController = null;
let progressBarEl = null;
let progressTimer = null;

// Pastikan script yang mendaftarkan DOMContentLoaded tetap berjalan saat halaman ditukar dinamis
const setupDomContentLoadedShim = () => {
    if (window._spaDomContentLoadedShimInstalled) return;
    window._spaDomContentLoadedShimInstalled = true;

    const originalAddEventListener = document.addEventListener;
    document.addEventListener = function (type, listener, options) {
        if (type === 'DOMContentLoaded' && document.readyState !== 'loading') {
            try {
                listener.call(document, new Event('DOMContentLoaded'));
            } catch (error) {
                console.error('SPA: Kesalahan mengeksekusi DOMContentLoaded listener', error);
            }
            return;
        }
        return originalAddEventListener.call(document, type, listener, options);
    };
};

// Buat & kelola progress bar tipis di bagian atas layar
const getProgressBar = () => {
    if (!progressBarEl) {
        progressBarEl = document.querySelector('.spa-progress-bar');
        if (!progressBarEl) {
            progressBarEl = document.createElement('div');
            progressBarEl.className = 'spa-progress-bar';
            document.body.appendChild(progressBarEl);
        }
    }
    return progressBarEl;
};

const startProgressBar = () => {
    const bar = getProgressBar();
    if (progressTimer) clearInterval(progressTimer);

    bar.style.transition = 'width 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease';
    bar.style.opacity = '1';
    bar.style.width = '25%';

    let current = 25;
    progressTimer = setInterval(() => {
        if (current < 75) {
            current += 10;
            bar.style.width = `${current}%`;
        } else if (current < 90) {
            current += 2;
            bar.style.width = `${current}%`;
        }
    }, 200);
};

const finishProgressBar = () => {
    const bar = getProgressBar();
    if (progressTimer) clearInterval(progressTimer);

    bar.style.width = '100%';
    setTimeout(() => {
        bar.style.opacity = '0';
        setTimeout(() => {
            bar.style.width = '0%';
        }, 200);
    }, 150);
};

// Eksekusi script baru yang terdapat di dalam konten halaman baru
const executeInjectedScripts = (sourceDoc) => {
    const mainContainer = sourceDoc.getElementById('kt_app_main_content');
    if (!mainContainer) return;

    // Kumpulkan script di dalam main container & stack scripts di body
    const scripts = Array.from(mainContainer.querySelectorAll('script')).concat(
        Array.from(sourceDoc.body.querySelectorAll('script')).filter((s) => {
            const src = s.getAttribute('src') || '';
            // Lewati bundler inti yang sudah dimuat
            return !src.includes('vendor.js') && !src.includes('app.js') && !src.includes('scripts.bundle.js');
        })
    );

    scripts.forEach((oldScript) => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach((attr) => {
            newScript.setAttribute(attr.name, attr.value);
        });
        newScript.textContent = oldScript.textContent;
        document.body.appendChild(newScript);
        // Hapus elemen script setelah eksekusi agar DOM tetap bersih
        setTimeout(() => newScript.remove(), 50);
    });
};

export const navigateTo = async (url, pushState = true) => {
    const targetUrl = new URL(url, window.location.origin);
    const mainContent = document.getElementById('kt_app_main_content');

    if (!mainContent) {
        window.location.assign(targetUrl.href);
        return;
    }

    if (activeAbortController) {
        activeAbortController.abort();
    }
    activeAbortController = new AbortController();

    // Efek redup halus pada konten lama dan jalankan progress bar
    mainContent.classList.add('spa-loading');
    startProgressBar();

    try {
        const response = await fetch(targetUrl.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-SPA-Router': 'true',
            },
            signal: activeAbortController.signal,
        });

        // Jika dialihkan (misal session habis ke /login) atau bukan status sukses
        if (!response.ok || response.redirected) {
            window.location.assign(response.url || targetUrl.href);
            return;
        }

        const html = await response.text();
        const parser = new DOMParser();
        const newDoc = parser.parseFromString(html, 'text/html');

        const newMainContent = newDoc.getElementById('kt_app_main_content');
        if (!newMainContent) {
            window.location.assign(targetUrl.href);
            return;
        }

        // 1. Perbarui Judul Halaman & Meta CSRF
        if (newDoc.title) {
            document.title = newDoc.title;
        }
        const newCsrf = newDoc.querySelector('meta[name="csrf-token"]')?.content;
        if (newCsrf) {
            const existingMeta = document.querySelector('meta[name="csrf-token"]');
            if (existingMeta) existingMeta.content = newCsrf;
            if (window.axios) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newCsrf;
        }

        // 2. Perbarui History Browser
        if (pushState) {
            window.history.pushState({ spa: true, url: targetUrl.href }, '', targetUrl.href);
        }

        // 3. Ganti Isi Konten Utama (Toolbar + Content Container)
        mainContent.innerHTML = newMainContent.innerHTML;
        mainContent.classList.remove('spa-loading');

        // 4. Pasang Animasi Masuk dari Kanan ke Kiri
        mainContent.classList.remove('spa-slide-enter');
        // Force reflow agar animasi selalu re-trigger
        void mainContent.offsetWidth;
        mainContent.classList.add('spa-slide-enter');

        // Hapus kelas animasi setelah durasi transisi selesai agar tidak mengganggu posisi modal/tooltip
        setTimeout(() => {
            mainContent.classList.remove('spa-slide-enter');
        }, 320);

        // 5. Sinkronkan Status Menu Sidebar (Active & Show states)
        const newMenu = newDoc.querySelector('#kt_app_sidebar .app-sidebar-wrapper .menu');
        const currentMenu = document.querySelector('#kt_app_sidebar .app-sidebar-wrapper .menu');
        if (newMenu && currentMenu) {
            currentMenu.innerHTML = newMenu.innerHTML;
            if (window.KTMenu && typeof window.KTMenu.createInstances === 'function') {
                window.KTMenu.createInstances();
            }
        }

        // 6. Tampilkan Flash Toast jika ada di halaman baru
        const newToast = newDoc.querySelector('.toast');
        if (newToast && window.bootstrap?.Toast) {
            const toastContainer = document.querySelector('.toast')?.closest('.position-fixed');
            if (toastContainer) {
                toastContainer.innerHTML = newToast.outerHTML;
                const toastEl = toastContainer.querySelector('.toast');
                if (toastEl) new window.bootstrap.Toast(toastEl).show();
            }
        }

        // 7. Scroll ke Atas Halaman
        window.scrollTo({ top: 0, behavior: 'instant' });

        // 8. Eksekusi Script Inline/Page-Specific yang Diinjeksikan
        executeInjectedScripts(newDoc);

        // 9. Inisialisasi Ulang Komponen UI (Select2, Flatpickr, DataTable, dll.)
        if (typeof window.initializeApplication === 'function') {
            window.initializeApplication();
        }

        finishProgressBar();
        window.dispatchEvent(new CustomEvent('app:page-loaded', { detail: { url: targetUrl.href } }));
    } catch (error) {
        if (error.name === 'AbortError') {
            // Permintaan dibatalkan karena pengguna mengklik link lain
            return;
        }
        console.warn('SPA Navigation gagal, beralih ke navigasi standar:', error);
        window.location.assign(targetUrl.href);
    } finally {
        mainContent.classList.remove('spa-loading');
    }
};

let isSpaRouterInitialized = false;

export const initializeSpaRouter = () => {
    if (isSpaRouterInitialized) return;
    isSpaRouterInitialized = true;

    setupDomContentLoadedShim();
    getProgressBar();

    // 1. Delegasi Klik Link Navigasi (GET)
    document.addEventListener('click', (event) => {
        // Abaikan klik kanan atau klik dengan tombol modifier (Ctrl/Cmd/Shift/Alt)
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        const link = event.target.closest('a');
        if (!link) return;

        // Abaikan link khusus
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download') || link.dataset.noSpa !== undefined) return;

        // Abaikan tombol logout atau link di dalam form logout
        if (link.closest('form[action*="logout"]') || link.classList.contains('sidebar-logout-btn')) {
            return;
        }

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

        let targetUrl;
        try {
            targetUrl = new URL(link.href, window.location.origin);
        } catch {
            return;
        }

        // Hanya tangani link internal domain yang sama
        if (targetUrl.origin !== window.location.origin) return;

        // Abaikan file asset/download (.pdf, .xlsx, .csv, gambar, zip)
        if (/\.(pdf|xlsx|xls|csv|zip|png|jpe?g|svg|webp)$/i.test(targetUrl.pathname)) {
            return;
        }

        // Jika mengarah ke halaman yang sama persis
        if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) {
            if (targetUrl.hash) return; // biarkan anchor lokal scroll
            event.preventDefault();
            return;
        }

        event.preventDefault();
        navigateTo(targetUrl.href, true);
    });

    // 2. Delegasi Form Pencarian/Filter (GET)
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form || form.tagName !== 'FORM') return;

        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method !== 'GET') return; // POST/PUT/DELETE tetap submit normal

        if (form.target && form.target !== '_self') return;
        if (form.dataset.noSpa !== undefined) return;

        event.preventDefault();
        const action = form.getAttribute('action') || window.location.href;
        const targetUrl = new URL(action, window.location.origin);

        const formData = new FormData(form);
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (value !== '') {
                params.append(key, value);
            }
        }

        targetUrl.search = params.toString();
        navigateTo(targetUrl.href, true);
    });

    // 3. Tangani Navigasi Tombol Back / Forward Browser
    window.addEventListener('popstate', () => {
        navigateTo(window.location.href, false);
    });
};
