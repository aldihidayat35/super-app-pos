<?php

namespace App\Services\B2B;

use App\Enums\B2bComplaintStatus;
use App\Enums\B2bOrderStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Events\B2B\B2bOrderStatusChanged;
use App\Exceptions\ServiceException;
use App\Models\B2bComplaint;
use App\Models\B2bOrder;
use App\Models\B2bOrderMessage;
use App\Models\B2bOrderStatusHistory;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\ShipmentProof;
use App\Models\User;
use App\Services\Organization\DocumentNumberService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class B2bFulfillmentService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly B2bOrderWorkflowService $workflow,
    ) {}

    public function issueInvoice(B2bOrder $order, User $actor, ?Carbon $dueDate = null): Invoice
    {
        return DB::transaction(function () use ($order, $actor, $dueDate): Invoice {
            $order = B2bOrder::query()->with(['items', 'customer'])->lockForUpdate()->findOrFail($order->id);
            $existing = Invoice::query()->where('b2b_order_id', $order->id)->first();
            if ($existing instanceof Invoice) {
                return $existing->fresh(['items', 'order', 'customer']);
            }

            $status = $this->orderStatus($order);
            if (! in_array($status, [B2bOrderStatus::RESERVED, B2bOrderStatus::INVOICE_READY, B2bOrderStatus::AWAITING_PAYMENT, B2bOrderStatus::APPROVED_CREDIT], true)) {
                throw ServiceException::validation('Invoice hanya dapat dibuat setelah stok order di-reserve.');
            }

            $issueDate = now();
            $customer = $this->requireCustomer($order->customer);
            $dueDate ??= now()->addDays((int) $customer->payment_term_days);
            $subtotal = Decimal::normalize($order->subtotal_amount, 2);
            $discount = Decimal::normalize($order->discount_amount, 2);
            $shipping = Decimal::normalize($order->shipping_cost_amount, 2);
            $tax = Decimal::normalize($order->tax_amount, 2);
            $total = Decimal::add(Decimal::sub($subtotal, $discount, 2), Decimal::add($shipping, $tax, 2), 2);

            $invoice = Invoice::query()->create([
                'number' => $this->numbers->next('invoice'),
                'source_type' => 'b2b_order',
                'b2b_order_id' => $order->id,
                'customer_id' => $customer->id,
                'status' => InvoiceStatus::ISSUED,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'shipping_amount' => $shipping,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => '0.00',
                'outstanding_amount' => $total,
                'issued_at' => $issueDate,
                'created_by' => $actor->id,
                'issued_by' => $actor->id,
                'notes' => 'Invoice dibuat dari order B2B '.$order->number,
            ]);

            foreach ($order->items as $item) {
                $quantity = Decimal::normalize($item->approved_quantity ?? $item->quantity, 4);
                $invoice->items()->create([
                    'b2b_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => $item->product_name_snapshot,
                    'unit_name_snapshot' => $item->unit_name_snapshot,
                    'quantity' => $quantity,
                    'unit_price' => $item->selected_price,
                    'discount_amount' => $item->discount_amount,
                    'tax_amount' => $item->tax_amount,
                    'line_total' => $item->line_total,
                ]);
            }

            $customer->forceFill([
                'receivable_balance' => Decimal::add((string) $customer->receivable_balance, $total, 2),
            ])->save();

            $nextStatus = $order->payment_preference === 'credit' ? B2bOrderStatus::APPROVED_CREDIT : B2bOrderStatus::AWAITING_PAYMENT;
            $this->transitionOrder($order, $nextStatus, $actor, 'Invoice '.$invoice->number.' diterbitkan.');

            return $invoice->fresh(['items', 'order', 'customer']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(Invoice $invoice, User $actor, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $actor, $data): Payment {
            $invoice = Invoice::query()->with('customer')->lockForUpdate()->findOrFail($invoice->id);
            if ($this->invoiceStatus($invoice) === InvoiceStatus::CANCELLED) {
                throw ServiceException::validation('Invoice dibatalkan tidak dapat dibayar.');
            }

            $idempotencyKey = filled($data['idempotency_key'] ?? null) ? (string) $data['idempotency_key'] : null;
            if ($idempotencyKey !== null) {
                $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing instanceof Payment) {
                    return $existing->fresh(['allocations.invoice', 'customer']);
                }
            }

            $amount = Decimal::normalize($data['amount'], 2);
            $allowDeposit = (bool) ($data['allow_deposit'] ?? false);
            if (! $allowDeposit && Decimal::compare($amount, (string) $invoice->outstanding_amount, 2) > 0) {
                throw ServiceException::validation('Nominal pembayaran melebihi sisa tagihan.');
            }

            $payment = Payment::query()->create([
                'number' => $this->numbers->next('payment'),
                'customer_id' => $invoice->customer_id,
                'method' => $data['method'],
                'status' => PaymentStatus::PENDING_VERIFICATION,
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'bank_name' => $data['bank_name'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
                'payer_name' => $data['payer_name'] ?? $actor->name,
                'received_by' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'notes' => $data['notes'] ?? null,
            ]);
            $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $amount]);

            return $payment->fresh(['allocations.invoice', 'customer']);
        });
    }

    public function verifyPayment(Payment $payment, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $actor): Payment {
            $payment = Payment::query()->with(['allocations.invoice.order', 'customer'])->lockForUpdate()->findOrFail($payment->id);
            $status = $this->paymentStatus($payment);
            if ($status === PaymentStatus::VERIFIED) {
                return $payment;
            }

            if ($status !== PaymentStatus::PENDING_VERIFICATION) {
                throw ServiceException::validation('Hanya pembayaran menunggu verifikasi yang dapat diproses.');
            }

            foreach ($payment->allocations as $allocation) {
                $invoice = Invoice::query()->with('order.customer')->lockForUpdate()->findOrFail($allocation->invoice_id);
                if (Decimal::compare((string) $allocation->amount, (string) $invoice->outstanding_amount, 2) > 0) {
                    throw ServiceException::validation('Alokasi pembayaran melebihi sisa invoice.');
                }

                $paid = Decimal::add((string) $invoice->paid_amount, (string) $allocation->amount, 2);
                $outstanding = Decimal::sub((string) $invoice->total_amount, $paid, 2);
                $invoiceStatus = Decimal::compare($outstanding, '0', 2) <= 0 ? InvoiceStatus::PAID : InvoiceStatus::PARTIAL;
                $invoice->forceFill([
                    'paid_amount' => $paid,
                    'outstanding_amount' => $outstanding,
                    'status' => $invoiceStatus,
                    'paid_at' => $invoiceStatus === InvoiceStatus::PAID ? now() : $invoice->paid_at,
                ])->save();

                $customer = $this->requireCustomer($invoice->customer);
                $customer->forceFill([
                    'receivable_balance' => Decimal::sub((string) $customer->receivable_balance, (string) $allocation->amount, 2),
                ])->save();

                if ($invoiceStatus === InvoiceStatus::PAID && $invoice->order instanceof B2bOrder) {
                    $this->transitionOrder($invoice->order, B2bOrderStatus::APPROVED_CREDIT, $actor, 'Pembayaran invoice '.$invoice->number.' telah diverifikasi.');
                }
            }

            $payment->forceFill([
                'status' => PaymentStatus::VERIFIED,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ])->save();

            return $payment->fresh(['allocations.invoice.order', 'customer']);
        });
    }

    public function rejectPayment(Payment $payment, User $actor, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($this->paymentStatus($payment) === PaymentStatus::VERIFIED) {
                throw ServiceException::validation('Pembayaran terverifikasi tidak dapat ditolak ulang.');
            }

            $payment->forceFill([
                'status' => PaymentStatus::REJECTED,
                'verified_by' => $actor->id,
                'rejected_at' => now(),
                'reject_reason' => $reason,
            ])->save();

            return $payment->fresh(['allocations.invoice', 'customer']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createShipment(B2bOrder $order, User $actor, array $data): Shipment
    {
        return DB::transaction(function () use ($order, $actor, $data): Shipment {
            $order = B2bOrder::query()->with(['items', 'reservations', 'customer', 'address'])->lockForUpdate()->findOrFail($order->id);
            $status = $this->orderStatus($order);
            if ($status === B2bOrderStatus::AWAITING_PAYMENT) {
                throw ServiceException::validation('Order masih menunggu pembayaran, belum dapat dibuat shipment.');
            }

            if (in_array($status, [B2bOrderStatus::RESERVED, B2bOrderStatus::INVOICE_READY, B2bOrderStatus::APPROVED_CREDIT], true)) {
                $order = $this->workflow->pack($order, $actor, 'Order masuk packing untuk shipment.');
                $order = $order->fresh(['items', 'reservations', 'customer', 'address']);
            }

            if ($this->orderStatus($order) !== B2bOrderStatus::PACKING) {
                throw ServiceException::validation('Shipment hanya dapat dibuat untuk order yang sedang packing.');
            }

            $plannedQuantities = is_array($data['planned_quantities'] ?? null) ? $data['planned_quantities'] : [];
            $originReservation = $order->reservations()->orderBy('id')->first();
            $shipment = Shipment::query()->create([
                'number' => $this->numbers->next('shipment'),
                'b2b_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'origin_work_location_id' => $originReservation?->work_location_id,
                'destination_address_id' => $order->customer_address_id,
                'status' => ShipmentStatus::PACKING,
                'delivery_method' => $data['delivery_method'] ?? $order->delivery_method,
                'courier_name' => $data['courier_name'] ?? $order->courier_name,
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_no' => $data['vehicle_no'] ?? null,
                'tracking_no' => $data['tracking_no'] ?? null,
                'scheduled_date' => $data['scheduled_date'] ?? now()->toDateString(),
                'shipping_cost_amount' => Decimal::normalize($data['shipping_cost_amount'] ?? $order->shipping_cost_amount ?? 0, 2),
                'created_by' => $actor->id,
            ]);

            $hasItem = false;
            foreach ($order->items as $item) {
                $planned = Decimal::normalize($plannedQuantities[$item->id] ?? $item->reserved_quantity, 4);
                if (Decimal::compare($planned, '0', 4) <= 0) {
                    continue;
                }

                if (Decimal::compare($planned, (string) $item->reserved_quantity, 4) > 0) {
                    throw ServiceException::validation('Qty shipment tidak boleh melebihi saldo reserved item.');
                }

                $shipment->items()->create([
                    'b2b_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity_planned' => $planned,
                    'status' => 'planned',
                ]);
                $hasItem = true;
            }

            if (! $hasItem) {
                throw ServiceException::validation('Shipment harus memiliki minimal satu item.');
            }

            return $shipment->fresh(['items.orderItem', 'order.customer']);
        });
    }

    public function postShipment(Shipment $shipment, User $actor): Shipment
    {
        return DB::transaction(function () use ($shipment, $actor): Shipment {
            $shipment = Shipment::query()->with(['items', 'order'])->lockForUpdate()->findOrFail($shipment->id);
            $status = $this->shipmentStatus($shipment);
            if ($status === ShipmentStatus::SHIPPED || $status === ShipmentStatus::DELIVERED) {
                return $shipment;
            }

            if ($status !== ShipmentStatus::PACKING && $status !== ShipmentStatus::READY) {
                throw ServiceException::validation('Shipment tidak dapat diposting pada status saat ini.');
            }

            $shipQuantities = [];
            foreach ($shipment->items as $item) {
                $shipQuantities[$item->b2b_order_item_id] = $item->quantity_planned;
                $item->forceFill([
                    'quantity_shipped' => $item->quantity_planned,
                    'status' => 'shipped',
                ])->save();
            }

            $this->workflow->ship($shipment->order, $actor, 'Shipment '.$shipment->number.' diposting.', $shipQuantities, 'shipment-'.$shipment->id);
            $shipment->forceFill([
                'status' => ShipmentStatus::SHIPPED,
                'shipped_at' => now(),
                'shipped_by' => $actor->id,
            ])->save();

            return $shipment->fresh(['items.orderItem', 'order', 'proofs']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeShipmentProof(Shipment $shipment, User $actor, array $data): ShipmentProof
    {
        return DB::transaction(function () use ($shipment, $actor, $data): ShipmentProof {
            $shipment = Shipment::query()->with(['items', 'order'])->lockForUpdate()->findOrFail($shipment->id);
            $proof = $shipment->proofs()->create([
                'type' => $data['type'],
                'file_path' => $data['file_path'] ?? null,
                'receiver_name' => $data['receiver_name'] ?? null,
                'signature_data' => $data['signature_data'] ?? null,
                'notes' => $data['notes'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'uploaded_by' => $actor->id,
            ]);

            if ($data['type'] === 'delivery') {
                $status = $this->shipmentStatus($shipment);
                if ($status !== ShipmentStatus::SHIPPED && $status !== ShipmentStatus::DELIVERED) {
                    throw ServiceException::validation('Bukti terima hanya dapat diunggah setelah shipment dikirim.');
                }

                foreach ($shipment->items as $item) {
                    $item->forceFill([
                        'quantity_delivered' => $item->quantity_shipped,
                        'status' => 'delivered',
                    ])->save();
                }

                $shipment->forceFill([
                    'status' => ShipmentStatus::DELIVERED,
                    'delivered_at' => now(),
                    'receiver_name' => $data['receiver_name'] ?? $shipment->receiver_name,
                    'delivery_note' => $data['notes'] ?? $shipment->delivery_note,
                ])->save();

                $order = $shipment->order;
                if ($order instanceof B2bOrder && $order->shipments()->whereNot('status', ShipmentStatus::DELIVERED->value)->doesntExist()) {
                    $this->transitionOrder($order, B2bOrderStatus::RECEIVED, $actor, 'Proof of delivery shipment '.$shipment->number.' diterima.');
                }
            }

            if ($data['type'] === 'failed_delivery') {
                $shipment->forceFill([
                    'status' => ShipmentStatus::FAILED,
                    'failed_at' => now(),
                    'failure_reason' => $data['notes'] ?? 'Pengiriman gagal.',
                ])->save();
            }

            return $proof->fresh('shipment');
        });
    }

    public function confirmCustomerReceived(Shipment $shipment, User $actor): Shipment
    {
        return DB::transaction(function () use ($shipment, $actor): Shipment {
            $shipment = Shipment::query()->with('order')->lockForUpdate()->findOrFail($shipment->id);
            if ($this->shipmentStatus($shipment) !== ShipmentStatus::DELIVERED) {
                throw ServiceException::validation('Hanya shipment terkirim yang dapat dikonfirmasi selesai.');
            }

            if ($shipment->order instanceof B2bOrder) {
                $this->transitionOrder($shipment->order, B2bOrderStatus::COMPLETED, $actor, 'Customer mengonfirmasi shipment '.$shipment->number.' selesai.');
            }

            return $shipment->fresh(['order', 'items', 'proofs']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitComplaint(Customer $customer, User $actor, array $data): B2bComplaint
    {
        return DB::transaction(function () use ($customer, $actor, $data): B2bComplaint {
            $orderId = $data['b2b_order_id'] ?? null;
            if ($orderId !== null && ! B2bOrder::query()->where('id', $orderId)->where('customer_id', $customer->id)->exists()) {
                throw ServiceException::validation('Order tidak valid untuk pelanggan ini.');
            }

            $shipmentId = $data['shipment_id'] ?? null;
            if ($shipmentId !== null && ! Shipment::query()->where('id', $shipmentId)->where('customer_id', $customer->id)->exists()) {
                throw ServiceException::validation('Shipment tidak valid untuk pelanggan ini.');
            }

            return B2bComplaint::query()->create([
                'number' => $this->numbers->next('complaint'),
                'customer_id' => $customer->id,
                'b2b_order_id' => $orderId,
                'shipment_id' => $shipmentId,
                'b2b_order_item_id' => $data['b2b_order_item_id'] ?? null,
                'type' => $data['type'],
                'requested_solution' => $data['requested_solution'] ?? null,
                'quantity' => $data['quantity'] ?? null,
                'status' => B2bComplaintStatus::SUBMITTED,
                'evidence_path' => $data['evidence_path'] ?? null,
                'message' => $data['message'],
                'created_by' => $actor->id,
            ]);
        });
    }

    private function transitionOrder(B2bOrder $order, B2bOrderStatus $to, User $actor, string $note): void
    {
        $from = $this->orderStatus($order);
        if ($from === $to) {
            return;
        }

        $order->forceFill(['status' => $to])->save();
        B2bOrderStatusHistory::query()->create([
            'b2b_order_id' => $order->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor->id,
            'note' => $note,
        ]);
        B2bOrderMessage::query()->create([
            'b2b_order_id' => $order->id,
            'user_id' => $actor->id,
            'visibility' => 'customer',
            'message' => $note,
        ]);
        event(new B2bOrderStatusChanged($order, $from, $to, $actor, $note));
    }

    private function orderStatus(B2bOrder $order): B2bOrderStatus
    {
        return B2bOrderStatus::from((string) $order->getRawOriginal('status'));
    }

    private function invoiceStatus(Invoice $invoice): InvoiceStatus
    {
        return InvoiceStatus::from((string) $invoice->getRawOriginal('status'));
    }

    private function paymentStatus(Payment $payment): PaymentStatus
    {
        return PaymentStatus::from((string) $payment->getRawOriginal('status'));
    }

    private function shipmentStatus(Shipment $shipment): ShipmentStatus
    {
        return ShipmentStatus::from((string) $shipment->getRawOriginal('status'));
    }

    private function requireCustomer(?Customer $customer): Customer
    {
        if (! $customer instanceof Customer) {
            throw ServiceException::validation('Data pelanggan tidak valid.');
        }

        return $customer;
    }
}
