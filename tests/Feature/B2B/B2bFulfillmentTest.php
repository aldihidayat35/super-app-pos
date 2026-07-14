<?php

namespace Tests\Feature\B2B;

use App\Enums\B2bOrderStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductPriceStatus;
use App\Enums\ShipmentStatus;
use App\Models\B2bComplaint;
use App\Models\B2bOrder;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Shipment;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class B2bFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private User $customerUser;

    private User $otherCustomerUser;

    private User $warehouseHead;

    private Customer $customer;

    private Customer $otherCustomer;

    private Product $product;

    private Unit $unit;

    private WorkLocation $warehouseLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake();

        $this->customerUser = User::factory()->create(['username' => 'b2b-p20']);
        $this->customerUser->assignRole(Role::findOrCreate('langganan_owner'));
        $this->otherCustomerUser = User::factory()->create(['username' => 'b2b-other-p20']);
        $this->otherCustomerUser->assignRole(Role::findOrCreate('langganan_owner'));
        $this->warehouseHead = User::factory()->create(['username' => 'warehouse-p20']);
        $this->warehouseHead->assignRole(Role::findOrCreate('kepala_gudang'));

        $this->customer = Customer::factory()->create(['price_category' => 'grosir', 'minimum_order' => 0, 'credit_limit' => 1000000, 'receivable_balance' => 0, 'payment_term_days' => 14]);
        $this->otherCustomer = Customer::factory()->create(['price_category' => 'grosir', 'minimum_order' => 0, 'credit_limit' => 1000000, 'receivable_balance' => 0]);
        $this->customer->users()->attach($this->customerUser->id, ['role' => 'langganan_owner', 'is_active' => true]);
        $this->otherCustomer->users()->attach($this->otherCustomerUser->id, ['role' => 'langganan_owner', 'is_active' => true]);
        CustomerAddress::query()->create(['customer_id' => $this->customer->id, 'label' => 'Utama', 'address' => 'Alamat B2B', 'is_primary' => true, 'primary_scope' => 'primary']);
        CustomerAddress::query()->create(['customer_id' => $this->otherCustomer->id, 'label' => 'Utama', 'address' => 'Alamat Lain', 'is_primary' => true, 'primary_scope' => 'primary']);

        $this->unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $this->product = Product::factory()->create(['base_unit_id' => $this->unit->id, 'name' => 'Produk Fulfillment', 'cost_price' => 5000, 'minimum_price' => 6000, 'minimum_order' => 1]);
        ProductPrice::query()->create([
            'product_id' => $this->product->id,
            'channel' => 'b2b',
            'price_ring' => 'grosir',
            'customer_category' => 'grosir',
            'recommended_price' => 10000,
            'minimum_qty' => 1,
            'status' => ProductPriceStatus::ACTIVE,
        ]);
        $this->warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        app(InventoryService::class)->receive($this->product, $this->warehouseLocation, null, 10, $this->warehouseHead, ['type' => 'test_seed'], 'Seed stok test.');
    }

    public function test_p20_pages_can_be_opened(): void
    {
        $order = $this->reservedOrder('credit');
        $invoice = $this->issueInvoice($order);
        $shipment = $this->createShipment($order, 1);

        $this->actingAs($this->customerUser)->get(route('invoices.index'))->assertOk()->assertSee($invoice->number);
        $this->actingAs($this->customerUser)->get(route('invoices.show', $invoice))->assertOk()->assertSee('Detail Invoice');
        $this->actingAs($this->customerUser)->get(route('payments.create', ['invoice_id' => $invoice->id]))->assertOk()->assertSee('Upload / Entri Pembayaran');
        $this->actingAs($this->warehouseHead)->get(route('shipments.index'))->assertOk()->assertSee('Daftar Pengiriman');
        $this->actingAs($this->warehouseHead)->get(route('shipments.show', $shipment))->assertOk()->assertSee($shipment->number);
        $this->actingAs($this->warehouseHead)->get(route('shipments.proof', $shipment))->assertOk()->assertSee('Bukti Pengiriman');
        $this->actingAs($this->customerUser)->get(route('langganan.shipments.show', $shipment))->assertOk()->assertSee('Tracking Pengiriman');
        $this->actingAs($this->customerUser)->get(route('langganan.complaints.index'))->assertOk()->assertSee('Komplain dan Retur B2B');
    }

    public function test_partial_payment_full_payment_and_duplicate_verification_are_idempotent(): void
    {
        $order = $this->reservedOrder('transfer', 3);
        $invoice = $this->issueInvoice($order);

        $this->assertDatabaseHas('b2b_orders', ['id' => $order->id, 'status' => B2bOrderStatus::AWAITING_PAYMENT->value]);
        $this->assertSame('30000.00', (string) $this->customer->fresh()->receivable_balance);

        $payment = $this->storePayment($invoice, 10000);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::PENDING_VERIFICATION->value]);
        $this->actingAs($this->warehouseHead)->post(route('payments.verify.store', $payment), ['decision' => 'approve'])->assertRedirect();
        $this->actingAs($this->warehouseHead)->post(route('payments.verify.store', $payment), ['decision' => 'approve'])->assertRedirect();

        $invoice = $invoice->fresh();
        $this->assertSame(InvoiceStatus::PARTIAL, $invoice->status);
        $this->assertSame('10000.00', (string) $invoice->paid_amount);
        $this->assertSame('20000.00', (string) $invoice->outstanding_amount);
        $this->assertSame('20000.00', (string) $this->customer->fresh()->receivable_balance);

        $second = $this->storePayment($invoice, 20000, 'second-payment');
        $this->actingAs($this->warehouseHead)->post(route('payments.verify.store', $second), ['decision' => 'approve'])->assertRedirect();

        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
        $this->assertDatabaseHas('b2b_orders', ['id' => $order->id, 'status' => B2bOrderStatus::APPROVED_CREDIT->value]);
        $this->assertSame('0.00', (string) $this->customer->fresh()->receivable_balance);
    }

    public function test_overpayment_is_rejected_before_verification(): void
    {
        $invoice = $this->issueInvoice($this->reservedOrder('transfer', 2));

        $this->actingAs($this->customerUser)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 999999,
            'method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('payment');
    }

    public function test_shipment_post_pod_customer_confirm_and_complaint(): void
    {
        $order = $this->reservedOrder('credit', 2);
        $this->issueInvoice($order);
        $shipment = $this->createShipment($order, 2);

        $this->actingAs($this->warehouseHead)->post(route('shipments.post', $shipment))->assertRedirect();
        $stock = Stock::query()->firstOrFail()->fresh();
        $this->assertSame('8.0000', (string) $stock->quantity_on_hand);
        $this->assertSame('0.0000', (string) $stock->quantity_reserved);
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => ShipmentStatus::SHIPPED->value]);

        $this->actingAs($this->warehouseHead)->post(route('shipments.proof.store', $shipment), [
            'type' => 'delivery',
            'receiver_name' => 'Pak Budi',
            'notes' => 'Diterima lengkap.',
        ])->assertRedirect();
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'status' => ShipmentStatus::DELIVERED->value, 'receiver_name' => 'Pak Budi']);
        $this->assertDatabaseHas('b2b_orders', ['id' => $order->id, 'status' => B2bOrderStatus::RECEIVED->value]);

        $this->actingAs($this->customerUser)->post(route('langganan.shipments.confirm', $shipment))->assertRedirect();
        $this->assertDatabaseHas('b2b_orders', ['id' => $order->id, 'status' => B2bOrderStatus::COMPLETED->value]);

        $this->actingAs($this->customerUser)->post(route('langganan.complaints.store'), [
            'b2b_order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'type' => 'pecah',
            'requested_solution' => 'diskusi',
            'quantity' => 1,
            'message' => 'Ada satu dus penyok, mohon dicek.',
        ])->assertRedirect();
        $this->assertSame(1, B2bComplaint::query()->where('customer_id', $this->customer->id)->count());
    }

    public function test_customer_scope_and_signed_proof_are_enforced(): void
    {
        $invoice = $this->issueInvoice($this->reservedOrder('transfer', 1));
        $payment = $this->storePayment($invoice, 10000, 'proof-payment');

        $this->actingAs($this->otherCustomerUser)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->actingAs($this->customerUser)->get(route('payments.proof', $payment))->assertForbidden();

        $signed = URL::temporarySignedRoute('payments.proof', now()->addMinutes(5), $payment);
        $this->actingAs($this->customerUser)->get($signed)->assertOk();
    }

    private function reservedOrder(string $paymentPreference = 'credit', int $quantity = 2): B2bOrder
    {
        $this->actingAs($this->customerUser)->post(route('langganan.keranjang.add'), [
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity' => $quantity,
        ])->assertRedirect();

        $this->actingAs($this->customerUser)->post(route('langganan.checkout.store'), [
            'customer_address_id' => $this->customer->addresses()->firstOrFail()->id,
            'delivery_method' => 'courier',
            'payment_preference' => $paymentPreference,
            'terms_accepted' => 1,
            'idempotency_key' => 'p20-'.str()->uuid()->toString(),
        ])->assertRedirect();

        $order = B2bOrder::query()->where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $order), [
            'approved_quantities' => [$order->items()->firstOrFail()->id => $quantity],
        ])->assertRedirect();

        return $order->fresh(['items', 'reservations']);
    }

    private function issueInvoice(B2bOrder $order): Invoice
    {
        $this->actingAs($this->warehouseHead)->post(route('invoices.issue-b2b', $order))->assertRedirect();

        return Invoice::query()->where('b2b_order_id', $order->id)->firstOrFail();
    }

    private function storePayment(Invoice $invoice, int $amount, string $reference = 'first-payment'): Payment
    {
        $this->actingAs($this->customerUser)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
            'reference_no' => $reference,
            'proof' => UploadedFile::fake()->image($reference.'.jpg'),
            'idempotency_key' => $reference,
        ])->assertRedirect();

        return Payment::query()->where('reference_no', $reference)->firstOrFail();
    }

    private function createShipment(B2bOrder $order, int $quantity): Shipment
    {
        $item = $order->fresh('items')->items->first();
        $this->actingAs($this->warehouseHead)->post(route('shipments.store'), [
            'b2b_order_id' => $order->id,
            'delivery_method' => 'courier',
            'courier_name' => 'Kurir Internal',
            'tracking_no' => 'RESI-P20',
            'scheduled_date' => now()->toDateString(),
            'planned_quantities' => [$item?->id => $quantity],
        ])->assertRedirect();

        return Shipment::query()->where('b2b_order_id', $order->id)->latest('id')->firstOrFail();
    }
}
