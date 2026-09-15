<?php

namespace App\Services\Retail;

use App\Enums\CashShiftStatus;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\ServiceException;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\EmergencyPurchase;
use App\Models\EmergencyPurchaseItem;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ShiftExpense;
use App\Models\Stock;
use App\Models\User;
use App\Services\Control\ApprovalWorkflowService;
use App\Services\Inventory\InventoryService;
use App\Services\Notifications\BusinessNotificationService;
use App\Services\Organization\DocumentNumberService;
use App\Support\CurrencyFormatter;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmergencyPurchaseService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly PosCatalogService $catalog,
        private readonly ApprovalWorkflowService $approvals,
        private readonly InventoryService $inventory,
        private readonly BusinessNotificationService $notifications,
    ) {}

    public function regularize(EmergencyPurchase $purchase, User $actor): EmergencyPurchase
    {
        return DB::transaction(function () use ($purchase, $actor): EmergencyPurchase {
            $purchase = EmergencyPurchase::query()->with(['items.product', 'branch.workLocation'])->lockForUpdate()->findOrFail($purchase->id);
            $this->assertLocation($purchase, $actor);
            if ($purchase->status !== 'unallocated') {
                throw ServiceException::validation('Hanya saldo pool darurat yang dapat dimasukkan ke stok reguler.');
            }

            $reservations = app(EmergencyStockService::class)->pendingAssignmentsFor($purchase->items->pluck('id')->all());
            $converted = '0.0000';
            foreach ($purchase->items as $item) {
                $remaining = $this->remaining($item);
                $free = Decimal::sub($remaining, $reservations[$item->id] ?? '0.0000', 4);
                if (Decimal::compare($free, '0', 4) <= 0) {
                    continue;
                }

                $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
                $beforeQty = Decimal::normalize(Stock::query()->where('product_id', $product->id)->sum('quantity_on_hand'));
                $hppBefore = Decimal::normalize($product->cost_price ?? 0, 2);
                $qtyAfter = Decimal::add($beforeQty, $free, 4);
                $incomingCost = Decimal::mul($free, (string) $item->unit_cost, 4, 2, 2);
                $hppAfter = Decimal::div(
                    Decimal::add(Decimal::mul($beforeQty, $hppBefore, 4, 2, 2), $incomingCost, 2),
                    $qtyAfter,
                    2,
                    4,
                    2,
                );

                $this->inventory->receive(
                    product: $product,
                    workLocation: $purchase->branch->workLocation,
                    warehouseLocation: null,
                    quantity: $free,
                    actor: $actor,
                    reference: ['type' => 'emergency_regularization', 'id' => $purchase->id, 'no' => $purchase->number],
                    reason: 'Saldo pembelian darurat dimasukkan ke stok reguler toko.',
                    idempotencyKey: "emergency-{$purchase->id}-item-{$item->id}-regularize",
                    metadata: ['emergency_purchase_item_id' => $item->id, 'unit_cost' => $item->unit_cost],
                );
                $product->forceFill(['cost_price' => $hppAfter])->save();
                $item->forceFill(['regularized_quantity' => Decimal::add((string) $item->regularized_quantity, $free, 4)])->save();
                $converted = Decimal::add($converted, $free, 4);
            }

            if (Decimal::compare($converted, '0', 4) <= 0) {
                throw ServiceException::validation('Tidak ada saldo bebas yang dapat dipindahkan. Saldo mungkin sedang dicadangkan untuk transaksi pelanggan.');
            }

            $hasRemaining = $purchase->items->contains(fn (EmergencyPurchaseItem $item): bool => Decimal::compare($this->remaining($item->fresh()), '0', 4) > 0);
            $purchase->forceFill([
                'status' => $hasRemaining ? 'unallocated' : 'completed',
                'regularized_by' => $actor->id,
                'regularized_at' => now(),
            ])->save();
            $this->history($purchase, $actor, 'regularized_to_store_stock', 'unallocated', $purchase->status, "Qty {$converted} masuk stok reguler toko.");

            return $purchase->fresh(['items', 'histories']);
        });
    }

    /** @param array<string, mixed> $data */
    public function confirm(array $data, User $actor): EmergencyPurchase
    {
        return DB::transaction(function () use ($data, $actor): EmergencyPurchase {
            if (filled($data['confirmation_key'] ?? null)) {
                $existing = EmergencyPurchase::query()->where('confirmation_key', $data['confirmation_key'])->first();
                if ($existing instanceof EmergencyPurchase) {
                    if ((int) $existing->requested_by !== (int) $actor->id) {
                        throw ServiceException::validation('Kunci konfirmasi sudah digunakan pemohon lain.');
                    }

                    return $existing->load('items.product');
                }
            }
            $branch = $this->branch((int) $data['branch_id'], $actor);
            $purpose = (string) ($data['purpose'] ?? 'customer_request');
            if (! in_array($purpose, ['customer_request', 'proactive_restock'], true)) {
                throw ServiceException::validation('Jenis pembelian darurat tidak valid.');
            }
            if ($purpose === 'proactive_restock' && filled($data['customer_id'] ?? null)) {
                throw ServiceException::validation('Restok darurat toko tidak boleh ditautkan ke pelanggan.');
            }
            $purchase = EmergencyPurchase::query()->create([
                'number' => $this->numbers->next('emergency_purchase', $branch->workLocation),
                'confirmation_key' => $data['confirmation_key'] ?? null,
                'purpose' => $purpose,
                'branch_id' => $branch->id,
                'work_location_id' => $branch->work_location_id,
                'customer_id' => $purpose === 'customer_request' ? ($data['customer_id'] ?? null) : null,
                'requested_by' => $actor->id,
                'status' => 'approved',
                'notes' => $data['notes'] ?? null,
            ]);
            $total = '0.00';
            $seen = [];
            foreach ($data['items'] as $row) {
                $product = Product::query()->with('units')->where('status', 'active')->findOrFail($row['product_id']);
                $unitId = (int) ($row['unit_id'] ?? $product->base_unit_id);
                $itemKey = (string) $product->id;
                if (isset($seen[$itemKey])) {
                    throw ServiceException::validation('Produk yang sama hanya boleh satu baris dalam satu permintaan.');
                }
                $seen[$itemKey] = true;
                $unit = $product->units->firstWhere('unit_id', $unitId);
                if ($unitId !== (int) $product->base_unit_id && (! $unit || ! $unit->is_active || ! $unit->is_sellable)) {
                    throw ServiceException::validation('Unit jual produk tidak valid.');
                }
                $quantity = Decimal::normalize((string) $row['quantity'], 4);
                if (Decimal::compare($quantity, '0', 4) <= 0) {
                    throw ServiceException::validation('Qty permintaan harus lebih besar dari nol.');
                }
                $factor = $unitId === (int) $product->base_unit_id ? '1.000000' : (string) $unit->conversion_factor;
                $baseQuantity = Decimal::mul($quantity, $factor, 4, 6, 4);
                $quote = $this->catalog->quote($branch, $actor, [
                    'product_id' => $product->id, 'unit_id' => $unitId,
                    'quantity' => $quantity,
                    'customer_id' => $purpose === 'customer_request' ? ($data['customer_id'] ?? null) : null,
                ]);
                $available = (string) $quote['regular_stock_base'];
                $emergencyAvailable = (string) $quote['emergency_stock_base'];
                $shortage = $purpose === 'customer_request'
                    ? Decimal::sub(Decimal::sub($baseQuantity, $available, 4), $emergencyAvailable, 4)
                    : '0.0000';
                if ($purpose === 'customer_request' && Decimal::compare($shortage, '0', 4) <= 0) {
                    throw ServiceException::validation($product->name.': stok masih cukup; pembelian darurat tidak diperlukan.');
                }
                $cost = Decimal::normalize((string) $row['unit_cost'], 2);
                if (Decimal::compare($cost, '0', 2) <= 0) {
                    throw ServiceException::validation('Estimasi biaya harus lebih besar dari nol.');
                }
                $purchase->items()->create([
                    'product_id' => $product->id, 'unit_id' => $unitId,
                    'warehouse_location_id' => $quote['warehouse_location_id'],
                    'requested_quantity' => $quantity, 'base_quantity' => $baseQuantity,
                    'available_at_request' => $available, 'shortage_at_request' => $shortage,
                    'unit_cost' => $cost, 'expected_sale_price' => $quote['pricing']['recommended_price'],
                ]);
                if ($purpose === 'customer_request') {
                    DB::table('retail_stockout_events')->insert([
                        'branch_id' => $branch->id, 'product_id' => $product->id,
                        'emergency_purchase_id' => $purchase->id, 'confirmed_by' => $actor->id,
                        'requested_quantity' => $baseQuantity, 'available_quantity' => $available,
                        'emergency_available_quantity' => $emergencyAvailable,
                        'shortage_quantity' => $shortage, 'confirmation_key' => ($data['confirmation_key'] ?? (string) Str::uuid()).':'.$itemKey,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $approvedQuantity = $purpose === 'proactive_restock' ? $baseQuantity : $shortage;
                $total = Decimal::add($total, Decimal::mul($approvedQuantity, $cost, 4, 2, 2), 2);
            }
            $purchase->forceFill(['total_cost' => $total])->save();
            $rule = DB::table('emergency_purchase_rules')->where('branch_id', $branch->id)->where('is_active', true)->first();
            if ($rule && ($rule->approval_above_amount === null || Decimal::compare($total, (string) $rule->approval_above_amount, 2) > 0)) {
                $purchase->forceFill(['status' => 'pending_approval'])->save();
                $this->approvals->create(
                    $purchase, 'emergency_purchase', 'retail', $actor, $total,
                    'Pembelian darurat toko '.$purchase->number,
                    after: [], location: $branch->workLocation,
                    requiredPermission: 'emergency_purchases.approve', requiredRole: 'kepala_toko',
                    handlerKey: 'retail.emergency_purchase',
                );
            }
            $this->history($purchase, $actor, $purpose === 'proactive_restock' ? 'proactive_restock_requested' : 'confirmed', null, $purchase->status);
            $this->notifications->send(
                'emergency_purchase',
                $purpose === 'proactive_restock' ? 'Restok Darurat Toko Dibuat' : 'Pembelian Darurat Diajukan',
                "{$purchase->number} di {$branch->name}.\nNilai perkiraan: ".CurrencyFormatter::rupiah($total)."\nStatus: {$purchase->status}",
                $purchase->work_location_id,
                route('retail.emergency.show', $purchase),
                $purchase->id.':requested',
            );

            return $purchase->load('items.product');
        });
    }

    /** @param array<string, mixed> $data */
    public function purchased(EmergencyPurchase $purchase, array $data, User $actor): EmergencyPurchase
    {
        return DB::transaction(function () use ($purchase, $data, $actor): EmergencyPurchase {
            $purchase = EmergencyPurchase::query()->with('items')->lockForUpdate()->findOrFail($purchase->id);
            $this->assertLocation($purchase, $actor);
            if ($purchase->status !== 'approved') {
                throw ServiceException::validation('Permintaan belum disetujui atau sudah diproses.');
            }
            $items = collect((array) $data['items'])->keyBy('id');
            if ($items->count() !== $purchase->items->count()) {
                throw ServiceException::validation('Semua item pembelian wajib dicatat.');
            }
            $total = '0.00';
            foreach ($purchase->items as $item) {
                $row = $items->get($item->id);
                if (! is_array($row)) {
                    throw ServiceException::validation('Item pembelian tidak sesuai permintaan.');
                }
                $quantity = Decimal::normalize((string) $row['purchased_quantity'], 4);
                $maximum = $purchase->purpose === 'proactive_restock'
                    ? (string) $item->base_quantity
                    : (string) $item->shortage_at_request;
                if (Decimal::compare($quantity, '0', 4) <= 0 || Decimal::compare($quantity, $maximum, 4) > 0) {
                    throw ServiceException::validation('Qty pembelian melebihi batas kekurangan atau jumlah yang disetujui.');
                }
                $cost = Decimal::normalize((string) $row['unit_cost'], 2);
                if (Decimal::compare($cost, '0', 2) <= 0) {
                    throw ServiceException::validation('Biaya aktual harus lebih besar dari nol.');
                }
                $item->forceFill(['purchased_quantity' => $quantity, 'unit_cost' => $cost])->save();
                $total = Decimal::add($total, Decimal::mul($quantity, $cost, 4, 2, 2), 2);
            }
            $rule = DB::table('emergency_purchase_rules')->where('branch_id', $purchase->branch_id)->where('is_active', true)->first();
            if ($rule && Decimal::compare($total, (string) $purchase->total_cost, 2) > 0) {
                throw ServiceException::validation('Biaya aktual melebihi nilai permintaan yang disetujui. Buat konfirmasi baru dengan estimasi yang sesuai.');
            }
            $requiresApproval = $rule && ($rule->approval_above_amount === null
                || Decimal::compare($total, (string) $rule->approval_above_amount, 2) > 0);
            if ($requiresApproval && ! DB::table('approval_requests')
                ->where('subject_type', $purchase->getMorphClass())->where('subject_id', $purchase->id)
                ->where('approval_type', 'emergency_purchase')->where('current_status', 'approved')->exists()) {
                throw ServiceException::validation('Aturan approval toko berubah. Ajukan kembali pembelian darurat sebelum membeli.');
            }
            $fund = (string) $data['fund_source'];
            if (! in_array($fund, ['store_cash', 'personal'], true)) {
                throw ServiceException::validation('Sumber dana pembelian darurat tidak valid.');
            }
            $expenseId = null;
            if ($fund === 'store_cash') {
                $shift = CashShift::query()->where('branch_id', $purchase->branch_id)
                    ->where('cashier_user_id', $actor->id)->where('status', CashShiftStatus::OPEN->value)
                    ->lockForUpdate()->first();
                if (! $shift) {
                    throw ServiceException::validation('Pembelian dengan kas toko memerlukan shift kasir aktif.');
                }
                $expense = ShiftExpense::query()->create([
                    'cash_shift_id' => $shift->id, 'branch_id' => $shift->branch_id,
                    'work_location_id' => $shift->work_location_id, 'created_by' => $actor->id,
                    'category' => 'emergency_purchase', 'payment_method' => 'cash',
                    'amount' => $total, 'notes' => $purchase->number,
                    'proof_path' => $data['receipt_path'], 'spent_at' => now(),
                ]);
                $expenseId = $expense->id;
            }
            $nextStatus = $purchase->purpose === 'proactive_restock' ? 'unallocated' : 'purchased';
            $purchase->forceFill([
                'status' => $nextStatus, 'supplier_name' => $data['supplier_name'],
                'fund_source' => $fund, 'receipt_path' => $data['receipt_path'],
                'total_cost' => $total, 'purchased_by' => $actor->id, 'purchased_at' => now(),
                'shift_expense_id' => $expenseId,
                'reimbursement_status' => $fund === 'personal' ? 'pending' : 'none',
                'reimbursement_amount' => $fund === 'personal' ? $total : null,
            ])->save();
            $this->history($purchase, $actor, 'purchased', 'approved', $nextStatus,
                $purchase->purpose === 'proactive_restock' ? 'Barang tersedia pada pool darurat toko.' : null);
            $this->notifications->send(
                'emergency_purchase',
                'Pembelian Darurat Sudah Dibeli',
                "{$purchase->number} telah dicatat oleh {$actor->name}.\nBiaya aktual: ".CurrencyFormatter::rupiah($total)."\nStatus: {$nextStatus}",
                $purchase->work_location_id,
                route('retail.emergency.show', $purchase),
                $purchase->id.':purchased',
            );

            return $purchase->fresh('items');
        });
    }

    public function cancel(EmergencyPurchase $purchase, User $actor, string $reason): EmergencyPurchase
    {
        return DB::transaction(function () use ($purchase, $actor, $reason): EmergencyPurchase {
            $purchase = EmergencyPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->assertLocation($purchase, $actor);
            if (! in_array($purchase->status, ['approved', 'pending_approval', 'purchased'], true)) {
                throw ServiceException::validation('Permintaan ini tidak dapat dibatalkan.');
            }
            $old = $purchase->status;
            $new = $old === 'purchased' && $purchase->fund_source !== 'reallocated' ? 'unallocated' : 'cancelled';
            if ($purchase->fund_source === 'reallocated') {
                foreach ($purchase->items as $item) {
                    $item->forceFill(['assigned_source_item_id' => null, 'assigned_quantity' => '0.0000',
                        'purchased_quantity' => '0.0000'])->save();
                }
            }
            $purchase->forceFill(['status' => $new])->save();
            DB::table('approval_requests')->where('subject_type', $purchase->getMorphClass())
                ->where('subject_id', $purchase->id)->where('current_status', 'pending')
                ->update(['current_status' => 'cancelled', 'cancelled_at' => now(), 'decision_notes' => $reason, 'updated_at' => now()]);
            $this->history($purchase, $actor, 'customer_cancelled', $old, $new, $reason);

            return $purchase;
        });
    }

    public function reimburse(EmergencyPurchase $purchase, User $actor, string $reference, string $proofPath): EmergencyPurchase
    {
        return DB::transaction(function () use ($purchase, $actor, $reference, $proofPath): EmergencyPurchase {
            if (trim($reference) === '' || trim($proofPath) === '') {
                throw ServiceException::validation('Referensi dan bukti pembayaran reimbursement wajib tercatat.');
            }
            $purchase = EmergencyPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $this->assertLocation($purchase, $actor);
            if ($purchase->fund_source !== 'personal' || $purchase->reimbursement_status !== 'pending') {
                throw ServiceException::validation('Tidak ada reimbursement yang menunggu pembayaran.');
            }
            $purchase->forceFill(['reimbursement_status' => 'paid', 'reimbursement_reference' => $reference,
                'reimbursement_proof_path' => $proofPath, 'reimbursed_at' => now()])->save();
            $this->history($purchase, $actor, 'reimbursed', $purchase->status, $purchase->status, $reference);

            return $purchase;
        });
    }

    public function supplierReturn(
        EmergencyPurchase $purchase,
        User $actor,
        string $reference,
        string $refundAmount,
        string $recipient = 'store',
        string $method = 'bank_transfer',
        ?int $cashShiftId = null,
    ): EmergencyPurchase {
        return DB::transaction(function () use ($purchase, $actor, $reference, $refundAmount, $recipient, $method, $cashShiftId): EmergencyPurchase {
            $purchase = EmergencyPurchase::query()->with('items')->lockForUpdate()->findOrFail($purchase->id);
            $this->assertLocation($purchase, $actor);
            if ($purchase->status !== 'unallocated') {
                throw ServiceException::validation('Hanya barang belum teralokasi dapat diretur ke pemasok.');
            }
            $hasRemaining = false;
            $maximumRefund = '0.00';
            foreach ($purchase->items as $item) {
                if (Decimal::compare($this->pendingAssignments($item), '0', 4) > 0) {
                    throw ServiceException::validation('Barang masih ditugaskan ke permintaan POS lain.');
                }
                $remaining = $this->remaining($item);
                $hasRemaining = $hasRemaining || Decimal::compare($remaining, '0', 4) > 0;
                $maximumRefund = Decimal::add($maximumRefund, Decimal::mul($remaining, (string) $item->unit_cost, 4, 2, 2), 2);
                $item->forceFill(['supplier_returned_quantity' => Decimal::add((string) $item->supplier_returned_quantity, $remaining, 4)])->save();
            }
            if (! $hasRemaining) {
                throw ServiceException::validation('Tidak ada barang tersisa untuk diretur ke pemasok.');
            }
            $refundAmount = Decimal::normalize($refundAmount, 2);
            if (Decimal::compare($refundAmount, '0', 2) < 0 || Decimal::compare($refundAmount, $maximumRefund, 2) > 0) {
                throw ServiceException::validation('Nominal pengembalian pemasok melebihi nilai barang yang diretur.');
            }
            if (! in_array($recipient, ['store', 'purchaser'], true) || ! in_array($method, ['cash', 'bank_transfer'], true)) {
                throw ServiceException::validation('Penerima atau metode pengembalian pemasok tidak valid.');
            }
            $refundShiftId = null;
            $reimbursement = [];
            if (Decimal::compare($refundAmount, '0', 2) > 0 && $recipient === 'purchaser') {
                if ($purchase->fund_source !== 'personal' || $purchase->reimbursement_status !== 'pending') {
                    throw ServiceException::validation('Refund kepada pembeli hanya dapat mengurangi reimbursement pribadi yang belum dibayar.');
                }
                $remainingPayable = Decimal::sub((string) $purchase->reimbursement_amount, $refundAmount, 2);
                $reimbursement = ['reimbursement_amount' => $remainingPayable,
                    'reimbursement_status' => Decimal::compare($remainingPayable, '0', 2) === 0 ? 'not_required' : 'pending'];
            }
            if (Decimal::compare($refundAmount, '0', 2) > 0 && $recipient === 'store' && $method === 'cash') {
                $shift = CashShift::query()->whereKey($cashShiftId)->where('branch_id', $purchase->branch_id)
                    ->where('status', CashShiftStatus::OPEN->value)->lockForUpdate()->first();
                if (! $shift instanceof CashShift) {
                    throw ServiceException::validation('Refund tunai untuk toko harus diterima pada shift kasir aktif di toko yang sama.');
                }
                $refundShiftId = $shift->id;
            }
            $purchase->forceFill(['status' => 'supplier_returned', 'supplier_returned_at' => now(),
                'supplier_return_reference' => $reference, 'supplier_refund_amount' => $refundAmount,
                'supplier_refund_recipient' => $recipient, 'supplier_refund_method' => $method,
                'supplier_refund_cash_shift_id' => $refundShiftId, ...$reimbursement])->save();
            $this->history($purchase, $actor, 'supplier_returned', 'unallocated', 'supplier_returned', $reference);

            return $purchase;
        });
    }

    public function reassign(EmergencyPurchase $source, EmergencyPurchase $target, User $actor): EmergencyPurchase
    {
        return DB::transaction(function () use ($source, $target, $actor): EmergencyPurchase {
            $source = EmergencyPurchase::query()->with('items')->lockForUpdate()->findOrFail($source->id);
            $target = EmergencyPurchase::query()->with('items')->lockForUpdate()->findOrFail($target->id);
            $this->assertLocation($source, $actor);
            $this->assertLocation($target, $actor);
            if ($source->status !== 'unallocated' || $target->status !== 'approved'
                || (int) $source->branch_id !== (int) $target->branch_id || $source->id === $target->id) {
                throw ServiceException::validation('Alokasi ulang hanya untuk permintaan disetujui di toko yang sama.');
            }
            $branch = Branch::query()->findOrFail($target->branch_id);
            foreach ($target->items as $targetItem) {
                $sourceItem = $source->items->first(fn (EmergencyPurchaseItem $candidate): bool => (int) $candidate->product_id === (int) $targetItem->product_id
                    && (int) $candidate->unit_id === (int) $targetItem->unit_id);
                if (! $sourceItem instanceof EmergencyPurchaseItem) {
                    throw ServiceException::validation('Produk dan satuan sumber tidak cocok dengan kebutuhan tujuan.');
                }
                $sourceItem = EmergencyPurchaseItem::query()->lockForUpdate()->findOrFail($sourceItem->id);
                $quote = $this->catalog->quote($branch, $actor, [
                    'product_id' => $targetItem->product_id, 'unit_id' => $targetItem->unit_id,
                    'quantity' => $targetItem->requested_quantity, 'customer_id' => $target->customer_id,
                ]);
                $available = (string) $quote['stock_base'];
                $shortage = Decimal::sub((string) $targetItem->base_quantity, $available, 4);
                if (Decimal::compare($shortage, '0', 4) <= 0) {
                    throw ServiceException::validation('Stok tujuan sudah cukup; tidak perlu alokasi darurat.');
                }
                $free = Decimal::sub($this->remaining($sourceItem), $this->pendingAssignments($sourceItem), 4);
                if (Decimal::compare($shortage, $free, 4) > 0) {
                    throw ServiceException::validation('Barang sumber belum teralokasi tidak cukup untuk kebutuhan tujuan.');
                }
                $targetItem->forceFill(['assigned_source_item_id' => $sourceItem->id,
                    'assigned_quantity' => $shortage, 'purchased_quantity' => $shortage,
                    'unit_cost' => $sourceItem->unit_cost])->save();
            }
            $target->forceFill(['status' => 'purchased', 'fund_source' => 'reallocated',
                'supplier_name' => $source->supplier_name, 'total_cost' => '0.00'])->save();
            $this->history($target, $actor, 'reallocated_in', 'approved', 'purchased', $source->number);
            $this->history($source, $actor, 'reallocated_out', 'unallocated', 'unallocated', $target->number);

            return $target->fresh('items');
        });
    }

    private function pendingAssignments(EmergencyPurchaseItem $sourceItem): string
    {
        return EmergencyPurchaseItem::query()->where('assigned_source_item_id', $sourceItem->id)
            ->whereHas('purchase', fn ($query) => $query->where('status', 'purchased'))
            ->get()->reduce(fn (string $sum, EmergencyPurchaseItem $item): string => Decimal::add($sum, Decimal::sub((string) $item->assigned_quantity, (string) $item->allocated_quantity, 4), 4), '0.0000');
    }

    public function forwardToWarehouse(EmergencyPurchase $purchase, PurchaseOrder $order, User $actor): EmergencyPurchase
    {
        return DB::transaction(function () use ($purchase, $order, $actor): EmergencyPurchase {
            $purchase = EmergencyPurchase::query()->with('items')->lockForUpdate()->findOrFail($purchase->id);
            $this->assertLocation($purchase, $actor);
            $order = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            if ($purchase->status !== 'unallocated'
                || ! in_array($order->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::SENT_TO_SUPPLIER], true)
                || GoodsReceipt::query()->where('purchase_order_id', $order->id)->where('status', GoodsReceiptStatus::POSTED->value)->exists()) {
                throw ServiceException::validation('PO harus disetujui dan belum diterima, serta barang darurat belum teralokasi.');
            }
            if (EmergencyPurchase::query()->where('purchase_order_id', $order->id)->exists()) {
                throw ServiceException::validation('PO sudah terhubung ke permintaan darurat lain.');
            }
            foreach ($purchase->items as $item) {
                if (Decimal::compare($this->pendingAssignments($item), '0', 4) > 0) {
                    throw ServiceException::validation('Barang masih ditugaskan ke POS lain.');
                }
                $remaining = $this->remaining($item);
                if (Decimal::compare($remaining, '0', 4) <= 0) {
                    continue;
                }
                $ordered = $order->items->where('product_id', $item->product_id)->reduce(
                    fn (string $sum, $poItem): string => Decimal::add($sum,
                        Decimal::mul((string) $poItem->quantity_ordered, (string) $poItem->conversion_factor_snapshot, 4, 6, 4), 4), '0.0000');
                if (Decimal::compare($ordered, $remaining, 4) < 0) {
                    throw ServiceException::validation('Qty PO untuk '.$item->product?->name.' tidak mencakup barang darurat tersisa.');
                }
            }
            $purchase->forceFill(['status' => 'warehouse_pending', 'purchase_order_id' => $order->id])->save();
            $this->history($purchase, $actor, 'forwarded_to_warehouse', 'unallocated', 'warehouse_pending', $order->number);

            return $purchase;
        });
    }

    public function completeWarehouse(EmergencyPurchase $purchase, GoodsReceipt $receipt, User $actor): EmergencyPurchase
    {
        return DB::transaction(function () use ($purchase, $receipt, $actor): EmergencyPurchase {
            $purchase = EmergencyPurchase::query()->with('items')->lockForUpdate()->findOrFail($purchase->id);
            $this->assertLocation($purchase, $actor);
            $receipt = GoodsReceipt::query()->with('items')->lockForUpdate()->findOrFail($receipt->id);
            if ($purchase->status !== 'warehouse_pending' || (int) $receipt->purchase_order_id !== (int) $purchase->purchase_order_id
                || $receipt->status !== GoodsReceiptStatus::POSTED) {
                throw ServiceException::validation('Penerimaan gudang harus sudah posted terhadap PO yang terhubung.');
            }
            foreach ($purchase->items as $item) {
                $remaining = $this->remaining($item);
                if (Decimal::compare($remaining, '0', 4) <= 0) {
                    continue;
                }
                $accepted = $receipt->items->where('product_id', $item->product_id)->reduce(
                    fn (string $sum, $receiptItem): string => Decimal::add($sum, $receiptItem->acceptedBaseQuantity(), 4), '0.0000');
                if (Decimal::compare($accepted, $remaining, 4) < 0) {
                    throw ServiceException::validation('Qty diterima gudang belum mencakup barang darurat tersisa.');
                }
                $item->forceFill(['warehouse_quantity' => Decimal::add((string) $item->warehouse_quantity, $remaining, 4)])->save();
            }
            $purchase->forceFill(['status' => 'warehouse_received', 'goods_receipt_id' => $receipt->id])->save();
            $this->history($purchase, $actor, 'warehouse_received', 'warehouse_pending', 'warehouse_received', $receipt->number);

            return $purchase;
        });
    }

    private function remaining(EmergencyPurchaseItem $item): string
    {
        return Decimal::sub(
            Decimal::sub(Decimal::sub(Decimal::sub((string) $item->purchased_quantity,
                (string) $item->allocated_quantity, 4), (string) $item->supplier_returned_quantity, 4),
                (string) $item->warehouse_quantity, 4),
            (string) $item->regularized_quantity,
            4,
        );
    }

    public function available(Branch $branch, int $productId, ?int $warehouseLocationId = null): string
    {
        $rows = Stock::query()->where('product_id', $productId)->where('work_location_id', $branch->work_location_id)
            ->when($warehouseLocationId !== null, fn ($query) => $query->where('warehouse_location_id', $warehouseLocationId))
            ->get();

        return $rows->reduce(fn (string $sum, Stock $stock): string => Decimal::add($sum, $stock->available_quantity, 4), '0.0000');
    }

    private function branch(int $branchId, User $actor): Branch
    {
        $branch = Branch::query()->with('workLocation')->where('is_active', true)->findOrFail($branchId);
        if (! $actor->canAccessWorkLocation((int) $branch->work_location_id)) {
            throw ServiceException::validation('Toko tidak termasuk dalam penugasan Anda.');
        }

        return $branch;
    }

    private function assertLocation(EmergencyPurchase $purchase, User $actor): void
    {
        if (! $actor->canAccessWorkLocation((int) $purchase->work_location_id)) {
            throw ServiceException::validation('Toko tidak termasuk dalam penugasan Anda.');
        }
    }

    private function history(EmergencyPurchase $purchase, User $actor, string $action, ?string $from, ?string $to, ?string $notes = null): void
    {
        $purchase->histories()->create(['actor_id' => $actor->id, 'action' => $action,
            'from_status' => $from, 'to_status' => $to, 'notes' => $notes]);
    }
}
