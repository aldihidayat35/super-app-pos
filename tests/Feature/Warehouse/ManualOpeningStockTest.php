<?php

namespace Tests\Feature\Warehouse;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockDirectAdjustment;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Warehouse\OpeningStockService;
use App\Services\Warehouse\StockAdjustmentAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualOpeningStockTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $head;

    private User $superAdmin;

    private WorkLocation $location;

    private WarehouseLocation $bin;

    private Product $product;

    private OpeningStockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->staff = $this->user('staff_gudang');
        $this->head = $this->user('kepala_gudang');
        $this->superAdmin = $this->user('super_admin');
        $this->location = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-AWAL']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $this->location->id, 'code' => 'WH-AWAL']);
        $this->bin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'code' => 'RAK-AWAL', 'full_code' => 'RAK-AWAL', 'is_active' => true]);
        $this->product = Product::factory()->create(['sku' => 'AWAL-001', 'status' => 'active', 'cost_price' => '12500.00']);
        foreach ([$this->staff, $this->head] as $user) {
            $user->workLocations()->attach($this->location->id, ['is_default' => true, 'is_active' => true]);
        }
        app(StockAdjustmentAccessService::class)->activate($this->location, [
            'active_from' => now()->subMinute()->toDateTimeString(),
            'active_until' => now()->addHour()->toDateTimeString(),
            'reason' => 'Pendataan saldo awal sebelum lokasi mulai digunakan.',
        ], $this->superAdmin);
        $this->service = app(OpeningStockService::class);
    }

    public function test_staff_can_record_product_without_existing_stock_and_head_posts_it(): void
    {
        $this->actingAs($this->staff)
            ->get(route('warehouse.opening-stocks.index', ['work_location_id' => $this->location->id]))
            ->assertOk()
            ->assertSee('Pendataan Stok Lokasi')
            ->assertSee($this->product->sku)
            ->assertSee('opening-product-picker', false)
            ->assertSee('Pilih Semua Hasil Filter');

        $document = $this->service->create($this->payload('7'), $this->staff);
        $this->assertNull($document->items->first()->stock_id);
        $this->assertDatabaseMissing('stocks', ['product_id' => $this->product->id, 'work_location_id' => $this->location->id]);

        $document = $this->service->submit($document, $this->staff);
        $this->actingAs($this->head)->get(route('warehouse.opening-stocks.show', $document))->assertOk()->assertSee('Posting sebagai Saldo Akhir');
        $completed = $this->service->approve($document, $this->head, 'Jumlah dan rak sudah diperiksa.');

        $this->assertSame('completed', $completed->status);
        $this->assertDatabaseHas('stocks', ['product_id' => $this->product->id, 'work_location_id' => $this->location->id, 'quantity_on_hand' => 7]);
        $this->assertDatabaseHas('stock_mutations', ['reference_type' => 'opening_balance_manual', 'reference_id' => $document->id, 'quantity_on_hand_after' => 7]);
        $this->assertSame('12500.00', (string) $this->product->fresh()->cost_price);
        $mutation = StockMutation::query()->where('reference_type', 'opening_balance_manual')->firstOrFail();
        $this->actingAs($this->head)->get(route('warehouse.stock-mutations.show', $mutation))->assertOk()->assertSee('Pendataan Stok Lokasi')->assertSee($this->staff->name);
    }

    public function test_head_can_post_own_opening_balance_but_not_own_operational_correction(): void
    {
        $document = $this->service->submit($this->service->create($this->payload('3'), $this->head), $this->head);
        $completed = $this->service->approve($document, $this->head, 'Posting mandiri kepala lokasi.');

        $this->assertSame($this->head->id, $completed->approved_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'opening_stock.self_posted_by_head', 'severity' => 'critical']);
        $this->assertFalse($this->head->can('decide', StockDirectAdjustment::query()->create([
            'number' => 'KST/TEST/2026/00001',
            'work_location_id' => $this->location->id,
            'purpose' => 'operational_correction',
            'created_by' => $this->head->id,
            'status' => 'pending_approval',
        ])));
    }

    public function test_zero_and_repeated_final_quantity_do_not_create_duplicate_mutations(): void
    {
        $zero = $this->service->submit($this->service->create($this->payload('0'), $this->head), $this->head);
        $this->service->approve($zero, $this->head, 'Produk telah dihitung dan kosong.');
        $this->assertSame(0, StockMutation::query()->count());
        $this->assertDatabaseHas('stock_direct_adjustment_items', ['stock_direct_adjustment_id' => $zero->id, 'physical_qty' => 0, 'posted_qty_after' => 0]);

        $first = $this->service->submit($this->service->create($this->payload('5'), $this->head), $this->head);
        $this->service->approve($first, $this->head, 'Saldo akhir lima.');
        $repeat = $this->service->submit($this->service->create($this->payload('5'), $this->head), $this->head);
        $this->service->approve($repeat, $this->head, 'Dihitung ulang tetap lima.');

        $this->assertSame(1, StockMutation::query()->where('reference_type', 'opening_balance_manual')->count());
        $this->assertSame('5.0000', (string) Stock::query()->where('product_id', $this->product->id)->firstOrFail()->quantity_on_hand);
    }

    /** @return array{work_location_id:int, notes:string, items:list<array{product_id:int, warehouse_location_id:int, physical_qty:string, note:string}>} */
    private function payload(string $quantity): array
    {
        return [
            'work_location_id' => $this->location->id,
            'notes' => 'Pendataan fisik lokasi.',
            'items' => [[
                'product_id' => $this->product->id,
                'warehouse_location_id' => $this->bin->id,
                'physical_qty' => $quantity,
                'note' => 'Produk sudah dihitung langsung di rak.',
            ]],
        ];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role));

        return $user;
    }
}
