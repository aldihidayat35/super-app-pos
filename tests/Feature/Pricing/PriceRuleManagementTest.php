<?php

namespace Tests\Feature\Pricing;

use App\Models\Branch;
use App\Models\PriceRule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PriceRuleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(Role::findOrCreate('admin_config'));
    }

    public function test_price_rules_page_displays_clean_layout_and_can_filter(): void
    {
        $branch = Branch::factory()->create();

        PriceRule::create([
            'name' => 'Rule Alpha Retail',
            'channel' => 'retail',
            'branch_id' => $branch->id,
            'margin_method' => 'percent',
            'minimum_margin_percent' => 25,
            'is_active' => true,
            'priority' => 10,
        ]);

        PriceRule::create([
            'name' => 'Rule Beta POS',
            'channel' => 'pos',
            'branch_id' => null,
            'margin_method' => 'nominal',
            'minimum_margin_amount' => 5000,
            'is_active' => false,
            'priority' => 20,
        ]);

        $this->actingAs($this->admin);

        // Standard index
        $res = $this->get(route('pricing.rules.index'));
        $res->assertOk();
        $res->assertSee('Rule Alpha Retail');
        $res->assertSee('Rule Beta POS');

        // Filter by channel
        $resChannel = $this->get(route('pricing.rules.index', ['channel' => 'retail']));
        $resChannel->assertOk();
        $resChannel->assertSee('Rule Alpha Retail');
        $resChannel->assertDontSee('Rule Beta POS');

        // Filter by status inactive
        $resStatus = $this->get(route('pricing.rules.index', ['status' => 'inactive']));
        $resStatus->assertOk();
        $resStatus->assertSee('Rule Beta POS');
        $resStatus->assertDontSee('Rule Alpha Retail');
    }

    public function test_price_rule_can_be_toggled(): void
    {
        $rule = PriceRule::create([
            'name' => 'Rule To Toggle',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => 15,
            'is_active' => true,
            'priority' => 50,
        ]);

        $this->actingAs($this->admin);

        $res = $this->post(route('pricing.rules.toggle', $rule));
        $res->assertRedirect();

        $this->assertFalse($rule->fresh()->is_active);

        // Toggle back
        $this->post(route('pricing.rules.toggle', $rule));
        $this->assertTrue($rule->fresh()->is_active);
    }

    public function test_price_rule_can_be_deleted(): void
    {
        $rule = PriceRule::create([
            'name' => 'Rule To Delete',
            'channel' => 'all',
            'margin_method' => 'percent',
            'minimum_margin_percent' => 15,
            'is_active' => true,
            'priority' => 90,
        ]);

        $this->actingAs($this->admin);

        $res = $this->delete(route('pricing.rules.destroy', $rule));
        $res->assertRedirect();

        $this->assertDatabaseMissing('price_rules', ['id' => $rule->id]);
    }
}
