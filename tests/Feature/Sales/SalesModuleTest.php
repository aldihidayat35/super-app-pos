<?php

namespace Tests\Feature\Sales;

use App\Enums\B2bOrderStatus;
use App\Enums\ProductPriceStatus;
use App\Models\B2bOrder;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SalesTarget;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Sales\SalesPerformanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $salesA;

    private User $salesB;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->salesA = User::factory()->create(['name' => 'Sales Aldi']);
        $this->salesA->assignRole(Role::findByName('sales'));
        $this->salesB = User::factory()->create(['name' => 'Sales Budi']);
        $this->salesB->assignRole(Role::findByName('sales'));

        $this->customerA = Customer::factory()->create(['business_name' => 'Customer Aldi', 'sales_user_id' => $this->salesA->id]);
        $this->customerB = Customer::factory()->create(['business_name' => 'Customer Budi', 'sales_user_id' => $this->salesB->id]);
    }

    public function test_role_sales_hanya_mendapat_permission_yang_diperlukan(): void
    {
        $this->assertTrue($this->salesA->can('sales.orders.create'));
        $this->assertTrue($this->salesA->can('sales.stock.view'));
        $this->assertFalse($this->salesA->can('stock.create'));
        $this->assertFalse($this->salesA->can('stock_adjustments.create'));
        $this->assertFalse($this->salesA->can('sales.performance.view'));
    }

    public function test_sales_hanya_dapat_melihat_customer_dan_order_miliknya(): void
    {
        $ownOrder = $this->order($this->salesA, $this->customerA, B2bOrderStatus::PENDING_CONFIRMATION, '10000');
        $otherOrder = $this->order($this->salesB, $this->customerB, B2bOrderStatus::PENDING_CONFIRMATION, '20000');

        $this->actingAs($this->salesA)->get(route('sales.customers.index'))
            ->assertOk()
            ->assertSee('Customer Aldi')
            ->assertDontSee('Customer Budi');

        $this->actingAs($this->salesA)->get(route('sales.customers.show', $this->customerB))->assertForbidden();
        $this->actingAs($this->salesA)->get(route('sales.orders.show', $ownOrder))->assertOk();
        $this->actingAs($this->salesA)->get(route('sales.orders.show', $otherOrder))->assertForbidden();
    }

    public function test_sales_dapat_membuat_order_melalui_business_logic_b2b(): void
    {
        $unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'minimum_order' => 1, 'minimum_price' => 8000]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'channel' => 'b2b',
            'price_ring' => 'grosir',
            'customer_category' => 'grosir',
            'recommended_price' => 10000,
            'minimum_qty' => 1,
            'status' => ProductPriceStatus::ACTIVE,
        ]);
        $address = CustomerAddress::query()->create([
            'customer_id' => $this->customerA->id,
            'label' => 'Utama',
            'address' => 'Jl. Customer A',
            'is_primary' => true,
            'primary_scope' => 'primary',
        ]);

        $this->actingAs($this->salesA)->post(route('sales.orders.store'), [
            'customer_id' => $this->customerA->id,
            'customer_address_id' => $address->id,
            'delivery_method' => 'courier',
            'payment_preference' => 'credit',
            'terms_accepted' => 1,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertRedirect();

        $this->assertDatabaseHas('b2b_orders', [
            'customer_id' => $this->customerA->id,
            'sales_user_id' => $this->salesA->id,
            'requested_by' => $this->salesA->id,
            'status' => B2bOrderStatus::PENDING_CONFIRMATION->value,
            'grand_total_amount' => 20000,
        ]);
        $this->assertDatabaseHas('b2b_order_items', ['product_id' => $product->id, 'selected_price' => 10000]);

        $this->actingAs($this->salesA)->post(route('sales.orders.store'), [
            'customer_id' => $this->customerB->id,
            'delivery_method' => 'courier',
            'payment_preference' => 'credit',
            'terms_accepted' => 1,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertForbidden();
    }

    public function test_dashboard_hanya_menghitung_order_completed_dan_bonus_setelah_target_tercapai(): void
    {
        SalesTarget::query()->create([
            'sales_user_id' => $this->salesA->id,
            'month' => now()->month,
            'year' => now()->year,
            'target_amount' => 100000,
            'bonus_percentage' => 1,
            'created_by' => $this->salesA->id,
        ]);
        $this->order($this->salesA, $this->customerA, B2bOrderStatus::COMPLETED, '110000', now());
        $this->order($this->salesA, $this->customerA, B2bOrderStatus::PENDING_CONFIRMATION, '900000');
        $this->order($this->salesB, $this->customerB, B2bOrderStatus::COMPLETED, '500000', now());

        $metrics = app(SalesPerformanceService::class)->forUser($this->salesA, now()->month, now()->year);

        $this->assertSame('110000.00', $metrics['sales_amount']);
        $this->assertSame('110.00', $metrics['achievement_percentage']);
        $this->assertSame('0.00', $metrics['remaining_target']);
        $this->assertSame('1100.00', $metrics['bonus_amount']);
        $this->assertSame(1, $metrics['order_count']);
    }

    public function test_bonus_periode_lampau_difinalisasi_dan_tidak_berubah(): void
    {
        $period = now()->subMonthNoOverflow();
        SalesTarget::query()->create([
            'sales_user_id' => $this->salesA->id,
            'month' => $period->month,
            'year' => $period->year,
            'target_amount' => 100000,
            'bonus_percentage' => 1,
            'created_by' => $this->salesA->id,
        ]);
        $this->order($this->salesA, $this->customerA, B2bOrderStatus::COMPLETED, '100000', $period->copy()->endOfMonth());

        $service = app(SalesPerformanceService::class);
        $first = $service->forUser($this->salesA, $period->month, $period->year);
        $this->assertNotNull($first['bonus']?->finalized_at);

        $this->order($this->salesA, $this->customerA, B2bOrderStatus::COMPLETED, '50000', $period->copy()->endOfMonth());
        $second = $service->forUser($this->salesA, $period->month, $period->year);

        $this->assertSame('100000.00', $second['sales_amount']);
        $this->assertSame('1000.00', $second['bonus_amount']);
    }

    public function test_cek_stok_mengikuti_scope_lokasi_kerja_sales(): void
    {
        $allowed = WorkLocation::factory()->create(['name' => 'Gudang Diizinkan']);
        $other = WorkLocation::factory()->create(['name' => 'Gudang Lain']);
        $this->salesA->workLocations()->attach($allowed->id, ['is_active' => true, 'is_default' => true]);
        $unit = Unit::factory()->create();
        $productA = Product::factory()->create(['base_unit_id' => $unit->id, 'name' => 'Produk Diizinkan']);
        $productB = Product::factory()->create(['base_unit_id' => $unit->id, 'name' => 'Produk Rahasia']);
        Stock::query()->create(['product_id' => $productA->id, 'work_location_id' => $allowed->id, 'location_scope_key' => 'allowed', 'quantity_on_hand' => 20, 'quantity_reserved' => 2, 'quantity_damaged' => 1, 'cost_value' => 0]);
        Stock::query()->create(['product_id' => $productB->id, 'work_location_id' => $other->id, 'location_scope_key' => 'other', 'quantity_on_hand' => 99, 'quantity_reserved' => 0, 'quantity_damaged' => 0, 'cost_value' => 0]);

        $this->actingAs($this->salesA)->get(route('sales.stocks.index'))
            ->assertOk()
            ->assertSee('Produk Diizinkan')
            ->assertSee('17')
            ->assertDontSee('Produk Rahasia');
    }

    public function test_halaman_dashboard_order_target_dan_form_order_dapat_dirender(): void
    {
        SalesTarget::query()->create([
            'sales_user_id' => $this->salesA->id,
            'month' => now()->month,
            'year' => now()->year,
            'target_amount' => 100000,
            'bonus_percentage' => 1,
            'created_by' => $this->salesA->id,
        ]);

        $this->actingAs($this->salesA)->get(route('dashboard'))->assertOk()->assertSee('Dashboard Sales');
        $this->actingAs($this->salesA)->get(route('sales.orders.index'))->assertOk()->assertSee('Order Saya');
        $this->actingAs($this->salesA)->get(route('sales.orders.create'))->assertOk()->assertSee('Customer Aldi');
        $this->actingAs($this->salesA)->get(route('sales.targets.index'))->assertOk()->assertSee('100.000');
    }

    public function test_admin_dapat_mengatur_target_dan_assignment_customer(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin_config'));

        $this->actingAs($admin)->post(route('sales.admin.targets.store'), [
            'sales_user_id' => $this->salesA->id,
            'month' => now()->month,
            'year' => now()->year,
            'target_amount' => 100000000,
            'bonus_percentage' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('sales_targets', [
            'sales_user_id' => $this->salesA->id,
            'target_amount' => 100000000,
            'bonus_percentage' => 1,
        ]);

        $this->actingAs($admin)->put(route('sales.assignments.update', $this->customerB), [
            'sales_user_id' => $this->salesA->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('customers', ['id' => $this->customerB->id, 'sales_user_id' => $this->salesA->id]);

        $this->actingAs($admin)->get(route('sales.performance'))->assertOk()->assertSee('Sales Aldi');
        $this->actingAs($admin)->get(route('sales.admin.targets.index'))->assertOk()->assertSee('100.000.000');
        $this->actingAs($admin)->get(route('sales.assignments.index'))->assertOk()->assertSee('Customer Aldi');
    }

    private function order(User $sales, Customer $customer, B2bOrderStatus $status, string $amount, mixed $completedAt = null): B2bOrder
    {
        return B2bOrder::query()->create([
            'number' => 'ORD/SALES/'.fake()->unique()->numerify('#####'),
            'customer_id' => $customer->id,
            'sales_user_id' => $sales->id,
            'requested_by' => $sales->id,
            'status' => $status,
            'grand_total_amount' => $amount,
            'submitted_at' => $completedAt ?? now(),
            'completed_at' => $completedAt,
        ]);
    }
}
