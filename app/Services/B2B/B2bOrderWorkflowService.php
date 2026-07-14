<?php

namespace App\Services\B2B;

use App\Enums\B2bOrderStatus;
use App\Enums\CustomerStatus;
use App\Enums\StockReservationStatus;
use App\Events\B2B\B2bOrderStatusChanged;
use App\Exceptions\ServiceException;
use App\Models\B2bOrder;
use App\Models\B2bOrderItem;
use App\Models\B2bOrderMessage;
use App\Models\B2bOrderStatusHistory;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class B2bOrderWorkflowService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function reserve(B2bOrder $order, User $actor, array $data = []): B2bOrder
    {
        return DB::transaction(function () use ($order, $actor, $data): B2bOrder {
            $order = B2bOrder::query()->with(['items.product', 'customer'])->lockForUpdate()->findOrFail($order->id);
            $this->ensureCustomerCanProceed($order);

            if ($order->reservations()->where('status', StockReservationStatus::ACTIVE->value)->exists()) {
                return $order->fresh(['items', 'reservations', 'customer']);
            }

            $status = $this->orderStatus($order);
            if (! in_array($status, [B2bOrderStatus::PENDING_CONFIRMATION, B2bOrderStatus::WAREHOUSE_VALIDATION], true)) {
                throw ServiceException::validation('Order tidak dapat di-reserve pada status saat ini.');
            }

            $this->transition($order, B2bOrderStatus::WAREHOUSE_VALIDATION, $actor, 'Order masuk validasi gudang.');

            $expiresAt = isset($data['reservation_expires_at']) && filled($data['reservation_expires_at'])
                ? Carbon::parse((string) $data['reservation_expires_at'])
                : now()->addHours(24);
            $allowPartial = (bool) ($data['allow_partial'] ?? false);
            $approvedQuantities = is_array($data['approved_quantities'] ?? null) ? $data['approved_quantities'] : [];
            $reservedTotal = '0.0000';
            $subtotal = '0.00';

            foreach ($order->items as $item) {
                $approvedQuantity = $approvedQuantities[$item->id] ?? $item->quantity;
                $approvedQuantity = Decimal::normalize($approvedQuantity, 4);
                $approvedBaseQuantity = Decimal::mul($approvedQuantity, (string) $item->conversion_factor_snapshot, 4, 6, 4);
                if (Decimal::compare($approvedBaseQuantity, '0', 4) <= 0) {
                    throw ServiceException::validation('Qty approval harus lebih besar dari nol.');
                }

                $lineTotal = Decimal::mul($approvedQuantity, (string) $item->selected_price, 4, 2, 2);
                $reserved = $this->reserveItem($order, $item, $approvedBaseQuantity, $actor, $expiresAt, $allowPartial);
                $shortage = Decimal::sub($approvedBaseQuantity, $reserved, 4);
                if (Decimal::compare($shortage, '0', 4) > 0 && ! $allowPartial) {
                    throw ServiceException::validation("Stok {$item->product_name_snapshot} tidak mencukupi. Shortage {$shortage}.");
                }

                $item->forceFill([
                    'approved_quantity' => $approvedQuantity,
                    'reserved_quantity' => $reserved,
                    'shortage_quantity' => $shortage,
                    'line_total' => $lineTotal,
                    'fulfillment_status' => Decimal::compare($shortage, '0', 4) > 0 ? 'partial_reserved' : 'reserved',
                ])->save();

                $reservedTotal = Decimal::add($reservedTotal, $reserved, 4);
                $subtotal = Decimal::add($subtotal, $lineTotal, 2);
            }

            if (Decimal::compare($reservedTotal, '0', 4) <= 0) {
                throw ServiceException::validation('Tidak ada stok yang dapat di-reserve untuk order ini.');
            }

            $shippingCost = Decimal::normalize($data['shipping_cost_amount'] ?? $order->shipping_cost_amount ?? 0, 2);
            $grandTotal = Decimal::add($subtotal, $shippingCost, 2);
            $this->ensureCreditLimit($order, $grandTotal);

            $order->forceFill([
                'status' => B2bOrderStatus::RESERVED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'subtotal_amount' => $subtotal,
                'shipping_cost_amount' => $shippingCost,
                'grand_total_amount' => $grandTotal,
                'reservation_expires_at' => $expiresAt,
                'internal_note' => $data['internal_note'] ?? $order->internal_note,
            ])->save();
            $this->logStatus($order, B2bOrderStatus::WAREHOUSE_VALIDATION, B2bOrderStatus::RESERVED, $actor, 'Stok berhasil di-reserve.', ['expires_at' => $expiresAt->toDateTimeString()]);
            $this->message($order, $actor, 'internal', $data['internal_note'] ?? 'Order berhasil di-reserve.');

            return $order->fresh(['items', 'reservations.product', 'customer', 'statusHistories']);
        });
    }

    public function cancel(B2bOrder $order, User $actor, string $reason): B2bOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason): B2bOrder {
            $order = B2bOrder::query()->with(['items', 'reservations.product'])->lockForUpdate()->findOrFail($order->id);
            $from = $this->orderStatus($order);
            if (! $from->canCustomerCancel()) {
                throw ServiceException::validation('Order tidak dapat dibatalkan pada status saat ini.');
            }

            $this->releaseActiveReservations($order, $actor, 'cancelled', $reason);
            $order->forceFill(['status' => B2bOrderStatus::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $this->logStatus($order, $from, B2bOrderStatus::CANCELLED, $actor, $reason);
            $this->message($order, $actor, 'customer', 'Order dibatalkan: '.$reason);

            return $order->fresh(['items', 'reservations']);
        });
    }

    public function reject(B2bOrder $order, User $actor, string $reason): B2bOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason): B2bOrder {
            $order = B2bOrder::query()->with(['items', 'reservations.product'])->lockForUpdate()->findOrFail($order->id);
            $from = $this->orderStatus($order);
            if ($from->isFinal() || in_array($from, [B2bOrderStatus::SHIPPED, B2bOrderStatus::RECEIVED], true)) {
                throw ServiceException::validation('Order tidak dapat ditolak pada status saat ini.');
            }

            $this->releaseActiveReservations($order, $actor, 'rejected', $reason);
            $order->forceFill(['status' => B2bOrderStatus::REJECTED, 'rejected_at' => now(), 'reject_reason' => $reason])->save();
            $this->logStatus($order, $from, B2bOrderStatus::REJECTED, $actor, $reason);
            $this->message($order, $actor, 'customer', 'Order ditolak: '.$reason);

            return $order->fresh(['items', 'reservations']);
        });
    }

    public function pack(B2bOrder $order, User $actor, ?string $note = null): B2bOrder
    {
        return DB::transaction(function () use ($order, $actor, $note): B2bOrder {
            $order = B2bOrder::query()->lockForUpdate()->findOrFail($order->id);
            $from = $this->orderStatus($order);
            if (! in_array($from, [B2bOrderStatus::RESERVED, B2bOrderStatus::INVOICE_READY, B2bOrderStatus::AWAITING_PAYMENT, B2bOrderStatus::APPROVED_CREDIT], true)) {
                throw ServiceException::validation('Order belum siap packing.');
            }

            $order->forceFill(['status' => B2bOrderStatus::PACKING, 'packed_at' => now(), 'internal_note' => $note ?? $order->internal_note])->save();
            $this->logStatus($order, $from, B2bOrderStatus::PACKING, $actor, $note ?? 'Order masuk proses packing.');
            $this->message($order, $actor, 'internal', $note ?? 'Order masuk proses packing.');

            return $order->fresh(['items', 'reservations']);
        });
    }

    /**
     * @param  array<int, string|int|float>  $shipQuantities
     */
    public function ship(B2bOrder $order, User $actor, ?string $note = null, array $shipQuantities = [], ?string $operationSuffix = null): B2bOrder
    {
        return DB::transaction(function () use ($order, $actor, $note, $shipQuantities, $operationSuffix): B2bOrder {
            $order = B2bOrder::query()->with(['items.product', 'reservations.product', 'reservations.stock.workLocation', 'reservations.stock.warehouseLocation'])->lockForUpdate()->findOrFail($order->id);
            $from = $this->orderStatus($order);
            if ($from !== B2bOrderStatus::PACKING) {
                throw ServiceException::validation('Order harus dalam status packing sebelum dikirim.');
            }

            $issuedAny = false;
            foreach ($order->reservations()->where('status', StockReservationStatus::ACTIVE->value)->get() as $reservation) {
                $remaining = $this->reservationRemaining($reservation);
                if (Decimal::compare($remaining, '0', 4) <= 0) {
                    continue;
                }

                $requested = $shipQuantities === [] ? $remaining : Decimal::normalize($shipQuantities[$reservation->b2b_order_item_id] ?? '0', 4);
                if (Decimal::compare($requested, '0', 4) <= 0) {
                    continue;
                }

                $shipQuantity = Decimal::compare($requested, $remaining, 4) >= 0 ? $remaining : $requested;
                $stock = $reservation->stock()->with(['workLocation', 'warehouseLocation'])->firstOrFail();
                $this->inventory->releaseReservation(
                    $reservation->product,
                    $stock->workLocation,
                    $stock->warehouseLocation,
                    $shipQuantity,
                    $actor,
                    ['type' => 'b2b_order_shipment_release', 'id' => $order->id, 'no' => $order->number],
                    'Konversi reserved stock ke pengiriman B2B.',
                    "b2b-reservation-{$reservation->id}-ship-".($operationSuffix ?? 'default').'-release',
                    ['b2b_order_item_id' => $reservation->b2b_order_item_id],
                );
                $this->inventory->issue(
                    $reservation->product,
                    $stock->workLocation,
                    $stock->warehouseLocation,
                    $shipQuantity,
                    $actor,
                    ['type' => 'b2b_order_shipment', 'id' => $order->id, 'no' => $order->number],
                    'Pengiriman order B2B.',
                    "b2b-reservation-{$reservation->id}-ship-".($operationSuffix ?? 'default').'-issue',
                    ['b2b_order_item_id' => $reservation->b2b_order_item_id],
                );

                $reservation->forceFill([
                    'quantity_issued' => Decimal::add((string) $reservation->quantity_issued, $shipQuantity, 4),
                    'status' => Decimal::compare($this->reservationRemainingAfterIssue($reservation, $shipQuantity), '0', 4) <= 0 ? StockReservationStatus::CONVERTED : StockReservationStatus::ACTIVE,
                    'issued_at' => now(),
                    'released_by' => $actor->id,
                ])->save();

                $reservation->item?->forceFill([
                    'reserved_quantity' => Decimal::sub((string) $reservation->item->reserved_quantity, $shipQuantity, 4),
                    'issued_quantity' => Decimal::add((string) $reservation->item->issued_quantity, $shipQuantity, 4),
                    'fulfillment_status' => Decimal::compare($this->reservationRemainingAfterIssue($reservation, $shipQuantity), '0', 4) <= 0 ? 'shipped' : 'partial_shipped',
                ])->save();
                $issuedAny = true;
            }

            if (! $issuedAny) {
                throw ServiceException::validation('Tidak ada qty reserved yang dapat dikirim.');
            }

            $hasRemainingReservation = $order->reservations()
                ->where('status', StockReservationStatus::ACTIVE->value)
                ->get()
                ->contains(fn (StockReservation $reservation): bool => Decimal::compare($this->reservationRemaining($reservation), '0', 4) > 0);

            if (! $hasRemainingReservation) {
                $order->forceFill(['status' => B2bOrderStatus::SHIPPED, 'shipped_at' => now(), 'internal_note' => $note ?? $order->internal_note])->save();
                $this->logStatus($order, $from, B2bOrderStatus::SHIPPED, $actor, $note ?? 'Order dikirim.');
                $this->message($order, $actor, 'customer', 'Order dikirim.');
            } else {
                $order->forceFill(['internal_note' => $note ?? $order->internal_note])->save();
                $this->message($order, $actor, 'internal', $note ?? 'Sebagian item order dikirim, sisa masih dalam packing.');
            }

            return $order->fresh(['items', 'reservations']);
        });
    }

    public function markReceived(B2bOrder $order, User $actor): B2bOrder
    {
        return DB::transaction(function () use ($order, $actor): B2bOrder {
            $order = B2bOrder::query()->lockForUpdate()->findOrFail($order->id);
            $from = $this->orderStatus($order);
            if ($from !== B2bOrderStatus::SHIPPED) {
                throw ServiceException::validation('Hanya order dikirim yang dapat dikonfirmasi diterima.');
            }

            $order->forceFill(['status' => B2bOrderStatus::RECEIVED, 'received_at' => now()])->save();
            $this->logStatus($order, $from, B2bOrderStatus::RECEIVED, $actor, 'Customer mengonfirmasi order diterima.');

            return $order->fresh(['items', 'reservations']);
        });
    }

    public function releaseReservation(StockReservation $reservation, User $actor, string $reason): StockReservation
    {
        return DB::transaction(function () use ($reservation, $actor, $reason): StockReservation {
            $reservation = StockReservation::query()->with(['product', 'stock.workLocation', 'stock.warehouseLocation', 'item'])->lockForUpdate()->findOrFail($reservation->id);
            if ($this->reservationStatus($reservation) !== StockReservationStatus::ACTIVE) {
                return $reservation;
            }

            $remaining = $this->reservationRemaining($reservation);
            if (Decimal::compare($remaining, '0', 4) > 0) {
                $stock = $reservation->stock;
                $this->inventory->releaseReservation(
                    $reservation->product,
                    $stock->workLocation,
                    $stock->warehouseLocation,
                    $remaining,
                    $actor,
                    ['type' => 'b2b_reservation_release', 'id' => $reservation->id, 'no' => $reservation->order?->number],
                    $reason,
                    "b2b-reservation-{$reservation->id}-manual-release",
                );
                $reservation->item?->forceFill([
                    'reserved_quantity' => Decimal::sub((string) $reservation->item->reserved_quantity, $remaining, 4),
                    'fulfillment_status' => 'released',
                ])->save();
            }

            $reservation->forceFill([
                'quantity_released' => Decimal::add((string) $reservation->quantity_released, $remaining, 4),
                'status' => StockReservationStatus::RELEASED,
                'released_at' => now(),
                'released_by' => $actor->id,
                'reason' => $reason,
            ])->save();

            return $reservation->fresh(['order', 'item', 'product']);
        });
    }

    public function expireReservations(?User $actor = null): int
    {
        $count = 0;
        StockReservation::query()
            ->with('order')
            ->where('status', StockReservationStatus::ACTIVE->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->get()
            ->each(function (StockReservation $reservation) use ($actor, &$count): void {
                $this->releaseExpiredReservation($reservation, $actor);
                $count++;
            });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logStatus(B2bOrder $order, ?B2bOrderStatus $from, B2bOrderStatus $to, ?User $actor, ?string $note = null, array $metadata = []): void
    {
        B2bOrderStatusHistory::query()->create([
            'b2b_order_id' => $order->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->id,
            'note' => $note,
            'metadata' => $metadata,
        ]);

        event(new B2bOrderStatusChanged($order, $from, $to, $actor, $note));
    }

    private function transition(B2bOrder $order, B2bOrderStatus $to, User $actor, string $note): void
    {
        $from = $this->orderStatus($order);
        if ($from === $to) {
            return;
        }

        $order->forceFill(['status' => $to])->save();
        $this->logStatus($order, $from, $to, $actor, $note);
    }

    private function reserveItem(B2bOrder $order, B2bOrderItem $item, string $requiredBaseQuantity, User $actor, Carbon $expiresAt, bool $allowPartial): string
    {
        $remaining = $requiredBaseQuantity;
        $reserved = '0.0000';

        /** @var Collection<int, Stock> $stocks */
        $stocks = Stock::query()
            ->with(['workLocation', 'warehouseLocation', 'product'])
            ->where('product_id', $item->product_id)
            ->whereHas('workLocation', fn ($query) => $query->where('type', 'warehouse'))
            ->orderBy('id')
            ->get();

        foreach ($stocks as $stock) {
            if (Decimal::compare($remaining, '0', 4) <= 0) {
                break;
            }

            $available = (string) $stock->available_quantity;
            if (Decimal::compare($available, '0', 4) <= 0) {
                continue;
            }

            $reserveQty = Decimal::compare($available, $remaining, 4) >= 0 ? $remaining : $available;
            $mutation = $this->inventory->reserve(
                $item->product,
                $stock->workLocation,
                $stock->warehouseLocation,
                $reserveQty,
                $actor,
                ['type' => 'b2b_order_reservation', 'id' => $order->id, 'no' => $order->number],
                'Reserved untuk order B2B.',
                "b2b-order-{$order->id}-item-{$item->id}-stock-{$stock->id}-reserve",
                ['b2b_order_item_id' => $item->id],
            );

            StockReservation::query()->create([
                'b2b_order_id' => $order->id,
                'b2b_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'stock_id' => $mutation->stock_id,
                'work_location_id' => $mutation->work_location_id,
                'warehouse_location_id' => $mutation->warehouse_location_id,
                'quantity_reserved' => $reserveQty,
                'status' => StockReservationStatus::ACTIVE,
                'reserved_at' => now(),
                'expires_at' => $expiresAt,
                'reserved_by' => $actor->id,
                'idempotency_key' => "b2b-order-{$order->id}-item-{$item->id}-stock-{$stock->id}-reserve",
                'metadata' => ['mutation_id' => $mutation->id],
            ]);

            $reserved = Decimal::add($reserved, $reserveQty, 4);
            $remaining = Decimal::sub($remaining, $reserveQty, 4);
        }

        if (Decimal::compare($remaining, '0', 4) > 0 && ! $allowPartial) {
            throw ServiceException::validation("Stok {$item->product_name_snapshot} tidak mencukupi. Shortage {$remaining}.");
        }

        return $reserved;
    }

    private function releaseActiveReservations(B2bOrder $order, User $actor, string $suffix, string $reason): void
    {
        foreach ($order->reservations()->where('status', StockReservationStatus::ACTIVE->value)->get() as $reservation) {
            $this->releaseReservationWithSuffix($reservation, $actor, $suffix, $reason, StockReservationStatus::RELEASED);
        }
    }

    private function releaseExpiredReservation(StockReservation $reservation, ?User $actor): void
    {
        DB::transaction(function () use ($reservation, $actor): void {
            $reservation = StockReservation::query()->with(['product', 'stock.workLocation', 'stock.warehouseLocation', 'item', 'order'])->lockForUpdate()->findOrFail($reservation->id);
            if ($this->reservationStatus($reservation) !== StockReservationStatus::ACTIVE) {
                return;
            }

            $this->releaseReservationWithSuffix($reservation, $actor, 'expired', 'Reservation kedaluwarsa.', StockReservationStatus::EXPIRED);
            $order = $reservation->order;
            if ($order instanceof B2bOrder && $order->reservations()->where('status', StockReservationStatus::ACTIVE->value)->doesntExist()) {
                $from = $this->orderStatus($order);
                $order->forceFill(['status' => B2bOrderStatus::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => 'Reservation kedaluwarsa.'])->save();
                $this->logStatus($order, $from, B2bOrderStatus::CANCELLED, $actor, 'Reservation kedaluwarsa.');
            }
        });
    }

    private function releaseReservationWithSuffix(StockReservation $reservation, ?User $actor, string $suffix, string $reason, StockReservationStatus $status): void
    {
        $remaining = $this->reservationRemaining($reservation);
        if (Decimal::compare($remaining, '0', 4) > 0) {
            $stock = $reservation->stock()->with(['workLocation', 'warehouseLocation'])->firstOrFail();
            $this->inventory->releaseReservation(
                $reservation->product,
                $stock->workLocation,
                $stock->warehouseLocation,
                $remaining,
                $actor,
                ['type' => 'b2b_reservation_'.$suffix, 'id' => $reservation->id, 'no' => $reservation->order?->number],
                $reason,
                "b2b-reservation-{$reservation->id}-{$suffix}",
            );

            $reservation->item?->forceFill([
                'reserved_quantity' => Decimal::sub((string) $reservation->item->reserved_quantity, $remaining, 4),
                'fulfillment_status' => $status === StockReservationStatus::EXPIRED ? 'expired' : 'released',
            ])->save();
        }

        $reservation->forceFill([
            'quantity_released' => Decimal::add((string) $reservation->quantity_released, $remaining, 4),
            'status' => $status,
            'released_at' => now(),
            'released_by' => $actor?->id,
            'reason' => $reason,
        ])->save();
    }

    private function reservationRemaining(StockReservation $reservation): string
    {
        return Decimal::sub(Decimal::sub((string) $reservation->quantity_reserved, (string) $reservation->quantity_released, 4), (string) $reservation->quantity_issued, 4);
    }

    private function reservationRemainingAfterIssue(StockReservation $reservation, string $issueQuantity): string
    {
        return Decimal::sub($this->reservationRemaining($reservation), $issueQuantity, 4);
    }

    private function orderStatus(B2bOrder $order): B2bOrderStatus
    {
        return B2bOrderStatus::from((string) $order->getRawOriginal('status'));
    }

    private function reservationStatus(StockReservation $reservation): StockReservationStatus
    {
        return StockReservationStatus::from((string) $reservation->getRawOriginal('status'));
    }

    private function ensureCustomerCanProceed(B2bOrder $order): void
    {
        $customer = $order->customer;
        if (! $customer || ! $customer->is_active || $customer->getRawOriginal('account_status') !== CustomerStatus::ACTIVE->value || $customer->getRawOriginal('verification_status') !== CustomerStatus::ACTIVE->value) {
            throw ServiceException::validation('Akun pelanggan belum aktif atau sedang diblokir.');
        }
    }

    private function ensureCreditLimit(B2bOrder $order, string $grandTotal): void
    {
        $customer = $order->customer;
        if (! $customer) {
            throw ServiceException::validation('Data pelanggan tidak valid.');
        }

        $afterCredit = Decimal::add((string) $customer->receivable_balance, $grandTotal, 2);
        if ($order->payment_preference === 'credit' && Decimal::compare((string) $customer->credit_limit, '0', 2) > 0 && Decimal::compare($afterCredit, (string) $customer->credit_limit, 2) > 0) {
            throw ServiceException::validation('Order melebihi limit kredit pelanggan.');
        }
    }

    private function message(B2bOrder $order, ?User $actor, string $visibility, string $message): void
    {
        B2bOrderMessage::query()->create([
            'b2b_order_id' => $order->id,
            'user_id' => $actor?->id,
            'visibility' => $visibility,
            'message' => $message,
        ]);
    }
}
