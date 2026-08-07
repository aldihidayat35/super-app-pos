<?php

namespace App\Services\Retail;

use App\Exceptions\ServiceException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\User;
use App\Services\Pricing\PriceResolverService;
use App\Support\CurrencyFormatter;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCatalogService
{
    public function __construct(private readonly PriceResolverService $prices) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{results: list<array<string, mixed>>, exact_match: bool, pagination: array{more: bool}}
     */
    public function search(Branch $branch, User $cashier, array $filters): array
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $customer = $this->customer($filters['customer_id'] ?? null);
        $exactBarcode = $search !== ''
            ? ProductBarcode::query()
                ->with('productUnit')
                ->where('code', $search)
                ->where('is_active', true)
                ->first()
            : null;

        if ($exactBarcode instanceof ProductBarcode) {
            $product = Product::query()
                ->with($this->productRelations((int) $branch->work_location_id))
                ->where('status', 'active')
                ->find($exactBarcode->product_id);
            if ($product instanceof Product) {
                $barcodeUnit = $exactBarcode->product_unit_id !== null ? $exactBarcode->productUnit : null;
                $unitId = $barcodeUnit instanceof ProductUnit
                    && (int) $barcodeUnit->product_id === (int) $product->id
                    && $barcodeUnit->is_active
                    && $barcodeUnit->is_sellable
                    ? (int) $barcodeUnit->unit_id
                    : (int) $product->base_unit_id;

                return [
                    'results' => [$this->productPayload($product, $branch, $cashier, $customer, $unitId)],
                    'exact_match' => true,
                    'pagination' => ['more' => false],
                ];
            }
        }

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 24), 12), 48);
        $query = Product::query()
            ->with($this->productRelations((int) $branch->work_location_id))
            ->where('status', 'active')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhereHas('barcodes', fn (Builder $barcode) => $barcode->where('is_active', true)->where('code', 'like', "%{$search}%"))))
            ->when(filled($filters['category_id'] ?? null), fn (Builder $query) => $query->where('category_id', $filters['category_id']))
            ->when(filled($filters['brand_id'] ?? null), fn (Builder $query) => $query->where('brand_id', $filters['brand_id']))
            ->when(filter_var($filters['in_stock'] ?? false, FILTER_VALIDATE_BOOL), fn (Builder $query) => $query->whereHas('stocks', fn (Builder $stock) => $stock
                ->where('work_location_id', $branch->work_location_id)
                ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_damaged) > 0')))
            ->orderBy('name');

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'results' => $products->getCollection()
                ->map(fn (Product $product): array => $this->productPayload($product, $branch, $cashier, $customer, (int) $product->base_unit_id))
                ->values()->all(),
            'exact_match' => false,
            'pagination' => ['more' => $products->hasMorePages()],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function quote(Branch $branch, User $cashier, array $data): array
    {
        $product = Product::query()
            ->with($this->productRelations((int) $branch->work_location_id))
            ->where('status', 'active')
            ->find($data['product_id']);
        if (! $product instanceof Product) {
            throw ServiceException::validation('Produk tidak aktif atau tidak ditemukan.');
        }

        return $this->productPayload(
            $product,
            $branch,
            $cashier,
            $this->customer($data['customer_id'] ?? null),
            isset($data['unit_id']) ? (int) $data['unit_id'] : (int) $product->base_unit_id,
            (string) ($data['quantity'] ?? 1),
            $data['selected_price'] ?? null,
            (string) ($data['discount_percent'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    private function productPayload(Product $product, Branch $branch, User $cashier, ?Customer $customer, int $unitId, string $quantity = '1', mixed $requestedPrice = null, string $discountPercent = '0'): array
    {
        $unit = $this->sellableUnit($product, $unitId);
        $pricing = $this->prices->resolve(
            $product,
            quantity: $quantity,
            unitId: $unit['id'],
            branch: $branch,
            customer: $customer,
            channel: 'pos',
            user: $cashier,
            requestedPrice: $requestedPrice,
            discountPercent: $discountPercent,
        );
        $stock = $this->defaultStock($product);
        $availableBase = $stock instanceof Stock ? $stock->available_quantity : '0.0000';
        $branchAvailableBase = $this->availableBaseQuantity($product);
        $availableUnit = Decimal::div($availableBase, $unit['factor'], 4, 6, 4);
        $requiredBase = (string) $pricing['quantity_base'];
        $status = $this->priceStatus($pricing);
        $showSensitive = $cashier->can('margins.view_sensitive');
        $pricingPayload = [
            'ring' => $pricing['price_ring'],
            'source' => $pricing['selected_source'],
            'reason' => $pricing['reason'],
            'recommended_price' => $pricing['recommended_price'],
            'minimum_price' => $pricing['minimum_price'],
            'maximum_price' => $pricing['maximum_price'],
            'selected_price' => $pricing['selected_price'],
            'discounted_price' => $pricing['discounted_price'],
            'discount_percent' => Decimal::normalize($discountPercent, 2),
            'approval_required' => $pricing['approval_required'],
            'approval_reasons' => $pricing['approval_reasons'],
            'status' => $status['code'],
            'status_label' => $status['label'],
            'status_message' => $status['message'],
            'rings' => $this->priceRings($pricing),
        ];
        if ($showSensitive) {
            $pricingPayload['hpp'] = $pricing['hpp_unit'];
            $pricingPayload['margin_amount'] = $pricing['margin_amount'];
            $pricingPayload['margin_percent'] = $pricing['margin_percent'];
        }

        return [
            'id' => $product->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'image_url' => $product->main_image_url,
            'category' => $product->category?->name,
            'brand' => $product->brand?->name,
            'unit_id' => $unit['id'],
            'unit' => $unit['name'],
            'unit_factor' => $unit['factor'],
            'units' => $this->unitOptions($product),
            'quantity' => Decimal::normalize($quantity),
            'stock_base' => $availableBase,
            'branch_stock_base' => $branchAvailableBase,
            'stock' => $availableUnit,
            'stock_sufficient' => Decimal::compare($availableBase, $requiredBase) >= 0,
            'stock_low' => Decimal::compare($availableBase, (string) $product->minimum_stock) <= 0,
            'warehouse_location_id' => $stock instanceof Stock ? $stock->warehouse_location_id : null,
            'pricing' => $pricingPayload,
        ];
    }

    /** @return array{id: int, name: string, factor: string} */
    private function sellableUnit(Product $product, int $unitId): array
    {
        if ((int) $product->base_unit_id === $unitId) {
            return ['id' => $unitId, 'name' => $product->baseUnit?->name ?: 'Unit', 'factor' => '1.000000'];
        }

        $productUnit = $product->units->first(fn (ProductUnit $unit): bool => (int) $unit->unit_id === $unitId && $unit->is_active && $unit->is_sellable);
        if (! $productUnit instanceof ProductUnit) {
            throw ServiceException::validation('Unit jual tidak tersedia untuk produk ini.');
        }

        return ['id' => (int) $productUnit->unit_id, 'name' => $productUnit->unit?->name ?: $productUnit->name, 'factor' => (string) $productUnit->conversion_factor];
    }

    /** @return list<array{id: int, text: string, factor: string}> */
    private function unitOptions(Product $product): array
    {
        $units = [[
            'id' => (int) $product->base_unit_id,
            'text' => $product->baseUnit?->name ?: 'Unit',
            'factor' => '1.000000',
        ]];

        foreach ($product->units as $productUnit) {
            if (! $productUnit->is_active || ! $productUnit->is_sellable || (int) $productUnit->unit_id === (int) $product->base_unit_id) {
                continue;
            }
            $units[] = [
                'id' => (int) $productUnit->unit_id,
                'text' => $productUnit->unit?->name ?: $productUnit->name,
                'factor' => (string) $productUnit->conversion_factor,
            ];
        }

        return $units;
    }

    private function availableBaseQuantity(Product $product): string
    {
        return $product->stocks->reduce(
            fn (string $carry, Stock $stock): string => Decimal::add($carry, $stock->available_quantity),
            '0.0000',
        );
    }

    private function defaultStock(Product $product): ?Stock
    {
        $stock = $product->stocks
            ->filter(fn (Stock $stock): bool => Decimal::compare($stock->available_quantity, '0') > 0)
            ->sort(function (Stock $left, Stock $right): int {
                $leftWithoutBin = $left->warehouse_location_id === null;
                $rightWithoutBin = $right->warehouse_location_id === null;
                if ($leftWithoutBin !== $rightWithoutBin) {
                    return $leftWithoutBin ? -1 : 1;
                }

                return Decimal::compare($right->available_quantity, $left->available_quantity);
            })
            ->first();

        return $stock instanceof Stock ? $stock : null;
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array{code: string, label: string, message: string}
     */
    private function priceStatus(array $pricing): array
    {
        $selected = (string) $pricing['discounted_price'];
        $minimum = (string) $pricing['minimum_price'];
        $maximum = (string) $pricing['maximum_price'];
        if (in_array('below_minimum', $pricing['approval_reasons'], true)) {
            return ['code' => 'below', 'label' => 'Di bawah batas', 'message' => CurrencyFormatter::rupiah(Decimal::sub($minimum, $selected, 2)).' di bawah batas minimum. Membutuhkan approval harga.'];
        }
        if (in_array('overpricing', $pricing['approval_reasons'], true)) {
            return ['code' => 'above', 'label' => 'Di atas batas', 'message' => CurrencyFormatter::rupiah(Decimal::sub((string) $pricing['selected_price'], $maximum, 2)).' di atas batas maksimum. Membutuhkan approval harga.'];
        }
        if ($pricing['approval_required'] === true) {
            return ['code' => 'approval', 'label' => 'Membutuhkan approval', 'message' => 'Diskon atau harga melanggar kebijakan yang aktif.'];
        }

        $distanceToMinimum = Decimal::sub($selected, $minimum, 2);
        $safeWidth = Decimal::sub($maximum, $minimum, 2);
        $nearThreshold = Decimal::mul($safeWidth, '0.10', 2, 2, 2);
        if (Decimal::compare($distanceToMinimum, $nearThreshold, 2) <= 0 || Decimal::compare(Decimal::sub($maximum, $selected, 2), $nearThreshold, 2) <= 0) {
            return ['code' => 'near', 'label' => 'Mendekati batas', 'message' => 'Harga masih di dalam pagar, tetapi dekat dengan salah satu batas.'];
        }

        return ['code' => 'safe', 'label' => 'Aman', 'message' => 'Harga berada di dalam pagar yang diizinkan.'];
    }

    private function customer(mixed $customerId): ?Customer
    {
        if (! filled($customerId)) {
            return null;
        }

        $customer = Customer::query()->where('is_active', true)->find($customerId);
        if (! $customer instanceof Customer) {
            throw ServiceException::validation('Pelanggan tidak aktif atau tidak ditemukan.');
        }

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return list<array{label: string, price: string, selected: bool}>
     */
    private function priceRings(array $pricing): array
    {
        $factor = (string) $pricing['unit_factor'];
        $selectedSource = (string) $pricing['selected_source'];

        return collect((array) $pricing['candidates'])
            ->map(function (mixed $candidate, int $index) use ($factor, $selectedSource): ?array {
                if (! is_array($candidate) || ! isset($candidate['price_base'])) {
                    return null;
                }

                $label = (string) ($candidate['ring_name'] ?? match ($candidate['source'] ?? null) {
                    'customer_special' => 'Harga Khusus',
                    'computed_minimum' => 'Harga POS',
                    default => 'Ring Harga',
                });

                return [
                    'label' => $label,
                    'price' => Decimal::mul((string) $candidate['price_base'], $factor, 2, 6, 2),
                    'selected' => $index === 0 && (string) ($candidate['source'] ?? '') === $selectedSource,
                ];
            })
            ->filter()
            ->unique(fn (array $ring): string => $ring['label'].'|'.$ring['price'])
            ->values()
            ->all();
    }

    /** @return array<int|string, mixed> */
    private function productRelations(int $workLocationId): array
    {
        return [
            'baseUnit',
            'category',
            'brand',
            'images',
            'units' => fn (HasMany $query) => $query->with('unit')->where('is_active', true)->where('is_sellable', true),
            'stocks' => fn (HasMany $query) => $query->where('work_location_id', $workLocationId),
        ];
    }
}
