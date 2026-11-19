<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\System\AppBrandingService;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    /**
     * Sajikan manifest PWA secara dinamis berbasis data branding perusahaan saat ini.
     */
    public function show(AppBrandingService $brandingService): JsonResponse
    {
        $manifest = $brandingService->buildManifestData();

        return response()
            ->json($manifest)
            ->header('Content-Type', 'application/manifest+json; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
