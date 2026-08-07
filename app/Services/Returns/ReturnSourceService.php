<?php

namespace App\Services\Returns;

use App\Enums\B2bOrderStatus;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PosSaleStatus;
use App\Enums\ReturnStatus;
use App\Enums\StockTransferStatus;
use App\Exceptions\ServiceException;
use App\Models\B2bOrder;
use App\Models\B2bOrderItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Support\Decimal;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

class ReturnSourceService
{
    /** @return array<string, string> */
    public static function sourceOptions(): array
    {
        return [
            'pos' => 'POS / Penjualan Toko',
            'b2b' => 'Pesanan B2B',
            'supplier' => 'Supplier / Goods Receipt',
            'transfer' => 'Transfer Stok',
            'manual' => 'Manual / Lainnya',
        ];
    }

    /** @return array{results: list<array<string, mixed>>, pagination: array{more: bool}} */
    public function searchDocuments(string $sourceType, int $workLocationId, string $search, int $page, int $perPage): array
    {
        $perPage = min(max($perPage, 10), 50);
        $page = max($page, 1);

        $paginator = match ($sourceType) {
            'pos' => PosSale::query()
                ->with(['customer', 'workLocation'])
                ->withCount('items')
                ->where('work_location_id', $workLocationId)
                ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('business_name', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%")
                        ->orWhere('pic_name', 'like', "%{$search}%"))))
                ->latest('completed_at')->paginate($perPage, ['*'], 'page', $page),
            'b2b' => B2bOrder::query()
                ->with(['customer', 'shipments' => fn ($query) => $query->where('origin_work_location_id', $workLocationId)->with('originWorkLocation')])
                ->withCount('items')
                ->whereIn('status', [B2bOrderStatus::RECEIVED->value, B2bOrderStatus::COMPLETED->value, B2bOrderStatus::RETURN_REQUESTED->value, B2bOrderStatus::RETURNED->value])
                ->whereHas('shipments', fn ($query) => $query->where('origin_work_location_id', $workLocationId))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('business_name', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%")
                        ->orWhere('pic_name', 'like', "%{$search}%"))))
                ->latest('created_at')->paginate($perPage, ['*'], 'page', $page),
            'supplier' => GoodsReceipt::query()
                ->with(['supplier', 'warehouse.workLocation', 'purchaseOrder'])
                ->withCount('items')
                ->where('status', GoodsReceiptStatus::POSTED->value)
                ->whereHas('warehouse', fn ($query) => $query->where('work_location_id', $workLocationId))
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('delivery_note_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"))))
                ->latest('received_at')->paginate($perPage, ['*'], 'page', $page),
            'transfer' => StockTransfer::query()
                ->with(['sourceWorkLocation', 'destinationWorkLocation'])
                ->withCount('items')
                ->where('destination_work_location_id', $workLocationId)
                ->whereIn('status', [StockTransferStatus::PARTIALLY_RECEIVED->value, StockTransferStatus::FULLY_RECEIVED->value, StockTransferStatus::COMPLETED->value])
                ->when($search !== '', fn ($query) => $query->where('number', 'like', "%{$search}%"))
                ->latest('transfer_date')->paginate($perPage, ['*'], 'page', $page),
            default => null,
        };

        if ($paginator === null) {
            return ['results' => [], 'pagination' => ['more' => false]];
        }

        return [
            'results' => $paginator->getCollection()->map(fn (Model $document): array => $this->documentSummary($this->asSourceDocument($document)))->values()->all(),
            'pagination' => ['more' => $paginator->hasMorePages()],
        ];
    }

    /** @return array<string, mixed> */
    public function document(string $sourceType, int $referenceId, int $workLocationId): array
    {
        return $this->documentSummary($this->findDocument($sourceType, $referenceId, $workLocationId));
    }

    /** @return list<array<string, mixed>> */
    public function documentItems(string $sourceType, int $referenceId, int $workLocationId, bool $showCost, ?int $excludeReturnId = null): array
    {
        $document = $this->findDocument($sourceType, $referenceId, $workLocationId);

        return $this->documentSourceItems($document)
            ->map(fn (Model $item): array => $this->sourceItemData($this->asSourceItem($item), $showCost, $excludeReturnId))
            ->filter(fn (array $item): bool => Decimal::compare((string) $item['maximum_quantity'], '0') > 0)
            ->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function manualItems(string $search, bool $showCost): array
    {
        return Product::query()
            ->with('baseUnit')
            ->where('status', 'active')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('name')->limit(30)->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'product_id' => $product->id,
                'source_item_id' => null,
                'source_item_type' => null,
                'text' => $product->sku.' - '.$product->name,
                'sku' => $product->sku,
                'name' => $product->name,
                'thumbnail' => $product->main_image_url,
                'unit' => $product->baseUnit?->name ?: 'Unit',
                'source_quantity' => null,
                'already_returned' => '0.0000',
                'maximum_quantity' => null,
                'unit_cost' => $showCost ? (string) $product->cost_price : null,
                'warehouse_location_id' => null,
            ])->values()->all();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizeReturnData(array $data, ?int $excludeReturnId = null): array
    {
        $sourceType = (string) $data['source_type'];
        $workLocationId = (int) $data['work_location_id'];

        if (in_array($sourceType, ['manual', 'branch'], true)) {
            $data['reference_type'] = $sourceType === 'manual' ? 'manual' : ($data['reference_type'] ?? 'manual');
            $data['reference_id'] = null;
            $data['reference_no'] = $data['reference_no'] ?? null;
            $data['items'] = collect((array) $data['items'])->map(function (array $item) use ($sourceType): array {
                $product = Product::query()->with('baseUnit')->where('status', 'active')->findOrFail($item['product_id']);

                return array_replace($item, [
                    'unit_id' => $product->base_unit_id,
                    'source_quantity' => $sourceType === 'branch'
                        ? ($item['source_quantity'] ?? $item['quantity_requested'])
                        : $item['quantity_requested'],
                    'unit_cost_snapshot' => (string) $product->cost_price,
                    'conversion_factor_snapshot' => '1.000000',
                    'source_item_type' => $sourceType === 'branch' ? ($item['source_item_type'] ?? null) : null,
                    'source_item_id' => $sourceType === 'branch' ? ($item['source_item_id'] ?? null) : null,
                ]);
            })->values()->all();

            return $data;
        }

        $referenceId = (int) ($data['reference_id'] ?? 0);
        if ($referenceId <= 0) {
            throw ServiceException::validation('Dokumen asal wajib dipilih untuk sumber retur ini.');
        }

        $document = $this->findDocument($sourceType, $referenceId, $workLocationId);
        $summary = $this->documentSummary($document);
        $sourceItems = $this->documentSourceItems($document)->keyBy('id');
        $normalizedItems = [];
        $seenSourceItems = [];

        foreach ((array) $data['items'] as $item) {
            $sourceItemId = (int) ($item['source_item_id'] ?? 0);
            if (isset($seenSourceItems[$sourceItemId])) {
                throw ServiceException::validation('Item sumber tidak boleh dipilih lebih dari sekali dalam satu retur.');
            }
            $seenSourceItems[$sourceItemId] = true;
            $sourceItem = $sourceItems->get($sourceItemId);
            if (! $sourceItem instanceof Model) {
                throw ServiceException::validation('Item retur tidak berasal dari dokumen yang dipilih.');
            }

            $sourceItem = $this->reloadSourceItem($this->asSourceItem($sourceItem), $sourceItemId);
            $trusted = $this->sourceItemData($sourceItem, true, $excludeReturnId);
            $normalizedItems[] = array_replace($item, [
                'product_id' => $trusted['product_id'],
                'unit_id' => $trusted['unit_id'],
                'source_item_type' => $trusted['source_item_type'],
                'source_item_id' => $trusted['source_item_id'],
                'source_quantity' => $trusted['source_quantity'],
                'source_already_returned' => $trusted['already_returned'],
                'unit_cost_snapshot' => $trusted['unit_cost'],
                'conversion_factor_snapshot' => '1.000000',
            ]);
        }

        $data['source_id'] = $summary['party_id'];
        $data['source_name'] = $summary['party'];
        $data['reference_type'] = $summary['reference_type'];
        $data['reference_id'] = $summary['id'];
        $data['reference_no'] = $summary['number'];
        $data['items'] = $normalizedItems;

        return $data;
    }

    private function findDocument(string $sourceType, int $referenceId, int $workLocationId): PosSale|B2bOrder|GoodsReceipt|StockTransfer
    {
        $document = match ($sourceType) {
            'pos' => PosSale::query()->with(['items.product.baseUnit', 'items.warehouseLocation', 'customer', 'workLocation'])
                ->whereKey($referenceId)->where('work_location_id', $workLocationId)
                ->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])->first(),
            'b2b' => B2bOrder::query()->with(['items.product.baseUnit', 'customer', 'shipments' => fn ($query) => $query->where('origin_work_location_id', $workLocationId)->with('originWorkLocation')])
                ->whereKey($referenceId)
                ->whereIn('status', [B2bOrderStatus::RECEIVED->value, B2bOrderStatus::COMPLETED->value, B2bOrderStatus::RETURN_REQUESTED->value, B2bOrderStatus::RETURNED->value])
                ->whereHas('shipments', fn ($query) => $query->where('origin_work_location_id', $workLocationId))->first(),
            'supplier' => GoodsReceipt::query()->with(['items.product.baseUnit', 'items.warehouseLocation', 'supplier', 'warehouse.workLocation', 'purchaseOrder'])
                ->whereKey($referenceId)->where('status', GoodsReceiptStatus::POSTED->value)
                ->whereHas('warehouse', fn ($query) => $query->where('work_location_id', $workLocationId))->first(),
            'transfer' => StockTransfer::query()->with(['items.product.baseUnit', 'items.destinationWarehouseLocation', 'sourceWorkLocation', 'destinationWorkLocation'])
                ->whereKey($referenceId)->where('destination_work_location_id', $workLocationId)
                ->whereIn('status', [StockTransferStatus::PARTIALLY_RECEIVED->value, StockTransferStatus::FULLY_RECEIVED->value, StockTransferStatus::COMPLETED->value])->first(),
            default => null,
        };

        if (! $document instanceof PosSale && ! $document instanceof B2bOrder && ! $document instanceof GoodsReceipt && ! $document instanceof StockTransfer) {
            throw ServiceException::validation('Dokumen asal tidak ditemukan, belum selesai, atau berada di luar akses lokasi kerja Anda.');
        }

        return $document;
    }

    /** @return array<string, mixed> */
    private function documentSummary(PosSale|B2bOrder|GoodsReceipt|StockTransfer $document): array
    {
        $data = match (true) {
            $document instanceof PosSale => [
                'number' => $document->number,
                'date' => $this->formatDate($document->getAttribute('completed_at')) ?: $this->formatDate($document->getAttribute('created_at')),
                'party_id' => $document->customer_id,
                'party' => $document->customer?->business_name ?: 'Pelanggan Umum',
                'location' => $document->workLocation?->name,
                'status' => PosSaleStatus::from((string) $document->getRawOriginal('status'))->label(),
                'total' => (string) $document->grand_total_amount,
                'reference_type' => 'pos_sale',
            ],
            $document instanceof B2bOrder => [
                'number' => $document->number,
                'date' => $this->formatDate($document->getAttribute('created_at')),
                'party_id' => $document->customer_id,
                'party' => $document->customer?->business_name ?: 'Pelanggan B2B',
                'location' => $document->shipments->first()?->originWorkLocation?->name,
                'status' => B2bOrderStatus::from((string) $document->getRawOriginal('status'))->label(),
                'total' => (string) $document->grand_total_amount,
                'reference_type' => 'b2b_order',
            ],
            $document instanceof GoodsReceipt => [
                'number' => $document->number,
                'date' => $this->formatDate($document->getAttribute('received_at')),
                'party_id' => $document->supplier_id,
                'party' => $document->supplier?->name ?: 'Supplier',
                'location' => $document->warehouse?->name,
                'status' => GoodsReceiptStatus::from((string) $document->getRawOriginal('status'))->label(),
                'total' => (string) $document->purchaseOrder->grand_total,
                'reference_type' => 'goods_receipt',
            ],
            $document instanceof StockTransfer => [
                'number' => $document->number,
                'date' => $this->formatDate($document->getAttribute('transfer_date')),
                'party_id' => $document->source_work_location_id,
                'party' => $document->sourceWorkLocation?->name ?: 'Lokasi Asal',
                'location' => $document->destinationWorkLocation?->name,
                'status' => StockTransferStatus::from((string) $document->getRawOriginal('status'))->label(),
                'total' => null,
                'reference_type' => 'stock_transfer',
            ],
        };

        $data['id'] = $document->getKey();
        $data['item_count'] = (int) ($document->getAttribute('items_count') ?? $this->documentSourceItems($document)->count());
        $data['text'] = $data['number'].' - '.$data['party'].' - '.($data['date'] ?: '-');

        return $data;
    }

    /** @return array<string, mixed> */
    private function sourceItemData(PosSaleItem|B2bOrderItem|GoodsReceiptItem|StockTransferItem $sourceItem, bool $showCost, ?int $excludeReturnId): array
    {
        [$type, $sourceQuantity, $baselineReturned, $unitCost, $defaultLocationId, $warehouseLocationText] = match (true) {
            $sourceItem instanceof PosSaleItem => [
                'pos_sale_item',
                (string) $sourceItem->base_quantity,
                (string) $sourceItem->returned_quantity,
                (string) $sourceItem->hpp_snapshot,
                $sourceItem->warehouse_location_id,
                $sourceItem->warehouseLocation?->full_code,
            ],
            $sourceItem instanceof B2bOrderItem => [
                'b2b_order_item',
                Decimal::compare((string) $sourceItem->issued_quantity, '0') > 0 ? (string) $sourceItem->issued_quantity : (string) $sourceItem->base_quantity,
                '0.0000',
                (string) $sourceItem->product->cost_price,
                null,
                null,
            ],
            $sourceItem instanceof GoodsReceiptItem => [
                'goods_receipt_item',
                Decimal::add($sourceItem->acceptedBaseQuantity(), $sourceItem->damagedBaseQuantity()),
                Decimal::mul((string) $sourceItem->quantity_returned_to_supplier, (string) $sourceItem->conversion_factor_snapshot, 4, 4, 4),
                $this->goodsReceiptUnitCost($sourceItem),
                $sourceItem->warehouse_location_id,
                $sourceItem->warehouseLocation?->full_code,
            ],
            $sourceItem instanceof StockTransferItem => [
                'stock_transfer_item',
                Decimal::add((string) $sourceItem->quantity_received, (string) $sourceItem->quantity_damaged),
                '0.0000',
                (string) $sourceItem->product->cost_price,
                $sourceItem->destination_warehouse_location_id,
                $sourceItem->destinationWarehouseLocation?->full_code,
            ],
        };

        $genericReturned = ReturnItem::query()
            ->where('source_item_type', $type)
            ->where('source_item_id', $sourceItem->getKey())
            ->when($excludeReturnId !== null, fn ($query) => $query->where('return_id', '!=', $excludeReturnId))
            ->whereHas('returnDocument', fn ($query) => $query->whereNotIn('status', [ReturnStatus::REJECTED->value, ReturnStatus::CANCELLED->value]))
            ->sum('quantity_requested') ?: '0.0000';
        $alreadyReturned = Decimal::add($baselineReturned, (string) $genericReturned);
        $maximum = Decimal::compare($sourceQuantity, $alreadyReturned) > 0 ? Decimal::sub($sourceQuantity, $alreadyReturned) : '0.0000';
        $product = $sourceItem->product;

        return [
            'id' => $sourceItem->getKey(),
            'product_id' => $product->id,
            'unit_id' => $product->base_unit_id,
            'source_item_id' => $sourceItem->getKey(),
            'source_item_type' => $type,
            'text' => $product->sku.' - '.$product->name,
            'sku' => $product->sku,
            'name' => $product->name,
            'thumbnail' => $product->main_image_url,
            'unit' => $product->baseUnit?->name ?: 'Unit',
            'source_quantity' => $sourceQuantity,
            'already_returned' => $alreadyReturned,
            'maximum_quantity' => $maximum,
            'unit_cost' => $showCost ? $unitCost : null,
            'warehouse_location_id' => $defaultLocationId,
            'warehouse_location_text' => $warehouseLocationText,
        ];
    }

    private function asSourceDocument(Model $document): PosSale|B2bOrder|GoodsReceipt|StockTransfer
    {
        if ($document instanceof PosSale || $document instanceof B2bOrder || $document instanceof GoodsReceipt || $document instanceof StockTransfer) {
            return $document;
        }

        throw new LogicException('Model dokumen sumber retur tidak didukung.');
    }

    private function asSourceItem(Model $item): PosSaleItem|B2bOrderItem|GoodsReceiptItem|StockTransferItem
    {
        if ($item instanceof PosSaleItem || $item instanceof B2bOrderItem || $item instanceof GoodsReceiptItem || $item instanceof StockTransferItem) {
            return $item;
        }

        throw new LogicException('Model item sumber retur tidak didukung.');
    }

    /** @return Collection<int, PosSaleItem|B2bOrderItem|GoodsReceiptItem|StockTransferItem> */
    private function documentSourceItems(PosSale|B2bOrder|GoodsReceipt|StockTransfer $document): Collection
    {
        $items = match (true) {
            $document instanceof PosSale => $document->items,
            $document instanceof B2bOrder => $document->items,
            $document instanceof GoodsReceipt => $document->items,
            $document instanceof StockTransfer => $document->items,
        };

        return new Collection($items->all());
    }

    private function reloadSourceItem(PosSaleItem|B2bOrderItem|GoodsReceiptItem|StockTransferItem $item, int $sourceItemId): PosSaleItem|B2bOrderItem|GoodsReceiptItem|StockTransferItem
    {
        return match (true) {
            $item instanceof PosSaleItem => PosSaleItem::query()->with(['product.baseUnit', 'warehouseLocation'])->lockForUpdate()->findOrFail($sourceItemId),
            $item instanceof B2bOrderItem => B2bOrderItem::query()->with('product.baseUnit')->lockForUpdate()->findOrFail($sourceItemId),
            $item instanceof GoodsReceiptItem => GoodsReceiptItem::query()->with(['product.baseUnit', 'warehouseLocation'])->lockForUpdate()->findOrFail($sourceItemId),
            $item instanceof StockTransferItem => StockTransferItem::query()->with(['product.baseUnit', 'destinationWarehouseLocation'])->lockForUpdate()->findOrFail($sourceItemId),
        };
    }

    private function goodsReceiptUnitCost(GoodsReceiptItem $item): string
    {
        $baseQuantity = $item->acceptedBaseQuantity();
        if (Decimal::compare($baseQuantity, '0') > 0 && Decimal::compare((string) $item->incoming_cost, '0', 2) > 0) {
            return Decimal::div((string) $item->incoming_cost, $baseQuantity, 2, 2, 4);
        }

        return Decimal::compare((string) $item->hpp_after, '0', 2) > 0
            ? (string) $item->hpp_after
            : (string) $item->product->cost_price;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) && $value !== '' ? Carbon::parse($value)->format('Y-m-d') : null;
    }
}
