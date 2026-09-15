<?php

namespace App\Services\Retail;

use App\Enums\ProductPriceStatus;
use App\Enums\ProductStatus;
use App\Exceptions\ServiceException;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductPrice;
use App\Models\ProductRequest;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Product\ProductSkuService;
use App\Services\Product\UnitConversionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductRequestService
{
    public function __construct(
        private readonly ProductSkuService $sku,
        private readonly UnitConversionService $units,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): ProductRequest
    {
        $this->assertNoDuplicate($data);

        return DB::transaction(function () use ($data, $actor): ProductRequest {
            return ProductRequest::query()->create([
                ...$data,
                'number' => 'PP-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'requested_by' => $actor->id,
                'status' => 'pending_approval',
            ]);
        });
    }

    public function approve(ProductRequest $request, User $actor, ?string $notes = null): ProductRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes): ProductRequest {
            $request = ProductRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'pending_approval') {
                throw ServiceException::validation('Pengajuan produk ini sudah diputuskan.');
            }

            $this->assertNoDuplicate([
                'name' => $request->name,
                'proposed_sku' => $request->proposed_sku,
                'barcode' => $request->barcode,
            ]);

            $sku = $request->proposed_sku ?: $this->sku->generate();
            $product = Product::query()->create([
                'sku' => $sku,
                'name' => $request->name,
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,
                'description' => $request->description,
                'base_unit_id' => $request->base_unit_id,
                'status' => ProductStatus::ACTIVE,
                'minimum_order' => 0,
                'minimum_stock' => 0,
                'safety_stock' => 0,
                'cost_price' => $request->purchase_cost,
                'minimum_price' => $request->proposed_selling_price,
                'main_image_path' => $request->photo_path,
            ]);

            $this->units->syncProductUnits($product, [[
                'unit_id' => $request->base_unit_id,
                'name' => $request->baseUnit?->name,
                'conversion_factor' => 1,
                'is_sellable' => true,
                'is_active' => true,
            ]]);

            if ($request->barcode) {
                ProductBarcode::query()->create([
                    'product_id' => $product->id,
                    'product_unit_id' => $product->units()->value('id'),
                    'code' => $request->barcode,
                    'type' => 'barcode',
                    'is_primary' => true,
                    'is_active' => true,
                ]);
            }

            ProductPrice::query()->create([
                'product_id' => $product->id,
                'branch_id' => $request->branch_id,
                'channel' => 'retail',
                'price_ring' => 'retail',
                'min_price' => $request->proposed_selling_price,
                'recommended_price' => $request->proposed_selling_price,
                'max_price' => $request->proposed_selling_price,
                'minimum_qty' => 1,
                'priority' => 100,
                'status' => ProductPriceStatus::ACTIVE,
                'notes' => "Dibuat dari pengajuan {$request->number}",
            ]);

            if ($request->supplier_id) {
                SupplierProduct::query()->updateOrCreate(
                    ['supplier_id' => $request->supplier_id, 'product_id' => $product->id],
                    ['last_price' => $request->purchase_cost],
                );
            }

            $request->forceFill([
                'status' => 'approved', 'created_product_id' => $product->id,
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes,
            ])->save();
            activity()->causedBy($actor)->performedOn($product)->withProperties(['product_request_id' => $request->id])->log('product.created_from_store_request');

            return $request->fresh(['createdProduct', 'reviewer']);
        });
    }

    public function reject(ProductRequest $request, User $actor, string $notes): ProductRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes): ProductRequest {
            $request = ProductRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'pending_approval') {
                throw ServiceException::validation('Pengajuan produk ini sudah diputuskan.');
            }
            $request->forceFill([
                'status' => 'rejected', 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'review_notes' => $notes,
            ])->save();

            return $request;
        });
    }

    /** @param array<string, mixed> $data */
    private function assertNoDuplicate(array $data): void
    {
        $duplicate = Product::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) ($data['name'] ?? '')))])
            ->when(filled($data['proposed_sku'] ?? null), fn ($query) => $query->orWhere('sku', $data['proposed_sku']))
            ->when(filled($data['barcode'] ?? null), fn ($query) => $query->orWhereHas('barcodes', fn ($barcode) => $barcode->where('code', $data['barcode'])))
            ->first();

        if ($duplicate) {
            throw ServiceException::validation("Produk serupa sudah tersedia: {$duplicate->sku} - {$duplicate->name}.");
        }
    }
}
