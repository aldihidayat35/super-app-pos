<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BatchProductQuickSetupRequest;
use App\Http\Requests\Admin\SaveProductQuickSetupRequest;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\WorkLocation;
use App\Services\Product\ProductQuickSetupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductQuickSetupController extends Controller
{
    public function __construct(
        private readonly ProductQuickSetupService $quickSetupService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && ($user->can('products.view') || $user->can('products.update') || $user->can('products.create')), 403);

        $isUnrestricted = $user->hasUnrestrictedLocationScope();
        if ($isUnrestricted) {
            $locations = WorkLocation::query()->where('is_active', true)->orderBy('name')->get();
            $permittedIds = $locations->pluck('id')->all();
        } else {
            $permittedIds = $user->permittedWorkLocationIds();
            abort_if(empty($permittedIds), 403, 'Akun Anda belum memiliki penugasan lokasi kerja.');
            $locations = WorkLocation::query()->whereIn('id', $permittedIds)->where('is_active', true)->orderBy('name')->get();
        }

        $requestedLocationId = $request->integer('work_location_id');
        if ($requestedLocationId) {
            abort_unless(in_array($requestedLocationId, $permittedIds, true), 403, 'Anda tidak memiliki akses ke lokasi kerja ini.');
            $selectedLocationId = $requestedLocationId;
        } else {
            $defaultLocation = $user->workLocations()->wherePivot('is_default', true)->first() ?? $user->workLocations()->first();
            $selectedLocationId = ($defaultLocation && in_array($defaultLocation->id, $permittedIds, true))
                ? $defaultLocation->id
                : ($permittedIds[0] ?? null);
        }

        abort_if(! $selectedLocationId, 403, 'Lokasi kerja tidak valid.');

        /** @var WorkLocation $currentLocation */
        $currentLocation = WorkLocation::query()->findOrFail($selectedLocationId);

        $filters = [
            'completeness' => (string) $request->query('completeness', 'incomplete'),
            'category_id' => $request->query('category_id'),
            'q' => trim((string) $request->query('q', '')),
            'per_page' => $request->integer('per_page', 25),
        ];

        $products = $this->quickSetupService->getProducts($filters, $currentLocation);
        $stats = $this->quickSetupService->getStats($currentLocation);
        $units = Unit::query()->where('is_active', true)->orderBy('name')->get();
        $categories = ProductCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.products.quick-setup', [
            'currentLocation' => $currentLocation,
            'locations' => $locations,
            'products' => $products,
            'stats' => $stats,
            'units' => $units,
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function saveRow(SaveProductQuickSetupRequest $request): JsonResponse
    {
        $location = WorkLocation::query()->findOrFail($request->integer('work_location_id'));
        $result = $this->quickSetupService->saveRow($request->validated(), $request->user(), $location, $request);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['message'],
        ]);
    }

    public function batchSave(BatchProductQuickSetupRequest $request): JsonResponse|RedirectResponse
    {
        $location = WorkLocation::query()->findOrFail($request->integer('work_location_id'));
        $result = $this->quickSetupService->saveBatch($request->validated('rows'), $request->user(), $location, $request);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $result['failed_count'] === 0,
                'saved_count' => $result['saved_count'],
                'failed_count' => $result['failed_count'],
                'errors' => $result['errors'],
                'message' => "Berhasil menyimpan {$result['saved_count']} produk.".($result['failed_count'] > 0 ? " ({$result['failed_count']} gagal)" : ''),
            ]);
        }

        $notificationType = $result['failed_count'] === 0 ? 'success' : 'warning';
        $message = "Berhasil menyimpan {$result['saved_count']} produk.".($result['failed_count'] > 0 ? " ({$result['failed_count']} gagal: ".implode('; ', $result['errors']).')' : '');

        return back()->with('notification', [
            'type' => $notificationType,
            'message' => $message,
        ]);
    }
}
