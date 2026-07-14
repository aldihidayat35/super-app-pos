<?php

namespace Tests\Feature\B2B;

use App\Enums\B2bOrderStatus;
use App\Enums\CustomerStatus;
use App\Enums\ProductPriceStatus;
use App\Enums\StockReservationStatus;
use App\Models\B2bOrder;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class B2bOrderReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $customerUser;

    private User $warehouseHead;

    private Customer $customer;

    private Product $product;

    private Unit $unit;

    private WorkLocation $warehouseLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->customerUser = User::factory()->create(['username' => 'b2b-reserve']);
        $this->customerUser->assignRole(Role::findOrCreate('langganan_owner'));
        $this->warehouseHead = User::factory()->create(['username' => 'warehouse-head']);
        $this->warehouseHead->assignRole(Role::findOrCreate('kepala_gudang'));

        $this->customer = Customer::factory()->create(['price_category' => 'grosir', 'minimum_order' => 0, 'credit_limit' => 1000000, 'receivable_balance' => 0]);
        $this->customer->users()->attach($this->customerUser->id, ['role' => 'langganan_owner', 'is_active' => true]);
        CustomerAddress::query()->create(['customer_id' => $this->customer->id, 'label' => 'Utama', 'address' => 'Alamat B2B', 'is_primary' => true, 'primary_scope' => 'primary']);

        $this->unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $this->product = Product::factory()->create(['base_unit_id' => $this->unit->id, 'name' => 'Produk Reserved', 'cost_price' => 5000, 'minimum_price' => 6000, 'minimum_order' => 1]);
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
        app(InventoryService::class)->receive($this->product, $this->warehouseLocation, null, 5, $this->warehouseHead, ['type' => 'test_seed'], 'Seed stok test.');
    }

    public function test_p19_pages_can_be_opened(): void
    {
        $order = $this->createOrder(2);

        $this->actingAs($this->customerUser)->get(route('langganan.checkout.show'))->assertOk()->assertSee('Checkout Order B2B');
        $this->actingAs($this->customerUser)->get(route('langganan.orders.index'))->assertOk()->assertSee($order->number);
        $this->actingAs($this->customerUser)->get(route('langganan.orders.show', $order))->assertOk()->assertSee('Timeline Status');
        $this->actingAs($this->warehouseHead)->get(route('warehouse.b2b-orders.index'))->assertOk()->assertSee('Antrian Order Gudang');
        $this->actingAs($this->warehouseHead)->get(route('warehouse.b2b-orders.review', $order))->assertOk()->assertSee('Review dan Reserve');
        $this->actingAs($this->warehouseHead)->get(route('warehouse.reservations.index'))->assertOk()->assertSee('Monitor Reserved Stock');
    }

    public function test_reserve_then_customer_cancel_releases_stock(): void
    {
        $order = $this->createOrder(3);

        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $order), [
            'approved_quantities' => [$order->items()->firstOrFail()->id => 3],
        ])->assertRedirect();

        $this->assertDatabaseHas('b2b_orders', ['id' => $order->id, 'status' => B2bOrderStatus::RESERVED->value]);
        $this->assertDatabaseHas('stock_reservations', ['b2b_order_id' => $order->id, 'quantity_reserved' => 3, 'status' => StockReservationStatus::ACTIVE->value]);
        $this->assertSame('3.0000', (string) Stock::query()->firstOrFail()->quantity_reserved);

        $this->actingAs($this->customerUser)->post(route('langganan.orders.cancel', $order), ['reason' => 'Salah input qty'])->assertRedirect();

        $this->assertDatabaseHas('b2b_orders', ['id' => $order->id, 'status' => B2bOrderStatus::CANCELLED->value]);
        $this->assertDatabaseHas('stock_reservations', ['b2b_order_id' => $order->id, 'status' => StockReservationStatus::RELEASED->value]);
        $this->assertSame('0.0000', (string) Stock::query()->firstOrFail()->fresh()->quantity_reserved);
    }

    public function test_partial_availability_can_be_reserved_with_shortage(): void
    {
        $order = $this->createOrder(8);
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $order), [
            'approved_quantities' => [$item->id => 8],
        ])->assertSessionHasErrors('review');

        $this->assertDatabaseMissing('stock_reservations', ['b2b_order_id' => $order->id]);

        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $order), [
            'approved_quantities' => [$item->id => 8],
            'allow_partial' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('b2b_order_items', [
            'id' => $item->id,
            'reserved_quantity' => 5,
            'shortage_quantity' => 3,
            'fulfillment_status' => 'partial_reserved',
        ]);
    }

    public function test_duplicate_reserve_does_not_double_allocate(): void
    {
        $order = $this->createOrder(2);
        $payload = ['approved_quantities' => [$order->items()->firstOrFail()->id => 2]];

        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $order), $payload)->assertRedirect();
        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $order), $payload)->assertRedirect();

        $this->assertSame(1, StockReservation::query()->where('b2b_order_id', $order->id)->count());
        $this->assertSame('2.0000', (string) Stock::query()->firstOrFail()->fresh()->quantity_reserved);
    }

    public function test_ship_converts_reservation_to_issue_without_double_stock_drop(): void
    {
        $order = $this->createOrder(2);
        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $order), [
            'approved_quantities' => [$order->items()->firstOrFail()->id => 2],
        ]);

        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.pack', $order))->assertRedirect();
        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.ship', $order))->assertRedirect();

        $stock = Stock::query()->firstOrFail()->fresh();
        $this->assertSame('3.0000', (string) $stock->quantity_on_hand);
        $this->assertSame('0.0000', (string) $stock->quantity_reserved);
        $this->assertDatabaseHas('b2b_orders', ['id' => $order->id, 'status' => B2bOrderStatus::SHIPPED->value]);
        $this->assertDatabaseHas('stock_reservations', ['b2b_order_id' => $order->id, 'status' => StockReservationStatus::CONVERTED->value, 'quantity_issued' => 2]);
    }

    public function test_blocked_customer_invalid_ship_and_reservation_expiry(): void
    {
        $blocked = $this->createOrder(1);
        $this->customer->forceFill(['account_status' => CustomerStatus::FROZEN])->save();
        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $blocked), [
            'approved_quantities' => [$blocked->items()->firstOrFail()->id => 1],
        ])->assertSessionHasErrors('review');
        $this->customer->forceFill(['account_status' => CustomerStatus::ACTIVE])->save();

        $order = $this->createOrder(1, 'expiry-test');
        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.ship', $order))->assertSessionHasErrors('ship');
        $this->actingAs($this->warehouseHead)->post(route('warehouse.b2b-orders.reserve', $order), [
            'approved_quantities' => [$order->items()->firstOrFail()->id => 1],
            'reservation_expires_at' => now()->addSecond()->toDateTimeLocalString(),
        ])->assertRedirect();

        $reservation = StockReservation::query()->where('b2b_order_id', $order->id)->firstOrFail();
        $reservation->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->actingAs($this->warehouseHead)->post(route('warehouse.reservations.expire'))->assertRedirect();

        $this->assertDatabaseHas('stock_reservations', ['id' => $reservation->id, 'status' => StockReservationStatus::EXPIRED->value]);
        $this->assertDatabaseHas('b2b_orders', ['id' => $order->id, 'status' => B2bOrderStatus::CANCELLED->value]);
    }

    private function createOrder(int $quantity, string $idempotencyKey = 'checkout-default'): B2bOrder
    {
        $this->actingAs($this->customerUser)->post(route('langganan.keranjang.add'), [
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity' => $quantity,
        ])->assertRedirect();

        $this->actingAs($this->customerUser)->post(route('langganan.checkout.store'), [
            'customer_address_id' => $this->customer->addresses()->firstOrFail()->id,
            'delivery_method' => 'courier',
            'payment_preference' => 'credit',
            'terms_accepted' => 1,
            'idempotency_key' => $idempotencyKey.'-'.$quantity.'-'.str()->uuid()->toString(),
        ])->assertRedirect();

        return B2bOrder::query()->where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
    }
}
