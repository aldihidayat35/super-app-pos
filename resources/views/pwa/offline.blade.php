<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Koneksi Terputus - GudangToko</title>
    @include('layouts.metronic.partials.pwa-meta')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700">
    <link rel="stylesheet" href="{{ asset('assets/vendor/metronic/css/style.bundle.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/ki-icons-fallback.css') }}">
    <style>
        body {
            background-color: #f5f8fa;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 1.5rem;
        }
        [data-bs-theme="dark"] body {
            background-color: #151521;
            color: #cdcdde;
        }
        .offline-card {
            max-width: 480px;
            width: 100%;
            background: #ffffff;
            border-radius: 1.25rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            padding: 2.5rem 2rem;
            text-align: center;
        }
        [data-bs-theme="dark"] .offline-card {
            background: #1e1e2d;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        .offline-icon-wrap {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(241, 65, 108, 0.1);
            color: #f1416c;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.825rem;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            background: rgba(241, 65, 108, 0.15);
            color: #f1416c;
            margin-bottom: 1rem;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #f1416c;
            display: inline-block;
            animation: pulse-dot 1.8s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }
        .btn-retry {
            background: #1b84ff;
            color: #ffffff;
            border: none;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.65rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s;
        }
        .btn-retry:hover {
            background: #056ee9;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="offline-card">
        <div class="offline-icon-wrap">
            <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="1" y1="1" x2="23" y2="23"></line>
                <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"></path>
                <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"></path>
                <path d="M10.71 5.05A16 16 0 0 1 22.58 9"></path>
                <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"></path>
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                <line x1="12" y1="20" x2="12.01" y2="20"></line>
            </svg>
        </div>

        <div>
            <span class="status-pill" id="offlineStatus">
                <span class="status-dot"></span>
                Mode Offline
            </span>
        </div>

        <h3 class="fw-bold fs-2 mb-2 text-gray-900">Koneksi Terputus</h3>
        <p class="text-muted fs-6 mb-6">
            GudangToko tidak dapat menghubungi server. Periksa kembali sambungan Wi-Fi atau data seluler di perangkat Anda.
        </p>

        <div class="d-flex flex-column gap-3">
            <button type="button" class="btn btn-retry w-100 justify-content-center" onclick="tryReconnect()" id="btnRetry">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                </svg>
                Coba Sambungkan Ulang
            </button>
            <a href="/" class="btn btn-light w-100 fw-semibold">
                Kembali ke Beranda
            </a>
        </div>
    </div>

    <script>
        function tryReconnect() {
            const btn = document.getElementById('btnRetry');
            if (btn) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memeriksa...';
                btn.disabled = true;
            }
            fetch('/manifest.webmanifest', { cache: 'no-store' })
                .then(() => {
                    window.location.reload();
                })
                .catch(() => {
                    setTimeout(() => {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = 'Coba Sambungkan Ulang';
                        }
                    }, 1200);
                });
        }

        window.addEventListener('online', () => {
            const status = document.getElementById('offlineStatus');
            if (status) {
                status.style.background = 'rgba(80, 205, 137, 0.15)';
                status.style.color = '#50cd89';
                status.innerHTML = '<span class="status-dot" style="background:#50cd89;"></span>Koneksi Pulih! Memuat...';
            }
            setTimeout(() => {
                window.location.reload();
            }, 800);
        });
    </script>
</body>
</html>
