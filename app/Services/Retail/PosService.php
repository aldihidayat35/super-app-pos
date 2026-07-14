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
use App\Models\PosHold;
use App\Models\PosReturn;
use App\Models\PosReturnItem;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\SalePayment;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\DocumentNumberService;
use App\Services\Pricing\PriceResolverService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class PosService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly PriceResolverService $prices,
    ) {}

    /** @param array<string, mixed> $data */
    public function checkout(array $data, User $cashier): PosSale
    {
        if (($data['idempotency_key'] ?? null) !== null) {
            $existing = PosSale::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing instanceof PosSale) {
                return $existing->load(['items.product', 'payments', 'branch', 'cashShift', 'customer']);
            }
        }

        return DB::transaction(function () use ($data, $cashier): PosSale {
            $branch = $this->branchForSale((int) $data['branch_id'], $cashier);
            $shift = $this->activeShift($branch, $cashier, true);
            $customer = isset($data['customer_id']) ? Customer::query()->find($data['customer_id']) : null;

            if (! $shift instanceof CashShift) {
                throw ServiceException::validation('Kasir belum memiliki shift aktif di cabang ini.');
            }

            $itemPayloads = $data['items'] ?? [];
            if (! is_array($itemPayloads) || $itemPayloads === []) {
                throw ServiceException::validation('Keranjang tidak boleh kosong.');
            }

            $calculated = $this->calculateItems($itemPayloads, $branch, $customer, $cashier);
            $payments = $this->validatePayments($data['payments'] ?? [], $calculated['grand_total']);
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
                'tax_amount' => '0.00',
                'grand_total_amount' => $calculated['grand_total'],
                'paid_amount' => $payments['paid'],
                'change_amount' => $payments['change'],
                'total_margin_amount' => $calculated['margin'],
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'completed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($calculated['items'] as $itemData) {
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
                    'tax_amount' => '0.00',
                    'line_total' => $itemData['line_total'],
                    'margin_amount' => $itemData['margin_amount'],
                    'price_source' => $itemData['price']['selected_source'],
                    'price_snapshot' => $itemData['price'],
                ]);

                $this->inventory->issue(
                    $product,
                    $branch->workLocation,
                    $itemData['warehouse_location'],
                    $itemData['base_quantity'],
                    $cashier,
                    ['type' => 'pos_sale', 'id' => $sale->id, 'no' => $sale->number],
                    'Penjualan POS.',
                    "pos-sale-{$sale->id}-item-{$saleItem->id}-issue",
                    ['pos_sale_item_id' => $saleItem->id],
                );
            }

            foreach ($payments['rows'] as $payment) {
                SalePayment::query()->create([
                    'pos_sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference_no' => $payment['reference_no'] ?? null,
                    'notes' => $payment['notes'] ?? null,
                ]);
            }

            if (Decimal::compare($payments['cash_amount'], '0', 2) > 0) {
                $shift->forceFill([
                    'expected_cash_amount' => Decimal::add((string) $shift->expected_cash_amount, $payments['cash_amount'], 2),
                ])->save();
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

            return PosHold::query()->create([
                'number' => $this->numbers->next('sale', $branch->workLocation).'-H',
                'branch_id' => $branch->id,
                'work_location_id' => $branch->work_location_id,
                'cash_shift_id' => $shift->id,
                'cashier_user_id' => $cashier->id,
                'customer_id' => $data['customer_id'] ?? null,
                'status' => PosHoldStatus::HELD,
                'cart_snapshot' => $data['cart_snapshot'] ?? [],
                'estimated_total' => $data['estimated_total'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function resumeHold(PosHold $hold, User $cashier): PosHold
    {
        if ((int) $hold->cashier_user_id !== (int) $cashier->id) {
            throw ServiceException::validation('Hold hanya bisa dilanjutkan oleh kasir yang sama.');
        }

        $hold->forceFill(['status' => PosHoldStatus::RESUMED, 'resumed_at' => now()])->save();

        return $hold->fresh(['customer', 'branch']);
    }

    public function cancelHold(PosHold $hold, User $cashier, string $reason): PosHold
    {
        if ((int) $hold->cashier_user_id !== (int) $cashier->id) {
            throw ServiceException::validation('Hold hanya bisa dibatalkan oleh kasir yang sama.');
        }

        $hold->forceFill(['status' => PosHoldStatus::CANCELLED, 'cancel_reason' => $reason, 'cancelled_at' => now()])->save();

        return $hold->fresh(['customer', 'branch']);
    }

    public function voidSale(PosSale $sale, User $actor, string $reason): PosSale
    {
        return DB::transaction(function () use ($sale, $actor, $reason): PosSale {
            $sale = PosSale::query()->with(['items.product', 'branch.workLocation', 'cashShift'])->lockForUpdate()->findOrFail($sale->id);
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

                $this->inventory->returnIn(
                    $item->product,
                    $sale->branch->workLocation,
                    $item->warehouseLocation,
                    $remaining,
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
            $sale = PosSale::query()->with(['items.product', 'branch.workLocation', 'cashShift'])->lockForUpdate()->findOrFail($sale->id);
            if ($sale->cashShift?->status->isLocked()) {
                throw ServiceException::validation('Transaksi pada shift yang sudah closing tidak dapat diretur. Gunakan workflow koreksi resmi.');
            }
            if (! in_array($sale->status, [PosSaleStatus::COMPLETED, PosSaleStatus::RETURNED], true)) {
                throw ServiceException::validation('Transaksi tidak dapat diretur pada status saat ini.');
            }

            $refundAmount = '0.00';
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

                PosReturnItem::query()->create([
                    'pos_return_id' => $return->id,
                    'pos_sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'warehouse_location_id' => $item->warehouse_location_id,
                    'quantity' => $quantity,
                    'condition' => $condition,
                    'refund_amount' => $lineRefund,
                    'reason' => $itemData['reason'] ?? null,
                ]);

                $this->inventory->returnIn(
                    $item->product,
                    $sale->branch->workLocation,
                    $item->warehouseLocation,
                    $quantity,
                    $actor,
                    ['type' => 'pos_return', 'id' => $return->id, 'no' => $return->number],
                    $data['reason'] ?? 'Retur pelanggan POS.',
                    "pos-return-{$return->id}-item-{$item->id}-in",
                );

                if ($condition === 'damaged') {
                    $this->inventory->damage(
                        $item->product,
                        $sale->branch->workLocation,
                        $item->warehouseLocation,
                        $quantity,
                        $actor,
                        ['type' => 'pos_return', 'id' => $return->id, 'no' => $return->number],
                        'Retur POS masuk stok rusak.',
                        "pos-return-{$return->id}-item-{$item->id}-damage",
                    );
                }

                $item->forceFill(['returned_quantity' => Decimal::add((string) $item->returned_quantity, $quantity)])->save();
            }

            if (Decimal::compare($refundAmount, '0', 2) <= 0) {
                throw ServiceException::validation('Minimal satu item retur harus memiliki qty lebih besar dari nol.');
            }

            $return->forceFill(['refund_amount' => $refundAmount])->save();
            $sale->forceFill(['status' => PosSaleStatus::RETURNED])->save();

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
     * @param  array<int, mixed>  $items
     * @return array{subtotal: string, discount: string, grand_total: string, margin: string, items: list<array<string, mixed>>}
     */
    private function calculateItems(array $items, Branch $branch, ?Customer $customer, User $cashier): array
    {
        $subtotal = '0.00';
        $discountTotal = '0.00';
        $grandTotal = '0.00';
        $marginTotal = '0.00';
        $rows = [];

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $product = Product::query()->with(['baseUnit', 'units.unit'])->where('status', 'active')->findOrFail($row['product_id']);
            $unitId = isset($row['unit_id']) ? (int) $row['unit_id'] : (int) $product->base_unit_id;
            $unit = Unit::query()->findOrFail($unitId);
            $warehouseLocation = isset($row['warehouse_location_id']) ? WarehouseLocation::query()->find($row['warehouse_location_id']) : null;
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

            if ($price['approval_required'] === true) {
                throw ServiceException::validation('Harga/diskon membutuhkan approval: '.implode(', ', $price['approval_reasons']));
            }

            $selectedPrice = (string) $price['selected_price'];
            $discountedPrice = (string) $price['discounted_price'];
            $baseQuantity = (string) $price['quantity_base'];
            $lineSubtotal = Decimal::mul($quantity, $selectedPrice, 4, 2, 2);
            $lineTotal = Decimal::mul($quantity, $discountedPrice, 4, 2, 2);
            $discountAmount = Decimal::sub($lineSubtotal, $lineTotal, 2);
            $marginAmount = Decimal::mul($quantity, (string) $price['margin_amount'], 4, 2, 2);

            $subtotal = Decimal::add($subtotal, $lineSubtotal, 2);
            $discountTotal = Decimal::add($discountTotal, $discountAmount, 2);
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
                'line_total' => $lineTotal,
                'margin_amount' => $marginAmount,
            ];
        }

        return ['subtotal' => $subtotal, 'discount' => $discountTotal, 'grand_total' => $grandTotal, 'margin' => $marginTotal, 'items' => $rows];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, paid: string, change: string, cash_amount: string}
     */
    private function validatePayments(mixed $payments, string $grandTotal): array
    {
        if (! is_array($payments) || $payments === []) {
            throw ServiceException::validation('Minimal satu metode pembayaran wajib diisi.');
        }

        $paid = '0.00';
        $cash = '0.00';
        $rows = [];

        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }
            $method = (string) ($payment['method'] ?? '');
            if (! in_array($method, array_keys(PaymentMethod::options()), true)) {
                throw ServiceException::validation('Metode pembayaran tidak valid.');
            }
            if ($method === PaymentMethod::CREDIT->value) {
                throw ServiceException::validation('Pembayaran tempo/piutang belum aktif untuk POS internal.');
            }
            $amount = Decimal::normalize($payment['amount'] ?? 0, 2);
            if (Decimal::compare($amount, '0', 2) <= 0) {
                throw ServiceException::validation('Nominal pembayaran harus lebih besar dari nol.');
            }
            $paid = Decimal::add($paid, $amount, 2);
            if ($method === PaymentMethod::CASH->value) {
                $cash = Decimal::add($cash, $amount, 2);
            }
            $rows[] = [...$payment, 'method' => $method, 'amount' => $amount];
        }

        if (Decimal::compare($paid, $grandTotal, 2) < 0) {
            throw ServiceException::validation('Total pembayaran kurang dari grand total.');
        }

        return ['rows' => $rows, 'paid' => $paid, 'change' => Decimal::sub($paid, $grandTotal, 2), 'cash_amount' => $cash];
    }
}
