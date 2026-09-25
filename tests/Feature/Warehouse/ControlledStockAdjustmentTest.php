<?php

namespace Tests\Feature\Warehouse;

use App\Exceptions\ServiceException;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockAdjustmentAccess;
use App\Models\StockDirectAdjustment;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Warehouse\StockAdjustmentAccessService;
use App\Services\Warehouse\StockDirectAdjustmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlledStockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $approver;

    private User $superAdmin;

    private User $staff;

    private WorkLocation $location;

    private WarehouseLocation $bin;

    private Product $product;

    private Stock $stock;

    private StockAdjustmentAccessService $accesses;

    private StockDirectAdjustmentService $adjustments;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->creator = $this->user('kepala_gudang');
        $this->approver = $this->user('kepala_gudang');
        $this->superAdmin = $this->user('super_admin');
        $this->staff = $this->user('staff_gudang');
        $this->location = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-KST']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $this->location->id, 'code' => 'WH-KST']);
        $this->bin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'code' => 'BIN-KST', 'full_code' => 'BIN-KST']);
        $this->product = Product::factory()->create(['sku' => 'KST-001', 'cost_price' => '10000.00']);
        foreach ([$this->creator, $this->approver, $this->staff] as $user) {
            $user->workLocations()->attach($this->location->id, ['is_default' => true, 'is_active' => true]);
        }
        $this->inventory = app(InventoryService::class);
        $this->inventory->receive($this->product, $this->location, $this->bin, '10', $this->creator);
        $this->stock = Stock::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->accesses = app(StockAdjustmentAccessService::class);
        $this->adjustments = app(StockDirectAdjustmentService::class);
    }

    public function test_access_is_time_limited_and_audited(): void
    {
        $access = $this->activate();
        $this->assertTrue($access->isCurrentlyActive());
        $this->assertDatabaseHas('stock_adjustment_access_histories', ['work_location_id' => $this->location->id, 'action' => 'activated']);
        $this->assertTrue(AuditLog::query()->where('event', 'stock_adjustment_access.activated')->exists());

        $this->accesses->deactivate($access, 'Pemeriksaan stok telah selesai dilakukan.', $this->superAdmin);
        $this->assertFalse($access->fresh()->isCurrentlyActive());
        $this->expectException(ServiceException::class);
        $this->accesses->assertActive($this->location);
    }

    public function test_two_person_approval_posts_one_audited_mutation(): void
    {
        $this->activate();
        $adjustment = $this->createAdjustment('7');
        $this->assertSame('10.0000', (string) $this->stock->fresh()->quantity_on_hand);
        $adjustment = $this->adjustments->submit($adjustment, $this->creator);

        try {
            $this->adjustments->approve($adjustment, $this->creator, 'Tidak boleh disetujui sendiri.');
            $this->fail('Pembuat seharusnya tidak dapat menyetujui sendiri.');
        } catch (ServiceException) {
            $this->assertSame('pending_approval', $adjustment->fresh()->status);
        }

        $completed = $this->adjustments->approve($adjustment, $this->approver, 'Selisih fisik telah diperiksa ulang.');
        $this->assertSame('completed', $completed->status);
        $this->assertSame('7.0000', (string) $this->stock->fresh()->quantity_on_hand);
        $this->assertSame(1, StockMutation::query()->where('reference_type', 'stock_direct_adjustment')->where('reference_id', $adjustment->id)->count());
        $this->assertDatabaseHas('stock_direct_adjustment_items', ['stock_direct_adjustment_id' => $adjustment->id, 'posted_qty_before' => '10', 'posted_qty_after' => '7']);
        $this->assertTrue(AuditLog::query()->where('event', 'stock_direct_adjustment.completed')->where('severity', 'critical')->exists());
        $mutation = StockMutation::query()->where('reference_type', 'stock_direct_adjustment')->firstOrFail();
        $this->actingAs($this->creator)->get(route('warehouse.stock-mutations.show', $mutation))->assertOk()->assertSee($this->creator->name)->assertSee($this->approver->name)->assertSee('Target fisik');
    }

    public function test_changed_stock_returns_document_to_draft_without_adjusting(): void
    {
        $this->activate();
        $adjustment = $this->adjustments->submit($this->createAdjustment('7'), $this->creator);
        $this->inventory->receive($this->product, $this->location, $this->bin, '2', $this->creator);

        $result = $this->adjustments->approve($adjustment, $this->approver, 'Periksa perubahan stok setelah pengajuan.');
        $this->assertTrue((bool) $result->getAttribute('was_stale'));
        $this->assertSame('draft', $result->fresh()->status);
        $this->assertSame('12.0000', (string) $this->stock->fresh()->quantity_on_hand);
        $this->assertSame(0, StockMutation::query()->where('reference_type', 'stock_direct_adjustment')->count());
        $this->assertSame('12.0000', (string) $result->items()->firstOrFail()->system_qty_snapshot);
    }

    public function test_staff_cannot_open_or_create_direct_adjustment(): void
    {
        $this->activate();
        $this->actingAs($this->staff)->get(route('warehouse.stock-adjustments.index'))->assertForbidden();
        $this->assertFalse($this->staff->can('stock_direct_adjustments.create'));
    }

    public function test_pages_and_super_admin_access_management_are_available(): void
    {
        $this->activate();
        $this->actingAs($this->creator)->get(route('warehouse.stock-adjustments.index', ['work_location_id' => $this->location->id]))->assertOk()->assertSee('Koreksi Stok Langsung');
        $this->actingAs($this->superAdmin)->get(route('admin.settings.stock-adjustment-access.index'))->assertOk()->assertSee('Akses Koreksi Stok');
    }

    private function activate(): StockAdjustmentAccess
    {
        return $this->accesses->activate($this->location, ['active_from' => now()->subMinute()->toDateTimeString(), 'active_until' => now()->addHour()->toDateTimeString(), 'reason' => 'Pemeriksaan fisik stok terjadwal dan terbatas.'], $this->superAdmin);
    }

    private function createAdjustment(string $target): StockDirectAdjustment
    {
        return $this->adjustments->create(['work_location_id' => $this->location->id, 'notes' => 'Koreksi hasil hitung fisik.', 'items' => [['stock_id' => $this->stock->id, 'physical_qty' => $target, 'note' => 'Selisih ditemukan saat pemeriksaan rak.']]], $this->creator);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role));

        return $user;
    }
}
