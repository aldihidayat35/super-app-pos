<?php

namespace App\Services\Retail;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\PosHoldStatus;
use App\Enums\PosReturnStatus;
use App\Enums\PosSaleStatus;
use App\Exceptions\ServiceException;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\EmergencyPurchase;
use App\Models\EmergencyPurchaseItem;
use App\Models\PosHold;
use App\Models\PosReturn;
use App\Models\PosReturnItem;
use App\Models\PosSale;
use App\Models\PosSaleAllocation;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\SalePayment;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\DocumentNumberService;
use App\Services\Pricing\PriceResolverService;
use App\Services\Receivables\ReceivableService;
use App\Services\Tax\TaxComplianceService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class PosService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly PriceResolverService $prices,
        private readonly ReceivableService $receivables,
        private readonly TaxComplianceService $taxes,
        private readonly EmergencyStockService $emergencyStock,
    ) {}

    /** @param array<string, mixed> $data */
    public function checkout(array $data, User $cashier): PosSale
    {
        if (($data['idempotency_key'] ?? null) !== null) {
            $existing = PosSale::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing instanceof PosSale) {
                if ((int) $existing->cashier_user_id !== (int) $cashier->id) {
                    throw ServiceException::validation('Kunci idempotensi sudah digunakan oleh transaksi kasir lain.');
                }

                return $existing->load(['items.product', 'payments', 'branch', 'cashShift', 'customer']);
            }
        }

        return DB::transaction(function () use ($data, $cashier): PosSale {
            $branch = $this->branchForSale((int) $data['branch_id'], $cashier);
            $shift = $this->activeShift($branch, $cashier, true);
            $customer = isset($data['customer_id']) ? Customer::query()->where('is_active', true)->find($data['customer_id']) : null;
            if (filled($data['customer_id'] ?? null) && ! $customer instanceof Customer) {
                throw ServiceException::validation('Pelanggan tidak aktif atau tidak ditemukan.');
            }

            if (! $shift instanceof CashShift) {
                throw ServiceException::validation('Kasir belum memiliki shift aktif di cabang ini.');
            }

            $itemPayloads = $data['items'] ?? [];
            if (! is_array($itemPayloads) || $itemPayloads === []) {
                throw ServiceException::validation('Keranjang tidak boleh kosong.');
            }

            $calculated = $this->calculateItems($itemPayloads, $branch, $customer, $cashier);
            $emergencyPurchase = null;
            if (filled($data['emergency_purchase_id'] ?? null)) {
                $emergencyPurchase = EmergencyPurchase::query()->with('items')->whereKey($data['emergency_purchase_id'])->lockForUpdate()->firstOrFail();
                if ($emergencyPurchase->status !== 'purchased' || (int) $emergencyPurchase->branch_id !== (int) $branch->id
                    || (int) ($emergencyPurchase->customer_id ?? 0) !== (int) ($customer->id ?? 0)) {
                    throw ServiceException::validation('Pembelian darurat tidak siap atau tidak sesuai toko/pelanggan transaksi.');
                }
                $hasRequestedProduct = $emergencyPurchase->items->contains(fn (EmergencyPurchaseItem $requestedItem): bool => collect($calculated['items'])->contains(fn (array $item): bool => (int) $item['product']->id === (int) $requestedItem->product_id));
                if (! $hasRequestedProduct) {
                    throw ServiceException::validation('Keranjang POS harus memuat minimal satu produk dari permintaan darurat.');
                }
            }
            $allocationPlan = $this->planAllocations($calculated['items'], $branch, $emergencyPurchase);
            $approvalItems = collect($calculated['items'])->filter(fn (array $item): bool => $item['price']['approval_required'] === true);
            if ($approvalItems->isNotEmpty()) {
                $messages = $approvalItems->map(fn (array $item): string => $item['product']->name.' ('.implode(', ', $item['price']['approval_reasons']).')');
                throw ServiceException::validation('Harga/diskon membutuhkan approval dan belum dapat di-checkout: '.$messages->implode('; ').'.');
            }
            $payments = $this->validatePayments($data['payments'] ?? [], $calculated['grand_total'], $customer);
            if (Decimal::compare($payments['credit_amount'], '0', 2) > 0) {
                $this->receivables->assertCanUseCredit($this->requireCustomer($customer), $payments['credit_amount']);
            }
            $number = $this->numbers->next('sale', $branch->workLocation);

            $sale = PosSale::query()->create([
                'number' => $number,
                'branch_id' => $branch->id,
                'work_location_id' => $branch->work_location_id,
                'cash_shift_id' => $shift->id,
                'cashier_user_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'status' => PosSaleStatus::COMPLETED,
                'subtotal_amount' => $calculated['subtotal'],
                'discount_amount' => $calculated['discount'],
                'tax_amount' => $calculated['tax'],
                'grand_total_amount' => $calculated['grand_total'],
                'paid_amount' => $payments['paid'],
                'change_amount' => $payments['change'],
                'total_margin_amount' => $allocationPlan['margin'],
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'completed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($calculated['items'] as $index => $itemData) {
                /** @var Product $product */
                $product = $itemData['product'];
                $saleItem = PosSaleItem::query()->create([
                    'pos_sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'unit_id' => $itemData['unit']->id,
                    'warehouse_location_id' => $itemData['warehouse_location']?->id,
                    'sku_snapshot' => $product->sku,
                    'product_name_snapshot' => $product->name,
                    'unit_name_snapshot' => $itemData['unit']->name,
                    'conversion_factor_snapshot' => $itemData['unit_factor'],
                    'quantity' => $itemData['quantity'],
                    'base_quantity' => $itemData['base_quantity'],
                    'hpp_snapshot' => $itemData['price']['hpp_base'],
                    'minimum_price_snapshot' => $itemData['price']['minimum_price'],
                    'selected_price' => $itemData['selected_price'],
                    'discount_percent' => $itemData['discount_percent'],
                    'discount_amount' => $itemData['discount_amount'],
                    'tax_amount' => $itemData['tax_amount'],
                    'line_total' => $itemData['line_total'],
                    'margin_amount' => $allocationPlan['items'][$index]['margin'],
                    'price_source' => $itemData['price']['selected_source'],
                    'price_snapshot' => [...$itemData['price'], 'tax' => $itemData['tax_snapshot']],
                ]);

                foreach ($allocationPlan['items'][$index]['allocations'] as $allocation) {
                    PosSaleAllocation::query()->create([
                        'pos_sale_item_id' => $saleItem->id,
                        'emergency_purchase_item_id' => $allocation['emergency_item']?->id,
                        'source' => $allocation['source'], 'base_quantity' => $allocation['quantity'],
                        'normal_hpp_unit' => $itemData['price']['hpp_base'],
                        'actual_cost_unit' => $allocation['cost'],
                        'revenue_amount' => $allocation['revenue'],
                        'normal_cogs_amount' => $allocation['normal_cogs'],
                        'actual_cogs_amount' => $allocation['actual_cogs'],
                        'actual_margin_amount' => $allocation['margin'],
                        'lost_margin_amount' => $allocation['lost_margin'],
                    ]);
                    if ($allocation['emergency_item'] instanceof EmergencyPurchaseItem) {
                        $emergencyItem = $allocation['emergency_item'];
                        $emergencyItem->forceFill(['allocated_quantity' => Decimal::add((string) $emergencyItem->allocated_quantity, $allocation['quantity'], 4)])->save();
                        $targetItem = $allocation['target_item'] ?? null;
                        if ($targetItem instanceof EmergencyPurchaseItem) {
                            $targetItem->forceFill(['allocated_quantity' => Decimal::add((string) $targetItem->allocated_quantity, $allocation['quantity'], 4)])->save();
                        }
                    }
                }
                $normalQuantity = $allocationPlan['items'][$index]['normal_quantity'];
                if (Decimal::compare($normalQuantity, '0', 4) <= 0) {
                    continue;
                }
                $this->inventory->issue(
                    $product,
                    $branch->workLocation,
                    $itemData['warehouse_location'],
                    $normalQuantity,
                    $cashier,
                    ['type' => 'pos_sale', 'id' => $sale->id, 'no' => $sale->number],
                    'Penjualan POS.',
                    "pos-sale-{$sale->id}-item-{$saleItem->id}-issue",
                    ['pos_sale_item_id' => $saleItem->id],
                );
            }

            $this->finalizeEmergencyPurchases($emergencyPurchase, $allocationPlan['purchase_ids'], $sale, $cashier);

            foreach ($payments['rows'] as $payment) {
                SalePayment::query()->create([
                    'pos_sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference_no' => $payment['reference_no'] ?? null,
                    'notes' => $payment['notes'] ?? null,
                ]);
            }

            if (Decimal::compare($payments['net_cash_amount'], '0', 2) > 0) {
                $shift->forceFill([
                    'expected_cash_amount' => Decimal::add((string) $shift->expected_cash_amount, $payments['net_cash_amount'], 2),
                ])->save();
            }

            if (Decimal::compare($payments['credit_amount'], '0', 2) > 0) {
                $this->receivables->createFromPosSale($sale, $cashier);
            }

            return $sale->load(['items.product', 'payments', 'branch', 'cashShift', 'customer']);
        });
    }

    /** @param array<string, mixed> $data */
    public function hold(array $data, User $cashier): PosHold
    {
        return DB::transaction(function () use ($data, $cashier): PosHold {
            $branch = $this->branchForSale((int) $data['branch_id'], $cashier);
            $shift = $this->activeShift($branch, $cashier, true);
            if (! $shift instanceof CashShift) {
                throw ServiceException::validation('Kasir belum memiliki shift aktif di cabang ini.');
            }

            $customer = isset($data['customer_id']) ? Customer::query()->where('is_active', true)->find($data['customer_id']) : null;
            if (filled($data['customer_id'] ?? null) && ! $customer instanceof Customer) {
                throw ServiceException::validation('Pelanggan tidak aktif atau tidak ditemukan.');
            }

            if (isset($data['items']) && is_array($data['items']) && $data['items'] !== []) {
                $calculated = $this->calculateItems($data['items'], $branch, $customer, $cashier);
                $snapshot = [
                    'version' => 1,
                    'customer_id' => $customer?->id,
                    'items' => collect($calculated['items'])->map(fn (array $item): array => [
                        'product_id' => $item['product']->id,
                        'sku' => $item['product']->sku,
                        'name' => $item['product']->name,
                        'image_url' => $item['product']->main_image_url,
                        'unit_id' => $item['unit']->id,
                        'unit' => $item['unit']->name,
                        'warehouse_location_id' => $item['warehouse_location']?->id,
                        'quantity' => $item['quantity'],
                        'selected_price' => $item['selected_price'],
                        'discount_percent' => $item['discount_percent'],
                        'line_total' => $item['line_total'],
                        'pricing' => [
                            'ring' => $item['price']['price_ring'],
                            'source' => $item['price']['selected_source'],
                            'reason' => $item['price']['reason'],
                            'recommended_price' => $item['price']['recommended_price'],
                            'minimum_price' => $item['price']['minimum_price'],
                            'maximum_price' => $item['price']['maximum_price'],
                            'selected_price' => $item['price']['selected_price'],
                            'discounted_price' => $item['price']['discounted_price'],
                            'approval_required' => $item['price']['approval_required'],
                            'approval_reasons' => $item['price']['approval_reasons'],
                        ],
                    ])->values()->all(),
                    'totals' => [
                        'subtotal' => $calculated['subtotal'],
                        'discount' => $calculated['discount'],
                        'grand_total' => $calculated['grand_total'],
                    ],
                ];
                $estimatedTotal = $calculated['grand_total'];
            } else {
                // Jalur kompatibilitas untuk snapshot hold lama yang dibuat sebelum POS scanner-first.
                $snapshot = (array) ($data['cart_snapshot'] ?? []);
                $estimatedTotal = Decimal::normalize($data['estimated_total'] ?? 0, 2);
            }

            return PosHold::query()->create([
                'number' => $this->numbers->next('sale', $branch->workLocation).'-H',
                'branch_id' => $branch->id,
                'work_location_id' => $branch->work_location_id,
                'cash_shift_id' => $shift->id,
                'cashier_user_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'status' => PosHoldStatus::HELD,
                'cart_snapshot' => $snapshot,
                'estimated_total' => $estimatedTotal,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function resumeHold(PosHold $hold, User $cashier): PosHold
    {
        return DB::transaction(function () use ($hold, $cashier): PosHold {
            $hold = PosHold::query()->lockForUpdate()->findOrFail($hold->id);
            if ((int) $hold->cashier_user_id !== (int) $cashier->id) {
                throw ServiceException::validation('Hold hanya bisa dilanjutkan oleh kasir yang sama.');
            }

            if ($hold->status !== PosHoldStatus::HELD) {
                throw ServiceException::validation('Transaksi hold ini sudah dilanjutkan atau dibatalkan.');
            }
            $shift = CashShift::query()
                ->with('branch')
                ->whereKey($hold->cash_shift_id)
                ->where('cashier_user_id', $cashier->id)
                ->where('status', CashShiftStatus::OPEN->value)
                ->first();
            if (! $shift instanceof CashShift || ! $shift->branch instanceof Branch) {
                throw ServiceException::validation('Hold tidak dapat dilanjutkan karena shift asal sudah tidak aktif.');
            }

            $snapshot = $this->holdSnapshot($hold);
            if ((int) ($snapshot['version'] ?? 0) >= 1) {
                $customer = $hold->customer_id !== null
                    ? Customer::query()->where('is_active', true)->find($hold->customer_id)
                    : null;
                if ($hold->customer_id !== null && ! $customer instanceof Customer) {
                    throw ServiceException::validation('Pelanggan pada transaksi hold sudah tidak aktif.');
                }
                $this->calculateItems((array) ($snapshot['items'] ?? []), $shift->branch, $customer, $cashier);
            }

            $hold->forceFill(['status' => PosHoldStatus::RESUMED, 'resumed_at' => now()])->save();

            return $hold->fresh(['customer', 'branch']);
        });
    }

    public function cancelHold(PosHold $hold, User $cashier, string $reason): PosHold
    {
        if ((int) $hold->cashier_user_id !== (int) $cashier->id) {
            throw ServiceException::validation('Hold hanya bisa dibatalkan oleh kasir yang sama.');
        }

        if ($hold->status !== PosHoldStatus::HELD) {
            throw ServiceException::validation('Hanya transaksi hold aktif yang dapat dibatalkan.');
        }

        $hold->forceFill(['status' => PosHoldStatus::CANCELLED, 'cancel_reason' => $reason, 'cancelled_at' => now()])->save();

        return $hold->fresh(['customer', 'branch']);
    }

    public function voidSale(PosSale $sale, User $actor, string $reason): PosSale
    {
        return DB::transaction(function () use ($sale, $actor, $reason): PosSale {
            $sale = PosSale::query()->with(['items.product', 'items.allocations', 'branch.workLocation', 'cashShift'])->lockForUpdate()->findOrFail($sale->id);
            if ($sale->cashShift?->status->isLocked()) {
                throw ServiceException::validation('Transaksi pada shift yang sudah closing tidak dapat di-void. Gunakan workflow koreksi resmi.');
            }
            if (! $sale->status->canVoid()) {
                throw ServiceException::validation('Transaksi tidak dapat di-void pada status saat ini.');
            }

            foreach ($sale->items as $item) {
                $remaining = Decimal::sub((string) $item->base_quantity, (string) $item->returned_quantity);
                if (Decimal::compare($remaining, '0') <= 0) {
                    continue;
                }

                $normalRemaining = $item->allocations->isEmpty() ? $remaining : '0.0000';
                foreach ($item->allocations as $allocation) {
                    $open = Decimal::sub((string) $allocation->base_quantity, (string) $allocation->returned_quantity, 4);
                    if ($allocation->source === 'normal') {
                        $normalRemaining = Decimal::add($normalRemaining, $open, 4);
                    } elseif (Decimal::compare($open, '0', 4) > 0) {
                        $this->releaseEmergencyAllocation($allocation, $open, $actor, 'void');
                    }
                    $allocation->forceFill(['returned_quantity' => $allocation->base_quantity])->save();
                }
                if (Decimal::compare($normalRemaining, '0', 4) <= 0) {
                    continue;
                }
                $this->inventory->returnIn(
                    $item->product,
                    $sale->branch->workLocation,
                    $item->warehouseLocation,
                    $normalRemaining,
                    $actor,
                    ['type' => 'pos_sale_void', 'id' => $sale->id, 'no' => $sale->number],
                    $reason,
                    "pos-sale-{$sale->id}-item-{$item->id}-void",
                );
            }

            $sale->forceFill([
                'status' => PosSaleStatus::VOID_APPROVED,
                'void_requested_by' => $actor->id,
                'void_approved_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            return $sale->fresh(['items.product', 'payments', 'branch', 'cashShift', 'customer']);
        });
    }

    /** @param array<string, mixed> $data */
    public function returnSale(PosSale $sale, array $data, User $actor): PosReturn
    {
        return DB::transaction(function () use ($sale, $data, $actor): PosReturn {
            $sale = PosSale::query()->with(['items.product', 'items.allocations', 'branch.workLocation', 'cashShift'])->lockForUpdate()->findOrFail($sale->id);
            if ($sale->cashShift?->status->isLocked()) {
                throw ServiceException::validation('Transaksi pada shift yang sudah closing tidak dapat diretur. Gunakan workflow koreksi resmi.');
            }
            if (! in_array($sale->status, [PosSaleStatus::COMPLETED, PosSaleStatus::RETURNED], true)) {
                throw ServiceException::validation('Transaksi tidak dapat diretur pada status saat ini.');
            }

            $refundAmount = '0.00';
            $reversedMarginTotal = '0.00';
            $return = PosReturn::query()->create([
                'number' => $this->numbers->next('return', $sale->branch->workLocation),
                'pos_sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'work_location_id' => $sale->work_location_id,
                'cashier_user_id' => $actor->id,
                'status' => PosReturnStatus::COMPLETED,
                'resolution' => $data['resolution'] ?? 'refund',
                'refund_method' => $data['refund_method'] ?? 'cash',
                'refund_amount' => 0,
                'reason' => $data['reason'] ?? null,
                'completed_at' => now(),
            ]);

            foreach ($data['items'] ?? [] as $itemData) {
                $item = $sale->items->firstWhere('id', (int) $itemData['pos_sale_item_id']);
                if (! $item instanceof PosSaleItem) {
                    throw ServiceException::validation('Item retur tidak valid.');
                }

                $quantity = Decimal::normalize($itemData['quantity'], 4);
                if (Decimal::compare($quantity, '0') <= 0) {
                    continue;
                }

                $remaining = Decimal::sub((string) $item->base_quantity, (string) $item->returned_quantity);
                if (Decimal::compare($quantity, $remaining) > 0) {
                    throw ServiceException::validation('Qty retur melebihi sisa item yang bisa diretur.');
                }

                $unitRefund = Decimal::div((string) $item->line_total, (string) $item->base_quantity, 2, 4, 2);
                $lineRefund = Decimal::mul($quantity, $unitRefund, 4, 2, 2);
                $condition = $itemData['condition'] ?? 'good';
                $refundAmount = Decimal::add($refundAmount, $lineRefund, 2);

                $normalQuantity = Decimal::normalize((string) ($itemData['normal_quantity'] ?? $quantity), 4);
                $emergencyQuantity = Decimal::normalize((string) ($itemData['emergency_quantity'] ?? '0'), 4);
                if ($item->allocations->contains(fn (PosSaleAllocation $row): bool => $row->source === 'emergency')
                    && (! array_key_exists('normal_quantity', $itemData) || ! array_key_exists('emergency_quantity', $itemData))) {
                    throw ServiceException::validation('Pilih qty normal dan darurat secara terpisah pada retur campuran.');
                }
                if (Decimal::compare(Decimal::add($normalQuantity, $emergencyQuantity, 4), $quantity, 4) !== 0) {
                    throw ServiceException::validation('Jumlah qty normal dan darurat harus sama dengan qty retur.');
                }
                $reversedCogs = '0.00';
                $reversedRevenue = '0.00';
                foreach (['normal' => $normalQuantity, 'emergency' => $emergencyQuantity] as $source => $sourceQty) {
                    if (Decimal::compare($sourceQty, '0', 4) <= 0) {
                        continue;
                    }
                    $allocation = $item->allocations->firstWhere('source', $source);
                    if ($item->allocations->isEmpty() && $source === 'normal') {
                        $reversedCogs = Decimal::add($reversedCogs, Decimal::mul($sourceQty, (string) $item->hpp_snapshot, 4, 2, 2), 2);
                        $reversedRevenue = Decimal::add($reversedRevenue, Decimal::sub($lineRefund, Decimal::mul($sourceQty, Decimal::div((string) $item->tax_amount, (string) $item->base_quantity, 2, 4, 2), 4, 2, 2), 2), 2);

                        continue;
                    }
                    if (! $allocation instanceof PosSaleAllocation || Decimal::compare($sourceQty,
                        Decimal::sub((string) $allocation->base_quantity, (string) $allocation->returned_quantity, 4), 4) > 0) {
                        throw ServiceException::validation('Qty retur '.$source.' melebihi sisa alokasi asli.');
                    }
                    $reversedCogs = Decimal::add($reversedCogs, Decimal::mul($sourceQty, (string) $allocation->actual_cost_unit, 4, 2, 2), 2);
                    $reversedRevenue = Decimal::add($reversedRevenue, Decimal::mul($sourceQty,
                        Decimal::div((string) $allocation->revenue_amount, (string) $allocation->base_quantity, 2, 4, 2), 4, 2, 2), 2);
                    $allocation->forceFill(['returned_quantity' => Decimal::add((string) $allocation->returned_quantity, $sourceQty, 4)])->save();
                    if ($source === 'emergency') {
                        $this->releaseEmergencyAllocation($allocation, $sourceQty, $actor, 'return');
                    }
                }
                $reversedMargin = Decimal::sub($reversedRevenue, $reversedCogs, 2);
                $reversedMarginTotal = Decimal::add($reversedMarginTotal, $reversedMargin, 2);

                PosReturnItem::query()->create([
                    'pos_return_id' => $return->id,
                    'pos_sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'warehouse_location_id' => $item->warehouse_location_id,
                    'quantity' => $quantity,
                    'condition' => $condition,
                    'refund_amount' => $lineRefund,
                    'normal_quantity' => $normalQuantity,
                    'emergency_quantity' => $emergencyQuantity,
                    'reversed_cogs_amount' => $reversedCogs,
                    'reversed_margin_amount' => $reversedMargin,
                    'reason' => $itemData['reason'] ?? null,
                ]);

                if (Decimal::compare($normalQuantity, '0', 4) > 0) {
                    $this->inventory->returnIn(
                        $item->product,
                        $sale->branch->workLocation,
                        $item->warehouseLocation,
                        $normalQuantity,
                        $actor,
                        ['type' => 'pos_return', 'id' => $return->id, 'no' => $return->number],
                        $data['reason'] ?? 'Retur pelanggan POS.',
                        "pos-return-{$return->id}-item-{$item->id}-in",
                    );
                }

                if ($condition === 'damaged' && Decimal::compare($normalQuantity, '0', 4) > 0) {
                    $this->inventory->damage(
                        $item->product,
                        $sale->branch->workLocation,
                        $item->warehouseLocation,
                        $normalQuantity,
                        $actor,
                        ['type' => 'pos_return', 'id' => $return->id, 'no' => $return->number],
                        'Retur POS masuk stok rusak.',
                        "pos-return-{$return->id}-item-{$item->id}-damage",
                    );
                }

                $item->forceFill([
                    'returned_quantity' => Decimal::add((string) $item->returned_quantity, $quantity),
                    'margin_amount' => Decimal::sub((string) $item->margin_amount, $reversedMargin, 2),
                ])->save();
            }

            if (Decimal::compare($refundAmount, '0', 2) <= 0) {
                throw ServiceException::validation('Minimal satu item retur harus memiliki qty lebih besar dari nol.');
            }

            $return->forceFill(['refund_amount' => $refundAmount])->save();
            $sale->forceFill(['status' => PosSaleStatus::RETURNED,
                'total_margin_amount' => Decimal::sub((string) $sale->total_margin_amount, $reversedMarginTotal, 2)])->save();

            return $return->fresh(['items.product', 'sale']);
        });
    }

    private function activeShift(Branch $branch, User $cashier, bool $lock = false): ?CashShift
    {
        $query = CashShift::query()
            ->where('branch_id', $branch->id)
            ->where('cashier_user_id', $cashier->id)
            ->where('status', CashShiftStatus::OPEN->value);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function branchForSale(int $branchId, User $cashier): Branch
    {
        $branch = Branch::query()->with('workLocation')->where('is_active', true)->findOrFail($branchId);
        if ($branch->work_location_id === null || ! $cashier->canAccessWorkLocation((int) $branch->work_location_id)) {
            throw ServiceException::validation('Anda tidak memiliki akses ke cabang ini.');
        }

        return $branch;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *     margin: string,
     *     items: array<int, array{margin: string, normal_quantity: string, allocations: list<array<string, mixed>>}>,
     *     purchase_ids: list<int>
     * }
     */
    private function planAllocations(array $items, Branch $branch, ?EmergencyPurchase $purchase): array
    {
        $productIds = collect($items)->map(fn (array $item): int => (int) $item['product']->id)->unique()->values()->all();
        $boundByProduct = [];
        if ($purchase instanceof EmergencyPurchase) {
            $boundItems = EmergencyPurchaseItem::query()
                ->where('emergency_purchase_id', $purchase->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $purchase->setRelation('items', $boundItems);
            foreach ($boundItems as $targetItem) {
                $sourceItem = $targetItem;
                $remaining = $this->emergencyStock->remaining($targetItem);
                if ($targetItem->assigned_source_item_id) {
                    $sourceItem = EmergencyPurchaseItem::query()->lockForUpdate()->findOrFail($targetItem->assigned_source_item_id);
                    $assignedRemaining = Decimal::sub((string) $targetItem->assigned_quantity, (string) $targetItem->allocated_quantity, 4);
                    $sourceRemaining = $this->emergencyStock->remaining($sourceItem);
                    $remaining = Decimal::compare($sourceRemaining, $assignedRemaining, 4) < 0 ? $sourceRemaining : $assignedRemaining;
                }
                if (Decimal::compare($remaining, '0', 4) > 0) {
                    $boundByProduct[$targetItem->product_id][] = [
                        'item' => $sourceItem,
                        'target' => $targetItem->assigned_source_item_id ? $targetItem : null,
                        'remaining' => $remaining,
                    ];
                }
            }
        }

        $sharedItems = $this->emergencyStock->lockSharedLots($branch, $productIds);
        $reservations = $this->emergencyStock->pendingAssignmentsFor($sharedItems->pluck('id')->all());
        $sharedByProduct = [];
        foreach ($sharedItems as $sharedItem) {
            $remaining = Decimal::sub(
                $this->emergencyStock->remaining($sharedItem),
                $reservations[$sharedItem->id] ?? '0.0000',
                4,
            );
            if (Decimal::compare($remaining, '0', 4) > 0) {
                $sharedByProduct[$sharedItem->product_id][] = [
                    'item' => $sharedItem,
                    'target' => null,
                    'remaining' => $remaining,
                ];
            }
        }

        $stockBudget = [];
        $plan = [];
        $marginTotal = '0.00';
        $purchaseIds = [];
        foreach ($items as $index => $item) {
            $productId = (int) $item['product']->id;
            $stockKey = $productId.':'.($item['warehouse_location']->id ?? 'none');
            if (! isset($stockBudget[$stockKey])) {
                Stock::query()->firstOrCreate(
                    ['product_id' => $productId,
                        'location_scope_key' => 'work:'.$branch->work_location_id.'|bin:'.($item['warehouse_location']->id ?? 'none')],
                    ['work_location_id' => $branch->work_location_id,
                        'warehouse_location_id' => $item['warehouse_location']->id ?? null,
                        'quantity_on_hand' => '0.0000', 'quantity_reserved' => '0.0000',
                        'quantity_damaged' => '0.0000', 'cost_value' => '0.00'],
                );
                $stocks = Stock::query()->where('product_id', $productId)
                    ->where('work_location_id', $branch->work_location_id)
                    ->where('warehouse_location_id', $item['warehouse_location']?->id)
                    ->lockForUpdate()->get();
                $stockBudget[$stockKey] = $stocks->reduce(
                    fn (string $sum, Stock $stock): string => Decimal::add($sum, $stock->available_quantity, 4),
                    '0.0000',
                );
            }

            $quantity = (string) $item['base_quantity'];
            $normal = Decimal::compare($stockBudget[$stockKey], $quantity, 4) >= 0 ? $quantity : $stockBudget[$stockKey];
            if (Decimal::compare($normal, '0', 4) < 0) {
                $normal = '0.0000';
            }
            $stockBudget[$stockKey] = Decimal::sub($stockBudget[$stockKey], $normal, 4);
            $needed = Decimal::sub($quantity, $normal, 4);
            $pieces = [];

            foreach (['boundByProduct', 'sharedByProduct'] as $bucketName) {
                $bucket = $bucketName === 'boundByProduct' ? $boundByProduct : $sharedByProduct;
                foreach ($bucket[$productId] ?? [] as $candidateIndex => $candidate) {
                    if (Decimal::compare($needed, '0', 4) <= 0) {
                        break;
                    }
                    if (Decimal::compare($candidate['remaining'], '0', 4) <= 0) {
                        continue;
                    }
                    $taken = Decimal::compare($candidate['remaining'], $needed, 4) <= 0 ? $candidate['remaining'] : $needed;
                    $pieces[] = ['source' => 'emergency', 'quantity' => $taken,
                        'emergency_item' => $candidate['item'], 'target_item' => $candidate['target']];
                    $candidate['remaining'] = Decimal::sub($candidate['remaining'], $taken, 4);
                    $needed = Decimal::sub($needed, $taken, 4);
                    $purchaseIds[(int) $candidate['item']->emergency_purchase_id] = true;
                    if ($bucketName === 'boundByProduct') {
                        $boundByProduct[$productId][$candidateIndex] = $candidate;
                    } else {
                        $sharedByProduct[$productId][$candidateIndex] = $candidate;
                    }
                }
            }

            if (Decimal::compare($needed, '0', 4) > 0) {
                throw ServiceException::validation('Stok tersedia tidak mencukupi untuk '.$item['product']->name.'; kekurangan melampaui barang darurat yang tersedia.');
            }

            $rawAllocations = [];
            if (Decimal::compare($normal, '0', 4) > 0) {
                $rawAllocations[] = ['source' => 'normal', 'quantity' => $normal,
                    'emergency_item' => null, 'target_item' => null];
            }
            array_push($rawAllocations, ...$pieces);
            $netRevenue = Decimal::mul($item['quantity'], (string) $item['price']['discounted_price'], 4, 2, 2);
            $allocatedRevenue = '0.00';
            $allocations = [];
            $lastIndex = count($rawAllocations) - 1;
            foreach ($rawAllocations as $allocationIndex => $allocation) {
                $allocatedQty = $allocation['quantity'];
                $revenue = $allocationIndex === $lastIndex
                    ? Decimal::sub($netRevenue, $allocatedRevenue, 2)
                    : Decimal::mul($allocatedQty, Decimal::div($netRevenue, $quantity, 2, 4, 2), 4, 2, 2);
                $allocatedRevenue = Decimal::add($allocatedRevenue, $revenue, 2);
                $hpp = (string) $item['price']['hpp_base'];
                $cost = $allocation['source'] === 'emergency'
                    ? (string) $allocation['emergency_item']->unit_cost
                    : $hpp;
                $normalCogs = Decimal::mul($allocatedQty, $hpp, 4, 2, 2);
                $actualCogs = Decimal::mul($allocatedQty, $cost, 4, 2, 2);
                $margin = Decimal::sub($revenue, $actualCogs, 2);
                $allocations[] = [...$allocation, 'cost' => $cost, 'revenue' => $revenue,
                    'normal_cogs' => $normalCogs, 'actual_cogs' => $actualCogs, 'margin' => $margin,
                    'lost_margin' => Decimal::sub($actualCogs, $normalCogs, 2)];
            }
            $lineMargin = array_reduce($allocations,
                fn (string $sum, array $allocation): string => Decimal::add($sum, $allocation['margin'], 2),
                '0.00');
            $marginTotal = Decimal::add($marginTotal, $lineMargin, 2);
            $plan[$index] = ['margin' => $lineMargin, 'normal_quantity' => $normal, 'allocations' => $allocations];
        }

        return ['margin' => $marginTotal, 'items' => $plan, 'purchase_ids' => array_map('intval', array_keys($purchaseIds))];
    }

    /** @param list<int> $usedPurchaseIds */
    private function finalizeEmergencyPurchases(?EmergencyPurchase $boundPurchase, array $usedPurchaseIds, PosSale $sale, User $cashier): void
    {
        $ids = array_values(array_unique([...$usedPurchaseIds, ...($boundPurchase ? [(int) $boundPurchase->id] : [])]));
        foreach ($ids as $purchaseId) {
            $purchase = EmergencyPurchase::query()->with('items')->lockForUpdate()->findOrFail($purchaseId);
            $oldStatus = $purchase->status;
            if ($boundPurchase && (int) $purchase->id === (int) $boundPurchase->id && $purchase->fund_source === 'reallocated') {
                foreach ($purchase->items as $targetItem) {
                    if ($targetItem->assigned_source_item_id) {
                        $targetItem->forceFill(['assigned_quantity' => $targetItem->allocated_quantity])->save();
                    }
                }
                $nextStatus = 'completed';
            } else {
                $hasRemaining = $purchase->items->contains(fn (EmergencyPurchaseItem $item): bool => Decimal::compare($this->emergencyStock->remaining($item), '0', 4) > 0);
                $nextStatus = $hasRemaining ? 'unallocated' : 'completed';
            }
            $purchase->forceFill(['status' => $nextStatus])->save();
            $purchase->histories()->create([
                'actor_id' => $cashier->id,
                'action' => $boundPurchase && (int) $purchase->id === (int) $boundPurchase->id ? 'checkout' : 'pool_checkout',
                'from_status' => $oldStatus,
                'to_status' => $nextStatus,
                'notes' => $sale->number,
            ]);
        }
    }

    private function releaseEmergencyAllocation(PosSaleAllocation $allocation, string $quantity, User $actor, string $action): void
    {
        $item = EmergencyPurchaseItem::query()->lockForUpdate()->findOrFail($allocation->emergency_purchase_item_id);
        $item->forceFill(['allocated_quantity' => Decimal::sub((string) $item->allocated_quantity, $quantity, 4)])->save();
        $purchase = EmergencyPurchase::query()->lockForUpdate()->findOrFail($item->emergency_purchase_id);
        $old = $purchase->status;
        $purchase->forceFill(['status' => 'unallocated'])->save();
        $purchase->histories()->create(['actor_id' => $actor->id, 'action' => $action.'_unallocated',
            'from_status' => $old, 'to_status' => 'unallocated', 'notes' => 'Barang darurat tidak masuk stok reguler.']);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{subtotal: string, discount: string, tax: string, grand_total: string, margin: string, items: list<array<string, mixed>>}
     */
    private function calculateItems(array $items, Branch $branch, ?Customer $customer, User $cashier): array
    {
        $subtotal = '0.00';
        $discountTotal = '0.00';
        $taxTotal = '0.00';
        $grandTotal = '0.00';
        $marginTotal = '0.00';
        $rows = [];

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $product = Product::query()->with(['baseUnit', 'units.unit'])->where('status', 'active')->findOrFail($row['product_id']);
            $unitId = isset($row['unit_id']) ? (int) $row['unit_id'] : (int) $product->base_unit_id;
            $unit = Unit::query()->where('is_active', true)->findOrFail($unitId);
            if ($unitId !== (int) $product->base_unit_id && ! $product->units->contains(fn ($productUnit): bool => (int) $productUnit->unit_id === $unitId && $productUnit->is_active && $productUnit->is_sellable)) {
                throw ServiceException::validation('Unit '.$unit->name.' tidak tersedia sebagai unit jual produk '.$product->name.'.');
            }
            $warehouseLocation = null;
            if (filled($row['warehouse_location_id'] ?? null)) {
                $warehouseLocation = WarehouseLocation::query()->where('is_active', true)->find($row['warehouse_location_id']);
                $validStockLocation = $warehouseLocation instanceof WarehouseLocation && Stock::query()
                    ->where('product_id', $product->id)
                    ->where('work_location_id', $branch->work_location_id)
                    ->where('warehouse_location_id', $warehouseLocation->id)
                    ->exists();
                if (! $validStockLocation) {
                    throw ServiceException::validation('Lokasi stok produk '.$product->name.' tidak sesuai dengan cabang shift aktif.');
                }
            }
            $quantity = Decimal::normalize($row['quantity'] ?? 1, 4);
            $discountPercent = Decimal::normalize($row['discount_percent'] ?? 0, 2);
            $price = $this->prices->resolve(
                $product,
                quantity: $quantity,
                unitId: $unitId,
                branch: $branch,
                customer: $customer,
                channel: 'pos',
                user: $cashier,
                requestedPrice: $row['selected_price'] ?? null,
                discountPercent: $discountPercent,
            );

            $selectedPrice = (string) $price['selected_price'];
            $discountedPrice = (string) $price['discounted_price'];
            $baseQuantity = (string) $price['quantity_base'];
            $lineSubtotal = Decimal::mul($quantity, $selectedPrice, 4, 2, 2);
            $lineNet = Decimal::mul($quantity, $discountedPrice, 4, 2, 2);
            $discountAmount = Decimal::sub($lineSubtotal, $lineNet, 2);
            $tax = $this->taxes->calculate($product, $lineNet);
            $lineTotal = $tax['total_amount'];
            $marginAmount = Decimal::mul($quantity, (string) $price['margin_amount'], 4, 2, 2);

            $subtotal = Decimal::add($subtotal, $lineSubtotal, 2);
            $discountTotal = Decimal::add($discountTotal, $discountAmount, 2);
            $taxTotal = Decimal::add($taxTotal, Decimal::add($tax['tax_amount'], $tax['luxury_tax_amount'], 2), 2);
            $grandTotal = Decimal::add($grandTotal, $lineTotal, 2);
            $marginTotal = Decimal::add($marginTotal, $marginAmount, 2);

            $rows[] = [
                'product' => $product,
                'unit' => $unit,
                'warehouse_location' => $warehouseLocation,
                'quantity' => $quantity,
                'unit_factor' => $price['unit_factor'],
                'base_quantity' => $baseQuantity,
                'price' => $price,
                'selected_price' => $selectedPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax_amount' => $tax['tax_amount'],
                'tax_snapshot' => [
                    'rule_id' => $tax['rule']?->id,
                    'rate' => $tax['tax_rate'],
                    'dpp_factor' => $tax['dpp_factor'],
                    'dpp_amount' => $tax['dpp_amount'],
                    'luxury_tax_amount' => $tax['luxury_tax_amount'],
                ],
                'line_total' => $lineTotal,
                'margin_amount' => $marginAmount,
            ];
        }

        return ['subtotal' => $subtotal, 'discount' => $discountTotal, 'tax' => $taxTotal, 'grand_total' => $grandTotal, 'margin' => $marginTotal, 'items' => $rows];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, paid: string, change: string, cash_amount: string, net_cash_amount: string, credit_amount: string}
     */
    private function validatePayments(mixed $payments, string $grandTotal, ?Customer $customer): array
    {
        if (! is_array($payments) || $payments === []) {
            throw ServiceException::validation('Minimal satu metode pembayaran wajib diisi.');
        }

        $paid = '0.00';
        $cash = '0.00';
        $credit = '0.00';
        $rows = [];

        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }
            $method = (string) ($payment['method'] ?? '');
            if (! in_array($method, array_keys(PaymentMethod::options()), true)) {
                throw ServiceException::validation('Metode pembayaran tidak valid.');
            }
            $amount = Decimal::normalize($payment['amount'] ?? 0, 2);
            if (Decimal::compare($amount, '0', 2) <= 0) {
                throw ServiceException::validation('Nominal pembayaran harus lebih besar dari nol.');
            }
            $paid = Decimal::add($paid, $amount, 2);
            if ($method === PaymentMethod::CASH->value) {
                $cash = Decimal::add($cash, $amount, 2);
            }
            if ($method === PaymentMethod::CREDIT->value) {
                if (! $customer instanceof Customer) {
                    throw ServiceException::validation('Pelanggan wajib dipilih untuk transaksi POS kredit.');
                }
                $credit = Decimal::add($credit, $amount, 2);
            }
            $rows[] = [...$payment, 'method' => $method, 'amount' => $amount];
        }

        if (Decimal::compare($paid, $grandTotal, 2) < 0) {
            throw ServiceException::validation('Total pembayaran kurang dari grand total.');
        }

        $change = Decimal::sub($paid, $grandTotal, 2);
        if (Decimal::compare($change, $cash, 2) > 0) {
            throw ServiceException::validation('Pembayaran non-tunai tidak boleh melebihi total transaksi. Sesuaikan nominal split payment.');
        }

        return ['rows' => $rows, 'paid' => $paid, 'change' => $change, 'cash_amount' => $cash, 'net_cash_amount' => Decimal::sub($cash, $change, 2), 'credit_amount' => $credit];
    }

    private function requireCustomer(?Customer $customer): Customer
    {
        if (! $customer instanceof Customer) {
            throw ServiceException::validation('Pelanggan wajib dipilih untuk transaksi kredit.');
        }

        return $customer;
    }

    /** @return array<string, mixed> */
    private function holdSnapshot(PosHold $hold): array
    {
        $snapshot = $hold->getAttribute('cart_snapshot');

        return is_array($snapshot) ? $snapshot : [];
    }
}
