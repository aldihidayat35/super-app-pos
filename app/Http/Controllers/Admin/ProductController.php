<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Product\ProductSkuService;
use App\Services\Product\UnitConversionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $filters = [
            'category_id' => $request->query('category_id'),
            'brand_id' => $request->query('brand_id'),
            'status' => $request->query('status'),
            'stock_filter' => $request->query('stock_filter'),
            'q' => trim((string) $request->query('q')),
        ];

        $products = Product::query()
            ->with(['category', 'brand', 'baseUnit'])
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('sku', 'like', "%{$filters['q']}%")->orWhere('name', 'like', "%{$filters['q']}%")))
            ->when($filters['category_id'], fn ($query, $value) => $query->where('category_id', $value))
            ->when($filters['brand_id'], fn ($query, $value) => $query->where('brand_id', $value))
            ->when($filters['status'], fn ($query, $value) => $query->where('status', $value))
            ->when($filters['stock_filter'] === 'minimum', fn ($query) => $query->whereColumn('total_stock', '<=', 'minimum_stock'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'brands' => ProductBrand::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => ProductStatus::options(),
        ]);
    }

    public function create(UnitConversionService $conversion): View
    {
        $this->authorize('create', Product::class);
        $baseUnit = Unit::query()->where('is_active', true)->orderBy('id')->first();

        return view('admin.products.create', $this->formData(new Product([
            'status' => ProductStatus::ACTIVE,
            'minimum_order' => 0,
            'minimum_stock' => 0,
            'safety_stock' => 0,
            'base_unit_id' => $baseUnit?->id,
        ]), $conversion->defaultUnitsPayload((int) $baseUnit?->id)));
    }

    public function store(StoreProductRequest $request, ProductSkuService $skuService, UnitConversionService $conversion): RedirectResponse
    {
        try {
            $product = DB::transaction(function () use ($request, $skuService, $conversion): Product {
                $data = $this->productData($request->validated());
                $data['sku'] = $data['sku'] ?: $skuService->generate();
                $conversion->assertValidUnitPayload($request->validated('units'), (int) $data['base_unit_id']);

                if ($request->hasFile('main_image')) {
                    $data['main_image_path'] = $request->file('main_image')?->store('products', 'public');
                }

                $product = Product::query()->create($data);
                $conversion->syncProductUnits($product, $request->validated('units'));
                $this->syncBarcodes($product, $request->validated('barcodes', []));
                $this->syncPrimaryImage($product, $data['main_image_path'] ?? null);
                activity()->causedBy($request->user())->performedOn($product)->log('product.created');

                return $product;
            });
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['units' => $exception->getMessage()]);
        }

        return redirect()->route('admin.products.show', $product)->with('notification', ['type' => 'success', 'message' => 'Produk berhasil dibuat.']);
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        return view('admin.products.show', [
            'product' => $product->load(['category', 'subcategory', 'brand', 'baseUnit', 'defaultWarehouse', 'units.unit', 'barcodes.productUnit.unit', 'images']),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('admin.products.edit', $this->formData($product->load(['units', 'barcodes'])));
    }

    public function update(UpdateProductRequest $request, Product $product, UnitConversionService $conversion): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $product, $conversion): void {
                $data = $this->productData($request->validated());
                $conversion->assertValidUnitPayload($request->validated('units'), (int) $data['base_unit_id']);

                if ($product->has_transactions && $product->sku !== $data['sku']) {
                    throw ValidationException::withMessages(['sku' => 'SKU produk yang sudah dipakai transaksi tidak boleh diubah.']);
                }

                if ($request->hasFile('main_image')) {
                    $data['main_image_path'] = $request->file('main_image')?->store('products', 'public');
                }

                $product->fill($data)->save();
                $conversion->syncProductUnits($product, $request->validated('units'));
                $this->syncBarcodes($product, $request->validated('barcodes', []));
                $this->syncPrimaryImage($product, $data['main_image_path'] ?? null);
                activity()->causedBy($request->user())->performedOn($product)->log('product.updated');
            });
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['units' => $exception->getMessage()]);
        }

        return redirect()->route('admin.products.show', $product)->with('notification', ['type' => 'success', 'message' => 'Produk berhasil diperbarui.']);
    }

    public function deactivate(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $product->forceFill(['status' => ProductStatus::INACTIVE])->save();
        activity()->causedBy($request->user())->performedOn($product)->log('product.deactivated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Produk berhasil dinonaktifkan.']);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Product::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['sku', 'name', 'category_code', 'brand_code', 'base_unit_code', 'status', 'minimum_order', 'minimum_stock', 'safety_stock']);
            Product::query()->with(['category', 'brand', 'baseUnit'])->orderBy('sku')->each(function (Product $product) use ($handle): void {
                fputcsv($handle, [
                    $product->sku,
                    $product->name,
                    $product->category?->code,
                    $product->brand?->code,
                    $product->baseUnit?->code,
                    $product->status->value,
                    $product->minimum_order,
                    $product->minimum_stock,
                    $product->safety_stock,
                ]);
            });
            fclose($handle);
        }, 'produk-'.now()->format('Ymd-His').'.csv');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function productData(array $validated): array
    {
        return collect($validated)->only([
            'sku',
            'name',
            'category_id',
            'subcategory_id',
            'brand_id',
            'model',
            'size',
            'color',
            'material',
            'description',
            'base_unit_id',
            'status',
            'minimum_order',
            'minimum_stock',
            'safety_stock',
            'weight',
            'volume',
            'default_warehouse_id',
            'cost_price',
            'minimum_price',
        ])->all();
    }

    /** @param array<int, array<string, mixed>> $barcodes */
    private function syncBarcodes(Product $product, array $barcodes): void
    {
        $product->load('units');
        $keptIds = [];

        foreach ($barcodes as $index => $barcode) {
            if (blank($barcode['code'] ?? null)) {
                continue;
            }

            $existingId = $barcode['id'] ?? null;
            $duplicate = ProductBarcode::query()
                ->where('code', $barcode['code'])
                ->when($existingId, fn ($query) => $query->whereKeyNot($existingId))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(["barcodes.{$index}.code" => 'Barcode sudah dipakai produk lain.']);
            }

            $productUnitId = $product->units->firstWhere('unit_id', $product->base_unit_id)?->id;
            $saved = ProductBarcode::query()->updateOrCreate(
                ['id' => $existingId],
                [
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnitId,
                    'code' => $barcode['code'],
                    'type' => $barcode['type'] ?? 'barcode',
                    'is_primary' => $index === 0,
                    'is_active' => true,
                ],
            );
            $keptIds[] = $saved->id;
        }

        ProductBarcode::query()->where('product_id', $product->id)->whereNotIn('id', $keptIds)->delete();
    }

    private function syncPrimaryImage(Product $product, ?string $path): void
    {
        if (! $path) {
            return;
        }

        $product->images()->update(['is_primary' => false]);
        $product->images()->create(['path' => $path, 'alt_text' => $product->name, 'is_primary' => true]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $unitsPayload
     * @return array<string, mixed>
     */
    private function formData(Product $product, ?array $unitsPayload = null): array
    {
        return [
            'product' => $product,
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'brands' => ProductBrand::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => ProductStatus::options(),
            'unitRows' => $unitsPayload ?: $product->units->map(fn ($unit): array => $unit->only(['unit_id', 'name', 'conversion_factor', 'is_sellable', 'is_active']))->values()->all(),
            'barcodeRows' => $product->barcodes->map(fn ($barcode): array => $barcode->only(['id', 'code', 'type']))->values()->all(),
        ];
    }
}
