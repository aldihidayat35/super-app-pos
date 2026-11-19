<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AppBrandingService
{
    /**
     * Sinkronisasikan logo perusahaan ke favicon, PWA icons, dan web app manifest.
     */
    public function sync(): bool
    {
        try {
            $companyName = SystemSetting::getCompanyName();
            $logoPath = SystemSetting::where('key', 'logo_path')
                ->where('group', 'general')
                ->value('value');

            $sourceFile = null;
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                $sourceFile = Storage::disk('public')->path($logoPath);
            }

            // 1. Generate resized PWA icons and favicons if source file exists
            if ($sourceFile && file_exists($sourceFile)) {
                $this->generateIconsFromSource($sourceFile);
            }

            // 2. Update manifest.webmanifest and manifest.json
            $this->updateManifestFiles($companyName);

            return true;
        } catch (Throwable $e) {
            Log::warning('[AppBrandingService] Gagal menyinkronkan branding aplikasi: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Generate icon PWA dan favicon dengan berbagai ukuran standar dari gambar sumber.
     */
    private function generateIconsFromSource(string $sourcePath): void
    {
        $info = @getimagesize($sourcePath);
        if (! $info) {
            return;
        }

        $mime = $info['mime'] ?? '';
        $srcImage = match ($mime) {
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            default => null,
        };

        if (! $srcImage) {
            return;
        }

        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);

        $pwaDir = public_path('assets/media/pwa');
        if (! File::isDirectory($pwaDir)) {
            File::makeDirectory($pwaDir, 0755, true, true);
        }

        // Standard square icons
        $targets = [
            public_path('assets/media/pwa/icon-192x192.png') => 192,
            public_path('assets/media/pwa/icon-512x512.png') => 512,
            public_path('assets/media/pwa/apple-touch-icon.png') => 180,
            public_path('favicon-32x32.png') => 32,
            public_path('favicon-16x16.png') => 16,
            public_path('favicon.ico') => 64, // Modern browsers accept 64x64 PNG in .ico
        ];

        foreach ($targets as $destination => $size) {
            $dstImage = imagecreatetruecolor($size, $size);
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $size, $size, $transparent);

            // Maintain aspect ratio inside the box
            $ratio = min($size / $srcWidth, $size / $srcHeight);
            $newW = (int) round($srcWidth * $ratio);
            $newH = (int) round($srcHeight * $ratio);
            $dstX = (int) round(($size - $newW) / 2);
            $dstY = (int) round(($size - $newH) / 2);

            imagecopyresampled($dstImage, $srcImage, $dstX, $dstY, 0, 0, $newW, $newH, $srcWidth, $srcHeight);
            imagepng($dstImage, $destination, 9);
            imagedestroy($dstImage);
        }

        // Maskable icon with 15% safe-zone padding for Android PWA splash/adaptive icon
        $maskableSize = 512;
        $maskableDst = imagecreatetruecolor($maskableSize, $maskableSize);
        imagealphablending($maskableDst, false);
        imagesavealpha($maskableDst, true);
        $transparent = imagecolorallocatealpha($maskableDst, 255, 255, 255, 127);
        imagefilledrectangle($maskableDst, 0, 0, $maskableSize, $maskableSize, $transparent);

        // Safe area is inner 80% (size * 0.8)
        $safeAreaSize = (int) round($maskableSize * 0.8);
        $ratio = min($safeAreaSize / $srcWidth, $safeAreaSize / $srcHeight);
        $newW = (int) round($srcWidth * $ratio);
        $newH = (int) round($srcHeight * $ratio);
        $dstX = (int) round(($maskableSize - $newW) / 2);
        $dstY = (int) round(($maskableSize - $newH) / 2);

        imagecopyresampled($maskableDst, $srcImage, $dstX, $dstY, 0, 0, $newW, $newH, $srcWidth, $srcHeight);
        imagepng($maskableDst, public_path('assets/media/pwa/icon-maskable-512x512.png'), 9);
        imagedestroy($maskableDst);

        imagedestroy($srcImage);
    }

    /**
     * Perbarui isi manifest.webmanifest dan manifest.json dengan data identitas perusahaan.
     */
    private function updateManifestFiles(string $companyName): void
    {
        $manifestData = $this->buildManifestData($companyName);
        $json = json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json) {
            File::put(public_path('manifest.webmanifest'), $json);
            File::put(public_path('manifest.json'), $json);
        }
    }

    /**
     * Buat struktur data Web App Manifest.
     *
     * @return array<string, mixed>
     */
    public function buildManifestData(?string $companyName = null): array
    {
        $companyName ??= SystemSetting::getCompanyName();
        $timestamp = time();

        return [
            'name' => $companyName.' - Sistem Manajemen Terpadu',
            'short_name' => $companyName,
            'description' => 'Aplikasi Manajemen Gudang, Kasir Retail POS, Presensi Karyawan, dan Toko Internal '.$companyName,
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#1E1E2D',
            'theme_color' => '#1B84FF',
            'lang' => 'id-ID',
            'dir' => 'ltr',
            'icons' => [
                [
                    'src' => '/assets/media/pwa/icon-192x192.png?v='.$timestamp,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/assets/media/pwa/icon-512x512.png?v='.$timestamp,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/assets/media/pwa/icon-maskable-512x512.png?v='.$timestamp,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => 'Kasir POS',
                    'short_name' => 'POS',
                    'description' => 'Buka modul kasir penjualan retail',
                    'url' => '/retail/pos',
                    'icons' => [
                        [
                            'src' => '/assets/media/pwa/icon-192x192.png?v='.$timestamp,
                            'sizes' => '192x192',
                        ],
                    ],
                ],
                [
                    'name' => 'Presensi Karyawan',
                    'short_name' => 'Presensi',
                    'description' => 'Check-in dan check-out absensi shift',
                    'url' => '/attendance/check',
                    'icons' => [
                        [
                            'src' => '/assets/media/pwa/icon-192x192.png?v='.$timestamp,
                            'sizes' => '192x192',
                        ],
                    ],
                ],
                [
                    'name' => 'Cek Stok Barang',
                    'short_name' => 'Stok',
                    'description' => 'Lihat inventaris dan ketersediaan stok produk',
                    'url' => '/warehouse/stocks',
                    'icons' => [
                        [
                            'src' => '/assets/media/pwa/icon-192x192.png?v='.$timestamp,
                            'sizes' => '192x192',
                        ],
                    ],
                ],
                [
                    'name' => 'Kotak Approval',
                    'short_name' => 'Approval',
                    'description' => 'Lihat dokumen yang menunggu persetujuan',
                    'url' => '/control/inbox',
                    'icons' => [
                        [
                            'src' => '/assets/media/pwa/icon-192x192.png?v='.$timestamp,
                            'sizes' => '192x192',
                        ],
                    ],
                ],
            ],
            'categories' => [
                'business',
                'finance',
                'productivity',
            ],
        ];
    }
}
