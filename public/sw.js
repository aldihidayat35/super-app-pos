/**
 * GudangToko Progressive Web App Service Worker
 * Version: 1.0.0
 * Strategy: Cache-First for static assets, Network-First for HTML navigation with /offline fallback.
 */

const CACHE_VERSION = 'v1.0.0';
const STATIC_CACHE = `gudangtoko-static-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline';

// Aset statis esensial yang di-precache saat instalasi
const PRECACHE_ASSETS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/assets/media/pwa/icon-192x192.png',
    '/assets/media/pwa/icon-512x512.png',
    '/assets/media/pwa/apple-touch-icon.png',
    '/assets/css/ki-icons-fallback.css'
];

// 1. Install Event: Pre-cache aset esensial
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[PWA SW] Pre-caching partial failure:', err);
            });
        })
    );
});

// 2. Activate Event: Bersihkan cache versi usang
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key.startsWith('gudangtoko-') && key !== STATIC_CACHE) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// 3. Message Event: Izinkan antarmuka mengirim pesan skipWaiting saat ada rilis baru
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// 4. Fetch Event: Routing request cerdas
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Abaikan permintaan selain GET (POST, PUT, DELETE selalu Network-Only demi keamanan data mutasi)
    if (request.method !== 'GET') {
        return;
    }

    // Abaikan protokol non-http/https (chrome-extension, blob, dll)
    if (!url.protocol.startsWith('http')) {
        return;
    }

    // Rute dinamis sensitif yang tidak boleh di-cache (Network-Only)
    const isSensitiveRoute = 
        url.pathname.startsWith('/login') ||
        url.pathname.startsWith('/logout') ||
        url.pathname.includes('/print') ||
        url.pathname.includes('/export') ||
        url.pathname.includes('/download') ||
        url.pathname.startsWith('/sanctum');

    if (isSensitiveRoute) {
        return;
    }

    // A. Permintaan Navigasi Halaman HTML (Dokumen)
    if (request.mode === 'navigate' || (request.headers.get('accept') && request.headers.get('accept').includes('text/html'))) {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    return networkResponse;
                })
                .catch(() => {
                    // Jika jaringan terputus, sajikan halaman offline fallback
                    return caches.match(OFFLINE_URL);
                })
        );
        return;
    }

    // B. Aset Statis (CSS, JS, Fonts, Images, Icons) -> Cache-First dengan background refresh
    const isStaticAsset = 
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/assets/') ||
        url.hostname.includes('fonts.googleapis.com') ||
        url.hostname.includes('fonts.gstatic.com') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.woff2') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.ico');

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    // Update cache di latar belakang (Stale-While-Revalidate)
                    fetch(request).then((freshResponse) => {
                        if (freshResponse && freshResponse.status === 200) {
                            const responseClone = freshResponse.clone();
                            caches.open(STATIC_CACHE).then((cache) => cache.put(request, responseClone));
                        }
                    }).catch(() => {/* Offline - abaikan kegagalan refresh latar */});

                    return cachedResponse;
                }

                // Jika belum ada di cache, ambil dari network dan simpan
                return fetch(request).then((freshResponse) => {
                    if (!freshResponse || freshResponse.status !== 200 || freshResponse.type === 'opaque') {
                        return freshResponse;
                    }
                    const responseClone = freshResponse.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(request, responseClone));
                    return freshResponse;
                });
            })
        );
    }
});
