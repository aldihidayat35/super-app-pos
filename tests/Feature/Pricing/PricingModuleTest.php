<?php

namespace Tests\Feature\Pricing;

use App\Enums\PriceApprovalStatus;
use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPriceOverride;
use App\Models\PriceApprovalRequest;
use App\Models\PriceHistory;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pricing\PriceManagementService;
use App\Services\Pricing\PriceResolverService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PricingModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private PriceResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->resolver = app(PriceResolverService::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(Role::findOrCreate('admin_config'));
        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
    }

    public function test_p15_pages_are_available(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('pricing.rules.index'))->assertOk()->assertSee('Aturan Harga dan Margin');
        $this->get(route('pricing.product-prices.index'))->assertOk()->assertSee('Harga Produk per Ring');
        $this->get(route('pricing.special-prices.index'))->assertOk()->assertSee('Harga Khusus Pelanggan');
        $this->get(route('pricing.history.index'))->assertOk()->assertSee('Histori Perubahan Harga');
        $this->get(route('pricing.approvals.index'))->assertOk()->assertSee('Antrian Approval Harga');
        $this->get(route('pricing.simulator.index'))->assertOk()->assertSee('Simulasi Margin');
    }

    public function test_price_resolver_calculates_minimum_maximum_and_exact_boundaries(): void
    {
        $product = $this->product(costPrice: '10000.00');
        $this->defaultRule();

        $atMinimum = $this->resolver->resolve($product, user: $this->admin, requestedPrice: '12000.00');
        $atMaximum = $this->resolver->resolve($product, user: $this->admin, requestedPrice: '18000.00');

        $this->assertSame('12000.00', $atMinimum['minimum_price']);
        $this->assertSame('18000.00', $atMinimum['maximum_price']);
        $this->assertFalse($atMinimum['approval_required']);
        $this->assertFalse($atMaximum['approval_required']);
    }

    public function test_priority_conflict_prefers_customer_special_price(): void
    {
        [$product, $branch, $customer] = $this->pricingFixture();

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'channel' => 'b2b',
            'price_ring' => 'ring_grosir',
            'customer_category' => 'grosir',
            'recommended_price' => '15000.00',
            'minimum_qty' => '1',
            'priority' => 1,
            'status' => ProductPriceStatus::ACTIVE,
        ]);
        CustomerPriceOverride::query()->create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'channel' => 'b2b',
            'price' => '14000.00',
            'minimum_qty' => '1',
            'priority' => 1,
            'status' => PriceApprovalStatus::APPROVED->value,
            'starts_at' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $result = $this->resolver->resolve($product, branch: $branch, customer: $customer, channel: 'b2b', user: $this->admin);

        $this->assertSame('customer_special', $result['selected_source']);
        $this->assertSame('14000.00', $result['selected_price']);
    }

    public function test_date_range_ignores_expired_prices(): void
    {
        $product = $this->product(costPrice: '10000.00');
        $this->defaultRule();

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'channel' => 'retail',
            'price_ring' => 'expired',
            'recommended_price' => '13000.00',
            'minimum_qty' => '1',
            'priority' => 1,
            'starts_at' => now()->subDays(10)->toDateString(),
            'ends_at' => now()->subDay()->toDateString(),
            'status' => ProductPriceStatus::ACTIVE,
        ]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'channel' => 'retail',
            'price_ring' => 'current',
            'recommended_price' => '16000.00',
            'minimum_qty' => '1',
            'priority' => 2,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
            'status' => ProductPriceStatus::ACTIVE,
        ]);

        $result = $this->resolver->resolve($product, channel: 'retail', user: $this->admin);

        $this->assertSame('16000.00', $result['selected_price']);
        $this->assertStringContainsString('current', $result['reason']);
    }

    public function test_unit_conversion_scales_price_boundaries(): void
    {
        $baseUnit = Unit::factory()->create();
        $boxUnit = Unit::factory()->create();
        $product = $this->product(costPrice: '10000.00', baseUnit: $baseUnit);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $boxUnit->id,
            'name' => 'Dus',
            'conversion_factor' => '10.000000',
            'is_active' => true,
        ]);
        $this->defaultRule();

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'channel' => 'retail',
            'price_ring' => 'box',
            'recommended_price' => '15000.00',
            'minimum_qty' => '1',
            'priority' => 1,
            'status' => ProductPriceStatus::ACTIVE,
        ]);

        $result = $this->resolver->resolve($product, unitId: $boxUnit->id, channel: 'retail', user: $this->admin);

        $this->assertSame('10.000000', $result['unit_factor']);
        $this->assertSame('120000.00', $result['minimum_price']);
        $this->assertSame('150000.00', $result['selected_price']);
    }

    public function test_below_minimum_overpricing_and_discount_cap_require_approval(): void
    {
        $product = $this->product(costPrice: '10000.00');
        $this->defaultRule();

        $belowMinimum = $this->resolver->resolve($product, user: $this->admin, requestedPrice: '11000.00');
        $overpricing = $this->resolver->resolve($product, user: $this->admin, requestedPrice: '20000.00');
        $tooMuchDiscount = $this->resolver->resolve($product, user: $this->admin, requestedPrice: '15000.00', discountPercent: '20');

        $this->assertTrue($belowMinimum['approval_required']);
        $this->assertContains('below_minimum', $belowMinimum['approval_reasons']);
        $this->assertTrue($overpricing['approval_required']);
        $this->assertContains('overpricing', $overpricing['approval_reasons']);
        $this->assertTrue($tooMuchDiscount['approval_required']);
        $this->assertContains('discount_exceeds_cap', $tooMuchDiscount['approval_reasons']);
    }

    public function test_special_price_below_minimum_creates_approval_and_can_be_approved(): void
    {
        [$product, $branch, $customer] = $this->pricingFixture();
        $service = app(PriceManagementService::class);

        $override = $service->saveCustomerOverride([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'channel' => 'b2b',
            'price' => '11000.00',
            'minimum_qty' => 1,
            'discount_percent' => 0,
            'starts_at' => now()->toDateString(),
            'reason' => 'Kontrak pelanggan strategis.',
        ], $this->admin);

        $approval = PriceApprovalRequest::query()->firstOrFail();

        $this->assertSame('pending', $override->status);
        $this->assertSame('below_minimum', $approval->approval_type);
        $service->approve($approval, $this->admin, 'Disetujui owner.');

        $this->assertSame('approved', $override->fresh()->status);
        $this->assertSame(1, PriceHistory::query()->where('priceable_id', $override->id)->count());
    }

    public function test_cashier_cannot_view_sensitive_hpp_margin(): void
    {
        $product = $this->product(costPrice: '10000.00');
        $this->defaultRule();

        $adminResult = $this->resolver->resolve($product, user: $this->admin);
        $cashierResult = $this->resolver->resolve($product, user: $this->cashier);

        $this->assertTrue($adminResult['can_view_sensitive_margin']);
        $this->assertFalse($cashierResult['can_view_sensitive_margin']);
    }

    public function test_product_price_can_be_assigned_to_multiple_products_and_exported(): void
    {
        $products = [
            $this->product(costPrice: '10000.00'),
            $this->product(costPrice: '10000.00'),
        ];
        $this->defaultRule();

        $this->actingAs($this->admin)->post(route('pricing.product-prices.store'), [
            'product_ids' => array_map(fn (Product $product): int => $product->id, $products),
            'channel' => 'retail',
            'price_ring' => 'ring_1',
            'recommended_price' => '15000.00',
            'minimum_qty' => '1',
            'priority' => 100,
        ])->assertRedirect();

        $this->assertSame(2, ProductPrice::query()->where('price_ring', 'ring_1')->count());
        $this->actingAs($this->admin)->get(route('pricing.product-prices.export'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    private function product(string $costPrice = '10000.00', ?Unit $baseUnit = null): Product
    {
        return Product::factory()->create([
            'base_unit_id' => $baseUnit?->id ?? Unit::factory()->create()->id,
            'cost_price' => $costPrice,
            'minimum_price' => 0,
        ]);
    }

    private function defaultRule(): PriceRule
    {
        return PriceRule::query()->create([
            'name' => 'Default Test Rule',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => '20.00',
            'minimum_margin_amount' => '0.00',
            'overpricing_tolerance_percent' => '50.00',
            'max_discount_percent' => '10.00',
            'approval_threshold_amount' => '0.00',
            'priority' => 100,
            'is_active' => true,
        ]);
    }

    /** @return array{0: Product, 1: Branch, 2: Customer} */
    private function pricingFixture(): array
    {
        $product = $this->product(costPrice: '10000.00');
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create(['price_category' => 'grosir']);
        $this->defaultRule();

        return [$product, $branch, $customer];
    }
}
