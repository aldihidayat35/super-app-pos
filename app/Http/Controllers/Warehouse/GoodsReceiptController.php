<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\GoodsReceiptStatus;
use App\Enums\ProductStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreGoodsReceiptRequest;
use App\Http\Requests\Warehouse\UpdateGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Product\ProductSkuService;
use App\Services\Warehouse\GoodsReceiptService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        return view('warehouse.goods-receipts.index', [
            'receipts' => $this->query($request)->paginate(15)->withQueryString(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => GoodsReceiptStatus::options(),
            'filters' => $request->only(['supplier_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GoodsReceipt::class);

        return view('warehouse.goods-receipts.create', $this->formData($request) + [
            'receipt' => new GoodsReceipt(['received_at' => now(), 'status' => GoodsReceiptStatus::DRAFT]),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request, GoodsReceiptService $service): RedirectResponse
    {
        try {
            $data = $this->validatedWithProof($request);
            $receipt = $service->createDraft($data, $request->user());

            if ($request->input('action') === 'post') {
                $this->authorize('post', $receipt);
                $receipt = $service->post($receipt, $request->user());
            }

            return redirect()
                ->route('warehouse.goods-receipts.show', $receipt)
                ->with('notification', ['type' => 'success', 'message' => "Receipt {$receipt->number} berhasil disimpan."]);
        } catch (ServiceException $e) {
            return back()->withInput()->with('notification', ['type' => 'danger', 'message' => $e->getMessage()]);
        }
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('view', $goodsReceipt);

        return view('warehouse.goods-receipts.show', [
            'receipt' => $goodsReceipt->load([
                'purchaseOrder.items',
                'warehouse.workLocation',
                'destinationWorkLocation',
                'supplier',
                'receiver',
                'items.product',
                'items.unit',
                'items.warehouseLocation',
                'items.qcResults',
                'stockMutations.product',
                'stockMutations.actor',
                'costHistories.product',
            ]),
        ]);
    }

    public function edit(Request $request, GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('update', $goodsReceipt);

        return view('warehouse.goods-receipts.edit', $this->formData($request, $goodsReceipt) + [
            'receipt' => $goodsReceipt->load('items'),
        ]);
    }

    public function update(UpdateGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt, GoodsReceiptService $service): RedirectResponse
    {
        $this->authorize('update', $goodsReceipt);

        try {
            $data = $this->validatedWithProof($request);
            $receipt = $service->updateDraft($goodsReceipt, $data, $request->user());

            if ($request->input('action') === 'post') {
                $this->authorize('post', $receipt);
                $receipt = $service->post($receipt, $request->user());
            }

            return redirect()
                ->route('warehouse.goods-receipts.show', $receipt)
                ->with('notification', ['type' => 'success', 'message' => 'Receipt berhasil diperbarui.']);
        } catch (ServiceException $e) {
            return back()->withInput()->with('notification', ['type' => 'danger', 'message' => $e->getMessage()]);
        }
    }

    public function post(Request $request, GoodsReceipt $goodsReceipt, GoodsReceiptService $service): RedirectResponse
    {
        $this->authorize('post', $goodsReceipt);

        try {
            $receipt = $service->post($goodsReceipt, $request->user());

            return redirect()
                ->route('warehouse.goods-receipts.show', $receipt)
                ->with('notification', ['type' => 'success', 'message' => 'Receipt berhasil di-posting dan stok/HPP sudah diperbarui.']);
        } catch (ServiceException $e) {
            return back()->withInput()->with('notification', ['type' => 'danger', 'message' => $e->getMessage()]);
        }
    }

    public function print(GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('view', $goodsReceipt);

        return view('warehouse.goods-receipts.print', [
            'receipt' => $goodsReceipt->load(['purchaseOrder', 'warehouse.workLocation', 'destinationWorkLocation', 'supplier', 'receiver', 'items.warehouseLocation']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nomor', 'PO', 'Supplier', 'Lokasi Penerima', 'Tanggal', 'Penerima', 'Accepted', 'Rejected', 'Damaged', 'Status']);

            $this->query($request)->chunk(200, function ($receipts) use ($handle): void {
                foreach ($receipts as $receipt) {
                    fputcsv($handle, [
                        $receipt->number,
                        $receipt->purchaseOrder?->number,
                        $receipt->supplier?->name,
                        $receipt->destinationName(),
                        optional($receipt->received_at)->format('Y-m-d'),
                        $receipt->receiver?->name,
                        $receipt->acceptedQuantity(),
                        $receipt->rejectedQuantity(),
                        $receipt->damagedQuantity(),
                        $receipt->status->label(),
                    ]);
                }
            });

            fclose($handle);
        }, 'goods-receipts.csv');
    }

    private function query(Request $request): mixed
    {
        return GoodsReceipt::query()
            ->with(['purchaseOrder', 'supplier', 'warehouse', 'destinationWorkLocation', 'receiver', 'items'])
            ->where(function ($query) use ($request): void {
                $locationIds = $request->user()?->permittedWorkLocationIds() ?? [];
                $activeId = (int) $request->session()->get('work_location.id', 0);
                if ($activeId > 0 && in_array($activeId, $locationIds, true)) {
                    $locationIds = [$activeId];
                }
                $query->whereIn('destination_work_location_id', $locationIds)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('destination_work_location_id')
                        ->whereHas('warehouse', fn ($warehouse) => $warehouse->whereIn('work_location_id', $locationIds)));
            })
            ->when($request->integer('supplier_id') > 0, fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('received_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('received_at', '<=', $request->query('date_to')))
            ->latest('received_at')
            ->latest('id');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, ?GoodsReceipt $receipt = null): array
    {
        $user = $request->user();
        $locationIds = $user?->permittedWorkLocationIds() ?? [];

        $permittedLocations = WorkLocation::query()
            ->whereIn('id', $locationIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $currentLocationId = (int) ($receipt?->destination_work_location_id
            ?: ($request->integer('destination_work_location_id')
                ?: ($request->integer('work_location_id')
                    ?: $permittedLocations->first()?->id)));

        $currentLocation = $permittedLocations->firstWhere('id', $currentLocationId) ?: $permittedLocations->first();

        $suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get();
        $units = Unit::query()->where('is_active', true)->orderBy('name')->get();
        $categories = ProductCategory::query()->where('is_active', true)->orderBy('name')->get();

        $products = Product::query()
            ->where('status', ProductStatus::ACTIVE)
            ->with(['baseUnit', 'units.unit', 'category', 'barcodes'])
            ->orderBy('name')
            ->get();

        $warehouse = $currentLocation ? Warehouse::query()->where('work_location_id', $currentLocation->id)->first() : null;
        $warehouseLocations = $warehouse
            ? WarehouseLocation::query()->where('warehouse_id', $warehouse->id)->where('is_active', true)->orderBy('full_code')->get()
            : collect();

        $readyStatuses = [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::SENT_TO_SUPPLIER, PurchaseOrderStatus::PARTIALLY_RECEIVED];
        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'warehouse.workLocation', 'destinationWorkLocation', 'items.product', 'items.unit'])
            ->whereIn('status', $readyStatuses)
            ->where(function ($query) use ($locationIds): void {
                $query->whereIn('destination_work_location_id', $locationIds)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('destination_work_location_id')
                        ->whereHas('warehouse', fn ($warehouse) => $warehouse->whereIn('work_location_id', $locationIds)));
            })
            ->latest('order_date')
            ->limit(100)
            ->get();

        $selectedPo = $receipt?->purchaseOrder ?: ($request->integer('purchase_order_id') > 0
            ? $purchaseOrders->firstWhere('id', $request->integer('purchase_order_id'))
            : null);

        return [
            'permittedLocations' => $permittedLocations,
            'currentLocation' => $currentLocation,
            'suppliers' => $suppliers,
            'units' => $units,
            'categories' => $categories,
            'products' => $products,
            'warehouseLocations' => $warehouseLocations,
            'purchaseOrders' => $purchaseOrders,
            'selectedPo' => $selectedPo?->loadMissing(['items.product', 'items.unit', 'warehouse.workLocation', 'destinationWorkLocation']),
        ];
    }

    public function storeProductAjax(Request $request, ProductSkuService $skuService): JsonResponse
    {
        $this->authorize('create', Product::class);

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

            $product = Product::create([
                'name' => $validated['name'],
                'category_id' => $validated['category_id'],
                'base_unit_id' => $validated['base_unit_id'],
                'sku' => $sku,
                'status' => ProductStatus::ACTIVE,
                'cost_price' => 0,
                'minimum_price' => 0,
                'minimum_order' => 0,
                'minimum_stock' => 0,
                'safety_stock' => 0,
            ]);

            $productUnit = ProductUnit::create([
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'conversion_factor' => 1,
                'is_base' => true,
            ]);

            if (! empty($validated['barcode'])) {
                ProductBarcode::create([
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit->id,
                    'code' => trim($validated['barcode']),
                    'type' => 'barcode',
                    'is_primary' => true,
                    'is_active' => true,
                ]);
            }

            return $product;
        });

        $product->load(['baseUnit', 'units.unit', 'category', 'barcodes']);
        $firstBarcode = $product->barcodes->first();

        return response()->json([
            'success' => true,
            'message' => 'Produk baru berhasil ditambahkan.',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category_name' => $product->category->name,
                'base_unit_id' => $product->base_unit_id,
                'base_unit_name' => $product->baseUnit->name,
                'barcode' => $firstBarcode ? $firstBarcode->code : '',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedWithProof(StoreGoodsReceiptRequest|UpdateGoodsReceiptRequest $request): array
    {
        $data = $request->validated();

        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')?->store('goods-receipts', 'public');
        }

        return $data;
    }
}
