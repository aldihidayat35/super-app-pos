<?php

namespace Tests\Feature\Retail;

use App\Enums\PaymentMethod;
use App\Enums\PosSaleStatus;
use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\CreditLimit;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Receivable;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosCreditAndPaymentUiTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Branch $branch;

    private WorkLocation $location;

    private Product $product;

    private Unit $unit;

    protected CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->location = WorkLocation::factory()->create([
            'is_active' => true,
        ]);

        $warehouse = Warehouse::factory()->create([
            'work_location_id' => $this->location->id,
            'is_active' => true,
        ]);

        $this->branch = Branch::factory()->create([
            'work_location_id' => $this->location->id,
            'primary_warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);

        $this->unit = Unit::factory()->create(['name' => 'Pieces', 'code' => 'PCS']);
        $this->product = Product::factory()->create([
            'base_unit_id' => $this->unit->id,
            'cost_price' => '10000.00',
            'minimum_price' => '10000.00',
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'work_location_id' => $this->location->id,
            'location_scope_key' => 'work:'.$this->location->id,
            'quantity_on_hand' => '100.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
        ]);

        ProductPrice::create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'channel' => 'pos',
            'price_ring' => 'pos',
            'recommended_price' => '20000.00',
            'min_price' => '15000.00',
            'max_price' => '25000.00',
            'starts_at' => now()->subDay(),
            'status' => ProductPriceStatus::ACTIVE,
        ]);

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
        $this->cashier->givePermissionTo('pos.create');
        $this->cashier->givePermissionTo('pos.view');
        $this->cashier->givePermissionTo('receivables.view');
        $this->cashier->givePermissionTo('receivables.pay');
        $this->cashier->workLocations()->attach($this->location->id);

        app(InventoryService::class)->receive(
            $this->product,
            $this->location,
            null,
            '50',
            $this->cashier,
            ['type' => 'opening', 'no' => 'OPEN-01']
        );

        $this->shift = CashShift::create([
            'number' => 'SHIFT-CREDIT-01',
            'cashier_user_id' => $this->cashier->id,
            'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id,
            'opened_at' => now(),
            'starting_cash' => '100000.00',
            'status' => 'open',
        ]);
    }

    public function test_pos_credit_payment_rejected_for_general_customer(): void
    {
        $this->actingAs($this->cashier);

        $payload = [
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                    'quantity' => 1,
                    'selected_price' => '20000.00',
                ],
            ],
            'payments' => [
                [
                    'method' => PaymentMethod::CREDIT->value,
                    'amount' => '20000.00',
                ],
            ],
        ];

        $res = $this->postJson(route('retail.pos.store'), $payload);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['customer_id']);
    }

    public function test_pos_credit_payment_succeeds_with_default_due_date_for_registered_customer(): void
    {
        $customer = Customer::factory()->create([
            'business_name' => 'Kedai Langganan',
            'payment_term_days' => 14,
            'credit_limit' => '500000.00',
            'receivable_balance' => '0.00',
            'is_active' => true,
        ]);
        CreditLimit::create([
            'customer_id' => $customer->id,
            'credit_limit' => '500000.00',
            'payment_term_days' => 14,
            'current_balance' => '0.00',
            'effective_from' => now()->toDateString(),
        ]);

        $this->actingAs($this->cashier);

        $payload = [
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                    'quantity' => 2,
                    'selected_price' => '20000.00',
                ],
            ],
            'payments' => [
                [
                    'method' => PaymentMethod::CREDIT->value,
                    'amount' => '40000.00',
                ],
            ],
        ];

        $res = $this->postJson(route('retail.pos.store'), $payload);

        $res->assertCreated();
        $this->assertNotEmpty($this->shift->number);
        $saleId = $res->json('sale.id');

        $this->assertDatabaseHas('pos_sales', [
            'id' => $saleId,
            'customer_id' => $customer->id,
            'status' => PosSaleStatus::COMPLETED->value,
        ]);

        $receivable = Receivable::query()->where('pos_sale_id', $saleId)->first();
        $this->assertNotNull($receivable);
        $this->assertSame(now()->addDays(14)->toDateString(), Carbon::parse($receivable->due_date)->toDateString());
        $this->assertSame('40000.00', $receivable->outstanding_amount);
    }

    public function test_pos_credit_payment_succeeds_with_custom_due_date(): void
    {
        $customer = Customer::factory()->create([
            'business_name' => 'Toko Mitra Setia',
            'payment_term_days' => 30,
            'credit_limit' => '1000000.00',
            'receivable_balance' => '0.00',
            'is_active' => true,
        ]);
        CreditLimit::create([
            'customer_id' => $customer->id,
            'credit_limit' => '1000000.00',
            'payment_term_days' => 30,
            'current_balance' => '0.00',
            'effective_from' => now()->toDateString(),
        ]);

        $this->actingAs($this->cashier);

        $customDueDate = now()->addDays(60)->toDateString();

        $payload = [
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'custom_due_date' => $customDueDate,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                    'quantity' => 1,
                    'selected_price' => '20000.00',
                ],
            ],
            'payments' => [
                [
                    'method' => PaymentMethod::CREDIT->value,
                    'amount' => '20000.00',
                    'due_date' => $customDueDate,
                ],
            ],
        ];

        $res = $this->postJson(route('retail.pos.store'), $payload);

        $res->assertCreated();
        $this->assertNotEmpty($this->shift->number);
        $saleId = $res->json('sale.id');

        $receivable = Receivable::query()->where('pos_sale_id', $saleId)->first();
        $this->assertNotNull($receivable);
        $this->assertSame($customDueDate, Carbon::parse($receivable->due_date)->toDateString());
    }

    public function test_receivables_payment_create_page_renders_with_customer_financial_summary(): void
    {
        $customer = Customer::factory()->create([
            'business_name' => 'Grosir Berkah',
            'code' => 'GB-01',
            'credit_limit' => '2000000.00',
            'receivable_balance' => '150000.00',
            'payment_term_days' => 21,
            'is_active' => true,
        ]);

        Receivable::create([
            'number' => 'REC-TEST-001',
            'customer_id' => $customer->id,
            'work_location_id' => $this->location->id,
            'source_type' => 'pos_sale',
            'source_no' => 'SAL/2026/001',
            'channel' => 'retail',
            'issue_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->addDays(16)->toDateString(),
            'principal_amount' => '150000.00',
            'outstanding_amount' => '150000.00',
            'status' => 'open',
        ]);

        $this->actingAs($this->cashier);

        // Akses tanpa customer_id
        $resBlank = $this->get(route('receivables.payments.create'));
        $resBlank->assertOk();
        $resBlank->assertSee('Pelanggan Belum Dipilih');

        // Akses dengan customer_id
        $resCust = $this->get(route('receivables.payments.create', ['customer_id' => $customer->id]));
        $resCust->assertOk();
        $resCust->assertSee('Grosir Berkah');
        $resCust->assertSee('Total Saldo Piutang');
        $resCust->assertSee('Rp150.000');
        $resCust->assertSee('REC-TEST-001');
        $resCust->assertSee('SAL/2026/001');
        $resCust->assertSee('21 Hari');
    }

    public function test_pos_checkout_blocked_when_minimum_order_not_met(): void
    {
        $customer = Customer::factory()->create([
            'business_name' => 'Kedai Awal',
            'minimum_order' => '100000.00',
            'credit_limit' => '5000000.00',
            'receivable_balance' => '0.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier);

        $payload = [
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                    'quantity' => 1,
                    'selected_price' => '20000.00',
                ],
            ],
            'payments' => [
                [
                    'method' => PaymentMethod::CASH->value,
                    'amount' => '20000.00',
                ],
            ],
        ];

        $res = $this->postJson(route('retail.pos.store'), $payload);

        $res->assertUnprocessable();
        $this->assertStringContainsString('belum mencapai batas minimum order', $res->json('message'));
    }

    public function test_pos_checkout_blocked_when_credit_limit_exceeded(): void
    {
        $customer = Customer::factory()->create([
            'business_name' => 'Pelanggan Melebihi Limit',
            'minimum_order' => '0.00',
            'credit_limit' => '50000.00',
            'receivable_balance' => '40000.00',
            'is_active' => true,
        ]);
        CreditLimit::create([
            'customer_id' => $customer->id,
            'credit_limit' => '50000.00',
            'payment_term_days' => 14,
            'current_balance' => '40000.00',
            'effective_from' => now()->toDateString(),
        ]);

        $this->actingAs($this->cashier);

        $payload = [
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                    'quantity' => 2,
                    'selected_price' => '20000.00',
                ],
            ],
            'payments' => [
                [
                    'method' => PaymentMethod::CREDIT->value,
                    'amount' => '40000.00',
                ],
            ],
        ];

        $res = $this->postJson(route('retail.pos.store'), $payload);

        $res->assertUnprocessable();
        $this->assertStringContainsString('limit kredit', $res->json('message'));
    }
}
