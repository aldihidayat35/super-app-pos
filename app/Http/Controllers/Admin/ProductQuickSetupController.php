<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BatchProductQuickSetupRequest;
use App\Http\Requests\Admin\SaveProductQuickSetupRequest;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\WorkLocation;
use App\Services\Product\ProductQuickSetupService;
use App\Services\Product\ProductSkuService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductQuickSetupController extends Controller
{
    public function __construct(
        private readonly ProductQuickSetupService $quickSetupService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->can('products.quick_setup'), 403);

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

    public function export(Request $request): StreamedResponse|BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->can('products.quick_setup') || $user->can('products.export')), 403);

        $isUnrestricted = $user->hasUnrestrictedLocationScope();
        if ($isUnrestricted) {
            $locations = WorkLocation::query()->where('is_active', true)->orderBy('name')->get();
            $permittedIds = $locations->pluck('id')->all();
        } else {
            $permittedIds = $user->permittedWorkLocationIds();
            abort_if(empty($permittedIds), 403, 'Akun Anda belum memiliki penugasan lokasi kerja.');
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
            'completeness' => (string) $request->query('completeness', 'complete'),
            'category_id' => $request->query('category_id'),
            'q' => trim((string) $request->query('q', '')),
        ];

        $query = $this->quickSetupService->buildQuery($filters, $currentLocation);
        $units = Unit::query()->where('is_active', true)->orderBy('name')->get();

        $locationSlug = Str::slug($currentLocation->name);
        $timestamp = now()->format('Ymd-His');
        $filename = "setup-cepat-produk-{$locationSlug}-{$timestamp}";

        $headings = [
            'No',
            'SKU',
            'Nama Produk',
            'Kategori',
            'Barcode',
            'Satuan Dasar',
            'Lokasi Kerja',
            'Satuan HPP Kulakan',
            'HPP Satuan Kulakan (Rp)',
            'HPP Modal per Pcs (Rp)',
            'Satuan Stok Awal',
            'Jumlah Stok Awal',
            'Total Stok Fisik (Pcs)',
            'Satuan Jual',
            'Harga Jual Satuan (Rp)',
            'Harga Jual Min per Pcs (Rp)',
            'Margin Laba per Pcs (Rp)',
            'Margin Persen (%)',
            'Status Setup',
            'Status Produk',
        ];

        if ($request->query('format') === 'xlsx') {
            $rows = [];
            $no = 1;
            foreach ($query->get() as $product) {
                $rows[] = $this->quickSetupService->formatProductForExport($product, $currentLocation, $units, $no);
                $no++;
            }

            return Excel::download(new ArrayReportExport($rows, $headings), "{$filename}.xlsx");
        }

        return response()->streamDownload(function () use ($query, $currentLocation, $units, $headings): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headings);

            $no = 1;
            $query->chunk(200, function ($products) use ($handle, $currentLocation, $units, &$no): void {
                foreach ($products as $product) {
                    $row = $this->quickSetupService->formatProductForExport($product, $currentLocation, $units, $no);
                    fputcsv($handle, array_values($row));
                    $no++;
                }
            });

            fclose($handle);
        }, "{$filename}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
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

    public function storeProductAjax(Request $request, ProductSkuService $skuService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->can('products.quick_setup'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:product_categories,id'],
            'base_unit_id' => ['required', 'exists:units,id'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:product_barcodes,code'],
        ], [
            'name.required' => 'Nama produk wajib diisi.',
            'category_id.required' => 'Kategori produk wajib dipilih.',
            'base_unit_id.required' => 'Satuan dasar wajib dipilih.',
            'sku.unique' => 'SKU sudah digunakan oleh produk lain.',
            'barcode.unique' => 'Barcode sudah digunakan oleh produk lain.',
        ]);

        $product = DB::transaction(function () use ($validated, $skuService): Product {
            $sku = ! empty($validated['sku']) ? trim($validated['sku']) : $skuService->generate();

            $product = Product::query()->create([
                'name' => trim($validated['name']),
                'sku' => $sku,
                'category_id' => $validated['category_id'],
                'base_unit_id' => $validated['base_unit_id'],
                'status' => 'active',
                'cost_price' => 0,
                'minimum_price' => 0,
                'minimum_order' => 0,
                'minimum_stock' => 0,
                'safety_stock' => 0,
            ]);

            ProductUnit::query()->create([
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'conversion_factor' => 1,
                'is_base' => true,
                'is_sellable' => true,
                'is_active' => true,
            ]);

            if (! empty($validated['barcode'])) {
                ProductBarcode::query()->create([
                    'product_id' => $product->id,
                    'code' => trim($validated['barcode']),
                    'is_primary' => true,
                ]);
            }

            return $product;
        });

        $product->load(['baseUnit', 'category', 'barcodes']);
        $firstBarcode = $product->barcodes->first();

        return response()->json([
            'success' => true,
            'message' => 'Produk baru berhasil ditambahkan.',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category_id' => $product->category_id,
                'category_name' => $product->category->name,
                'base_unit_id' => $product->base_unit_id,
                'base_unit_name' => $product->baseUnit->name,
                'barcode' => $firstBarcode ? $firstBarcode->code : '',
            ],
        ]);
    }

    public function bulkStoreProducts(Request $request, ProductSkuService $skuService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->can('products.quick_setup'), 403);

        $validated = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.category_id' => ['required', 'exists:product_categories,id'],
            'products.*.base_unit_id' => ['required', 'exists:units,id'],
            'products.*.sku' => ['nullable', 'string', 'max:100'],
            'products.*.barcode' => ['nullable', 'string', 'max:100'],
        ], [
            'products.required' => 'Minimal satu produk harus diisi.',
            'products.*.name.required' => 'Nama produk wajib diisi.',
            'products.*.category_id.required' => 'Kategori produk wajib dipilih.',
            'products.*.base_unit_id.required' => 'Satuan dasar wajib dipilih.',
        ]);

        $barcodesInPayload = [];
        foreach ($validated['products'] as $item) {
            $code = ! empty($item['barcode']) ? trim($item['barcode']) : null;
            if ($code !== null) {
                if (in_array($code, $barcodesInPayload, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Barcode \"{$code}\" duplikat di dalam daftar input.",
                    ], 422);
                }
                $barcodesInPayload[] = $code;

                if (ProductBarcode::query()->where('code', $code)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => "Barcode \"{$code}\" sudah digunakan oleh produk lain di database.",
                    ], 422);
                }
            }
        }

        $createdCount = 0;
        $createdProducts = DB::transaction(function () use ($validated, $skuService, &$createdCount): array {
            $results = [];

            foreach ($validated['products'] as $item) {
                $sku = ! empty($item['sku']) ? trim($item['sku']) : $skuService->generate();

                $product = Product::query()->create([
                    'name' => trim($item['name']),
                    'sku' => $sku,
                    'category_id' => $item['category_id'],
                    'base_unit_id' => $item['base_unit_id'],
                    'status' => 'active',
                    'cost_price' => 0,
                    'minimum_price' => 0,
                    'minimum_order' => 0,
                    'minimum_stock' => 0,
                    'safety_stock' => 0,
                ]);

                ProductUnit::query()->create([
                    'product_id' => $product->id,
                    'unit_id' => $product->base_unit_id,
                    'conversion_factor' => 1,
                    'is_base' => true,
                    'is_sellable' => true,
                    'is_active' => true,
                ]);

                if (! empty($item['barcode'])) {
                    ProductBarcode::query()->create([
                        'product_id' => $product->id,
                        'code' => trim($item['barcode']),
                        'is_primary' => true,
                    ]);
                }

                $results[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ];
                $createdCount++;
            }

            return $results;
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyimpan {$createdCount} produk baru.",
            'created_count' => $createdCount,
            'products' => $createdProducts,
        ]);
    }
}
