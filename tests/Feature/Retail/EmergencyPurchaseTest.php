<?php

namespace Tests\Feature\Retail;

use App\Enums\CashShiftStatus;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\ServiceException;
use App\Models\ApprovalRequest;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\EmergencyPurchase;
use App\Models\GoodsReceipt;
use App\Models\PosSaleAllocation;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Control\ApprovalWorkflowService;
use App\Services\Inventory\InventoryService;
use App\Services\Retail\CashShiftService;
use App\Services\Retail\EmergencyPurchaseService;
use App\Services\Retail\EmergencyStockService;
use App\Services\Retail\PosCatalogService;
use App\Services\Retail\PosService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmergencyPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private WorkLocation $location;

    private User $cashier;

    private User $staff;

    private User $manager;

    private Unit $unit;

    private EmergencyPurchaseService $emergency;

    private PosService $pos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->location = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-EM', 'name' => 'Toko Darurat']);
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-EM']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $this->branch = Branch::factory()->create(['work_location_id' => $this->location->id, 'primary_warehouse_id' => $warehouse->id]);
        $this->cashier = $this->user('kasir');
        $this->staff = $this->user('staf_toko');
        $this->manager = $this->user('kepala_toko');
        $this->unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        PriceRule::query()->create([
            'name' => 'POS Darurat', 'channel' => 'all', 'margin_method' => 'percent',
            'minimum_margin_percent' => '20.00', 'minimum_margin_amount' => '0.00',
            'overpricing_tolerance_percent' => '100.00', 'max_discount_percent' => '10.00',
            'priority' => 1, 'is_active' => true,
        ]);
        $this->shift('SHIFT-EM-1');
        $this->emergency = app(EmergencyPurchaseService::class);
        $this->pos = app(PosService::class);
    }

    public function test_zero_stock_purchase_checkout_and_return_never_increase_regular_stock(): void
    {
        $product = $this->product('0');
        $purchase = $this->request($product, '2');
        $this->assertSame('approved', $purchase->status);
        $this->assertDatabaseCount('retail_stockout_events', 1);
        $this->buy($purchase, '2', 'personal');
        $sale = $this->checkout($product, $purchase, '2');
        $allocation = PosSaleAllocation::query()->where('pos_sale_item_id', $sale->items->first()->id)->firstOrFail();
        $this->assertSame('emergency', $allocation->source);
        $this->assertSame('2.0000', $allocation->base_quantity);
        $this->assertSame('300.00', $allocation->actual_cogs_amount);
        $this->assertSame('-60.00', $sale->total_margin_amount);
        $this->assertSame('completed', $purchase->fresh()->status);
        $this->assertSame('0.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);

        $return = $this->pos->returnSale($sale, [
            'reason' => 'Pelanggan batal sebagian', 'resolution' => 'refund', 'refund_method' => 'cash',
            'items' => [['pos_sale_item_id' => $sale->items->first()->id, 'quantity' => '1',
                'normal_quantity' => '0', 'emergency_quantity' => '1', 'condition' => 'good']],
        ], $this->manager);
        $this->assertSame('150.00', $return->items->first()->reversed_cogs_amount);
        $this->assertSame('unallocated', $purchase->fresh()->status);
        $this->assertSame('0.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
    }

    public function test_mixed_allocation_uses_actual_cost_and_issues_only_normal_quantity(): void
    {
        $product = $this->product('1');
        $purchase = $this->request($product, '2');
        $this->assertSame('1.0000', $purchase->items->first()->shortage_at_request);
        $this->buy($purchase, '1', 'store_cash');
        $sale = $this->checkout($product, $purchase, '2');
        $this->assertSame('0.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
        $this->assertSame('1.0000', PosSaleAllocation::query()->where('source', 'normal')->firstOrFail()->base_quantity);
        $this->assertSame('1.0000', PosSaleAllocation::query()->where('source', 'emergency')->firstOrFail()->base_quantity);
        $this->assertSame('-10.00', $sale->total_margin_amount);
        $this->assertDatabaseCount('shift_expenses', 1);
        $this->assertSame('150.00', $purchase->fresh()->shiftExpense->amount);
    }

    public function test_checkout_quantity_may_be_lower_than_request_and_releases_remainder_to_pool(): void
    {
        $product = $this->product('30');
        $purchase = $this->request($product, '35');
        $this->assertSame('5.0000', $purchase->items->first()->shortage_at_request);
        $this->buy($purchase, '5', 'personal');

        $sale = $this->checkout($product, $purchase, '33');
        $allocations = PosSaleAllocation::query()->where('pos_sale_item_id', $sale->items->first()->id)->get()->keyBy('source');

        $this->assertSame('30.0000', $allocations['normal']->base_quantity);
        $this->assertSame('3.0000', $allocations['emergency']->base_quantity);
        $this->assertSame('unallocated', $purchase->fresh()->status);
        $this->assertSame('2.0000', app(EmergencyStockService::class)->available($this->branch, $product->id));
        $this->assertSame('0.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
        $this->assertNull($purchase->fresh()->pos_sale_id);
    }

    public function test_proactive_restock_creates_pool_without_stockout_and_pos_uses_fifo(): void
    {
        $product = $this->product('0');
        $first = $this->emergency->confirm([
            'purpose' => 'proactive_restock', 'branch_id' => $this->branch->id,
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id,
                'quantity' => '1', 'unit_cost' => '130.00']],
        ], $this->staff);
        $this->emergency->purchased($first, [
            'supplier_name' => 'Pemasok Pertama', 'fund_source' => 'personal', 'receipt_path' => 'first.pdf',
            'items' => [['id' => $first->items->first()->id, 'purchased_quantity' => '1', 'unit_cost' => '130.00']],
        ], $this->cashier);
        $second = $this->emergency->confirm([
            'purpose' => 'proactive_restock', 'branch_id' => $this->branch->id,
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id,
                'quantity' => '2', 'unit_cost' => '150.00']],
        ], $this->staff);
        $this->emergency->purchased($second, [
            'supplier_name' => 'Pemasok Kedua', 'fund_source' => 'personal', 'receipt_path' => 'second.pdf',
            'items' => [['id' => $second->items->first()->id, 'purchased_quantity' => '2', 'unit_cost' => '150.00']],
        ], $this->cashier);

        $sale = $this->checkoutWithoutRequest($product, '2');
        $allocations = PosSaleAllocation::query()->where('pos_sale_item_id', $sale->items->first()->id)->orderBy('id')->get();

        $this->assertDatabaseCount('retail_stockout_events', 0);
        $this->assertSame('proactive_restock', $first->fresh()->purpose);
        $this->assertNull($second->fresh()->customer_id);
        $this->assertSame('130.00', $allocations[0]->actual_cost_unit);
        $this->assertSame('150.00', $allocations[1]->actual_cost_unit);
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('unallocated', $second->fresh()->status);
        $this->assertSame('1.0000', app(EmergencyStockService::class)->available($this->branch, $product->id));
        $this->assertDatabaseCount('stocks', 1);
        $this->assertSame('0.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
        $quote = app(PosCatalogService::class)->quote($this->branch, $this->cashier, [
            'product_id' => $product->id, 'unit_id' => $this->unit->id, 'quantity' => '1',
        ]);
        $this->assertSame('0.0000', $quote['regular_stock_base']);
        $this->assertSame('1.0000', $quote['emergency_stock_base']);
        $this->assertSame('1.0000', $quote['sellable_stock_base']);
        $this->actingAs($this->staff)->get(route('retail.storefront.index'))
            ->assertOk()->assertSee('Darurat 1');
        $report = $this->actingAs($this->manager)->get(route('retail.emergency.report', ['branch_id' => $this->branch->id]));
        $this->assertSame('1.0000', $report->viewData('poolQuantity'));
        $this->assertSame('150.00', $report->viewData('poolCostValue'));
        $this->assertSame('1.0000', $report->viewData('poolProducts')[0]['quantity']);
        $this->assertSame('150.00', $report->viewData('poolProducts')[0]['cost_value']);
    }

    public function test_bound_checkout_may_omit_an_item_and_releases_it_to_shared_pool(): void
    {
        $firstProduct = $this->product('0');
        $secondProduct = $this->product('0');
        $purchase = $this->emergency->confirm([
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $firstProduct->id, 'unit_id' => $this->unit->id, 'quantity' => '2', 'unit_cost' => '140.00'],
                ['product_id' => $secondProduct->id, 'unit_id' => $this->unit->id, 'quantity' => '1', 'unit_cost' => '160.00'],
            ],
        ], $this->staff);
        $rows = $purchase->items->keyBy('product_id');
        $this->emergency->purchased($purchase, [
            'supplier_name' => 'Pemasok Campuran', 'fund_source' => 'personal', 'receipt_path' => 'mixed.pdf',
            'items' => [
                ['id' => $rows[$firstProduct->id]->id, 'purchased_quantity' => '2', 'unit_cost' => '140.00'],
                ['id' => $rows[$secondProduct->id]->id, 'purchased_quantity' => '1', 'unit_cost' => '160.00'],
            ],
        ], $this->cashier);

        $sale = $this->checkout($firstProduct, $purchase, '1');

        $this->assertSame('1.0000', $sale->items->first()->base_quantity);
        $this->assertSame('unallocated', $purchase->fresh()->status);
        $this->assertSame('1.0000', app(EmergencyStockService::class)->available($this->branch, $firstProduct->id));
        $this->assertSame('1.0000', app(EmergencyStockService::class)->available($this->branch, $secondProduct->id));
    }

    public function test_rule_requires_separate_approver_and_unconfigured_branch_auto_approves(): void
    {
        DB::table('emergency_purchase_rules')->insert([
            'branch_id' => $this->branch->id, 'approval_above_amount' => '100.00',
            'required_role' => 'kepala_toko', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $purchase = $this->request($this->product('0'), '1');
        $this->assertSame('pending_approval', $purchase->status);
        $approval = ApprovalRequest::query()->where('subject_id', $purchase->id)->firstOrFail();
        try {
            app(ApprovalWorkflowService::class)->approve($approval, $this->staff);
            $this->fail('Pemohon tidak boleh menyetujui sendiri.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('Requester', $exception->getMessage());
        }
        app(ApprovalWorkflowService::class)->approve($approval, $this->manager);
        $this->assertSame('approved', $purchase->fresh()->status);
    }

    public function test_staff_can_request_without_shift_and_reimbursement_needs_reference(): void
    {
        $product = $this->product('0');
        $purchase = $this->request($product, '1');
        $this->buy($purchase, '1', 'personal');
        $this->assertSame('pending', $purchase->fresh()->reimbursement_status);
        $this->emergency->reimburse($purchase, $this->manager, 'TRF-123', 'proof.pdf');
        $this->assertSame('paid', $purchase->fresh()->reimbursement_status);
        $this->assertSame('TRF-123', $purchase->fresh()->reimbursement_reference);
        $this->actingAs($this->staff)->get(route('retail.emergency.show', $purchase))->assertOk();
        $this->actingAs($this->staff)->get(route('retail.pos.index'))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('retail.pos.index', ['emergency_purchase_id' => $purchase->id]))
            ->assertOk()->assertSee('emergencyPurchaseId', false);
    }

    public function test_unallocated_goods_can_be_reassigned_to_confirmed_shortage_in_same_store(): void
    {
        $product = $this->product('0');
        $target = $this->request($product, '1');
        $source = $this->request($product, '1');
        $this->buy($source, '1', 'personal');
        $this->emergency->cancel($source, $this->staff, 'Pelanggan pertama batal');
        $this->assertSame('unallocated', $source->fresh()->status);
        $this->emergency->reassign($source, $target, $this->manager);
        $this->assertSame('reallocated', $target->fresh()->fund_source);
        $sale = $this->checkout($product, $target, '1');
        $this->assertSame($source->items->first()->id,
            PosSaleAllocation::query()->where('pos_sale_item_id', $sale->items->first()->id)->firstOrFail()->emergency_purchase_item_id);
        $this->assertSame('completed', $source->fresh()->status);
        $this->assertSame('0.00', $target->fresh()->total_cost);
    }

    public function test_stock_change_before_checkout_reduces_emergency_allocation_and_keeps_remainder_unallocated(): void
    {
        $product = $this->product('0');
        $purchase = $this->request($product, '2');
        $this->buy($purchase, '2', 'personal');
        app(InventoryService::class)->receive($product, $this->location, null, '1',
            $this->cashier, ['type' => 'transfer', 'no' => 'NEW-STOCK']);
        $sale = $this->checkout($product, $purchase, '2');
        $this->assertSame('1.0000', PosSaleAllocation::query()->where('pos_sale_item_id', $sale->items->first()->id)
            ->where('source', 'normal')->firstOrFail()->base_quantity);
        $this->assertSame('1.0000', PosSaleAllocation::query()->where('pos_sale_item_id', $sale->items->first()->id)
            ->where('source', 'emergency')->firstOrFail()->base_quantity);
        $this->assertSame('unallocated', $purchase->fresh()->status);
        $this->assertSame('0.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
    }

    public function test_intent_survives_shift_change_and_checkout_uses_current_shift(): void
    {
        $product = $this->product('0');
        $purchase = $this->request($product, '1');
        $this->buy($purchase, '1', 'personal');
        CashShift::query()->where('number', 'SHIFT-EM-1')->update(['status' => CashShiftStatus::CLOSED->value]);
        $newShift = $this->shift('SHIFT-EM-2');
        $sale = $this->checkout($product, $purchase, '1');
        $this->assertSame($newShift->id, $sale->cash_shift_id);
    }

    public function test_confirm_key_records_one_stockout_even_when_submitted_twice_and_purchase_is_cancelled(): void
    {
        $product = $this->product('0');
        $payload = ['branch_id' => $this->branch->id, 'confirmation_key' => 'STOCKOUT-1',
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id,
                'quantity' => '1', 'unit_cost' => '150.00']]];
        $first = $this->emergency->confirm($payload, $this->staff);
        $second = $this->emergency->confirm($payload, $this->staff);
        $this->assertSame($first->id, $second->id);
        $this->emergency->cancel($first, $this->staff, 'Pelanggan batal sebelum barang dibeli');
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertDatabaseCount('retail_stockout_events', 1);
        $this->assertDatabaseCount('shift_expenses', 0);
    }

    public function test_purchase_limit_and_failed_checkout_do_not_post_partial_sale_or_expense(): void
    {
        $product = $this->product('1');
        $purchase = $this->request($product, '2');
        try {
            $this->buy($purchase, '2', 'store_cash');
            $this->fail('Qty pembelian di atas kekurangan harus ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('batas kekurangan', $exception->getMessage());
        }
        $this->assertSame('0.0000', $purchase->items->first()->fresh()->purchased_quantity);
        $this->assertDatabaseCount('shift_expenses', 0);
        $this->buy($purchase, '1', 'personal');
        app(InventoryService::class)->issue($product, $this->location, null, '1',
            $this->cashier, ['type' => 'other_sale', 'no' => 'OTHER']);
        try {
            $this->checkout($product, $purchase, '2');
            $this->fail('Kekurangan baru lebih besar dari qty darurat harus ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('melampaui barang', $exception->getMessage());
        }
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseCount('pos_sale_allocations', 0);
        $this->assertSame('purchased', $purchase->fresh()->status);
    }

    public function test_one_emergency_intent_cannot_be_bound_to_unrelated_or_second_sale(): void
    {
        $requested = $this->product('0');
        $other = $this->product('1');
        $purchase = $this->request($requested, '1');
        $this->buy($purchase, '1', 'personal');

        try {
            $this->checkout($other, $purchase, '1');
            $this->fail('Keranjang yang tidak memuat kebutuhan pelanggan harus ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('Keranjang POS', $exception->getMessage());
        }
        $this->assertDatabaseCount('pos_sales', 0);

        $this->checkout($requested, $purchase, '1');
        try {
            $this->checkout($requested, $purchase, '1');
            $this->fail('Permintaan yang sudah digunakan tidak boleh dipakai dua kali.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('tidak siap', $exception->getMessage());
        }
        $this->assertDatabaseCount('pos_sales', 1);
    }

    public function test_mixed_return_reverses_actual_margin_and_report_shows_net_values(): void
    {
        $product = $this->product('1');
        $purchase = $this->request($product, '2');
        $this->buy($purchase, '1', 'personal');
        $sale = $this->checkout($product, $purchase, '2');
        $this->actingAs($this->manager)->get(route('retail.sales.return', $sale))
            ->assertOk()->assertSee('data-mobile-table="off"', false)->assertSee('Qty darurat');
        $this->pos->returnSale($sale, [
            'reason' => 'Retur darurat', 'resolution' => 'refund', 'refund_method' => 'cash',
            'items' => [['pos_sale_item_id' => $sale->items->first()->id, 'quantity' => '1',
                'normal_quantity' => '0', 'emergency_quantity' => '1', 'condition' => 'good']],
        ], $this->manager);
        $this->assertSame('20.00', $sale->fresh()->total_margin_amount);
        $this->assertSame('0.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
        $report = $this->actingAs($this->manager)->get(route('retail.emergency.report', ['branch_id' => $this->branch->id]));
        $report->assertOk();
        $this->assertSame('150.00', $report->viewData('spent'));
        $this->assertSame(1, $report->viewData('stockouts'));
        $this->assertEquals(0, $report->viewData('allocations')->cost);
        $this->assertEquals(0, $report->viewData('allocations')->lost_margin);
    }

    public function test_unallocated_supplier_return_records_refund_and_does_not_add_stock(): void
    {
        $product = $this->product('0');
        $purchase = $this->request($product, '1');
        $this->buy($purchase, '1', 'personal');
        $this->emergency->cancel($purchase, $this->staff, 'Pelanggan batal');
        $this->emergency->supplierReturn($purchase, $this->manager, 'RT-001', '140.00', 'purchaser');
        $this->assertSame('supplier_returned', $purchase->fresh()->status);
        $this->assertSame('10.00', $purchase->fresh()->reimbursement_amount);
        $this->assertSame('1.0000', $purchase->items->first()->fresh()->supplier_returned_quantity);
        $report = $this->actingAs($this->manager)->get(route('retail.emergency.report', ['branch_id' => $this->branch->id]));
        $this->assertSame('10.00', $report->viewData('spent'));
        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_supplier_cash_refund_is_counted_in_receiving_shift_once(): void
    {
        $product = $this->product('0');
        $purchase = $this->request($product, '1');
        $this->buy($purchase, '1', 'store_cash');
        $this->emergency->cancel($purchase, $this->staff, 'Pelanggan batal');
        $shift = CashShift::query()->where('number', 'SHIFT-EM-1')->firstOrFail();
        $this->emergency->supplierReturn($purchase, $this->manager, 'REFUND-EM-1', '140.00', 'store', 'cash', $shift->id);
        $summary = app(CashShiftService::class)->summary($shift);
        $this->assertSame('140.00', $summary['supplier_refunds']);
        $this->assertSame('990.00', $summary['expected_cash']);
        $this->assertDatabaseCount('shift_expenses', 1);
    }

    public function test_routes_enforce_store_access_and_uploaded_receipt_is_private(): void
    {
        Storage::fake('local');
        $product = $this->product('0');
        $purchase = $this->request($product, '1');
        $this->actingAs($this->cashier)->post(route('retail.emergency.purchased', $purchase), [
            'supplier_name' => 'Pemasok A', 'fund_source' => 'personal',
            'receipt' => UploadedFile::fake()->create('nota.pdf', 12, 'application/pdf'),
            'items' => [['id' => $purchase->items->first()->id,
                'purchased_quantity' => '1', 'unit_cost' => '150.00']],
        ])->assertRedirect();
        $this->assertSame('purchased', $purchase->fresh()->status);
        Storage::disk('local')->assertExists($purchase->fresh()->receipt_path);
        $this->actingAs($this->staff)->get(route('retail.emergency.receipt', $purchase))->assertOk();
        $otherLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-OTHER']);
        $outsider = User::factory()->create(['is_active' => true]);
        $outsider->assignRole(Role::findOrCreate('staf_toko'));
        $outsider->workLocations()->sync([$otherLocation->id => ['is_default' => true, 'is_active' => true]]);
        $this->actingAs($outsider)->get(route('retail.emergency.show', $purchase))->assertForbidden();
        $this->actingAs($outsider)->get(route('retail.emergency.receipt', $purchase))->assertForbidden();
        $this->actingAs($outsider)->get(route('retail.emergency.receipt-preview', $purchase))->assertForbidden();
        $this->actingAs($this->staff)->post(route('retail.emergency.reimburse', $purchase), [])->assertForbidden();
    }

    public function test_uploaded_image_receipt_is_previewed_inline_and_opens_in_a_new_tab(): void
    {
        Storage::fake('local');
        $product = $this->product('0');
        $purchase = $this->request($product, '1');

        $this->actingAs($this->cashier)->post(route('retail.emergency.purchased', $purchase), [
            'supplier_name' => 'Pemasok Foto', 'fund_source' => 'personal',
            'receipt' => UploadedFile::fake()->image('nota.jpg', 800, 600),
            'items' => [['id' => $purchase->items->first()->id,
                'purchased_quantity' => '1', 'unit_cost' => '150.00']],
        ])->assertRedirect();

        $previewUrl = route('retail.emergency.receipt-preview', $purchase);
        $this->actingAs($this->staff)->get(route('retail.emergency.show', $purchase))
            ->assertOk()
            ->assertSee('Nota pembelian')
            ->assertSee('Klik gambar untuk membuka ukuran penuh di tab baru.')
            ->assertSee('target="_blank"', false)
            ->assertSee('src="'.$previewUrl.'"', false);

        $preview = $this->actingAs($this->staff)->get($previewUrl);
        $preview->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->assertStringStartsWith('inline;', (string) $preview->headers->get('content-disposition'));
    }

    public function test_storefront_form_list_detail_preview_and_report_render_for_assigned_roles(): void
    {
        $product = $this->product('0');
        $purchase = $this->request($product, '1');

        $this->actingAs($this->staff)->get(route('retail.emergency.create'))
            ->assertOk()->assertSee('Pembelian Darurat');
        $this->get(route('retail.emergency.index'))->assertOk()->assertSee($purchase->number);
        $this->get(route('retail.emergency.show', $purchase))->assertOk()->assertSee($purchase->number);
        $this->get(route('retail.emergency.preview', [
            'branch_id' => $this->branch->id, 'product_id' => $product->id, 'quantity' => '1',
        ]))->assertOk()->assertJsonPath('stock_base', '0.0000');

        $this->actingAs($this->manager)->get(route('retail.emergency.report'))
            ->assertOk()
            ->assertSee('Uang keluar bersih')
            ->assertSee('Tambahan biaya dari harga pokok biasa')
            ->assertSee('Saat stok toko tidak cukup')
            ->assertSee('Cara membaca laporan')
            ->assertSee('Disetujui, belum dibeli')
            ->assertDontSee('COGS darurat bersih')
            ->assertDontSee('Lost margin bersih')
            ->assertDontSee('Kejadian stockout')
            ->assertDontSee('HPP biasa');
        $this->actingAs($this->staff)->get(route('retail.emergency.report'))->assertForbidden();
    }

    public function test_forward_to_existing_po_never_posts_stock_and_requires_posted_receipt(): void
    {
        $product = $this->product('0');
        $purchase = $this->request($product, '1');
        $this->buy($purchase, '1', 'personal');
        $this->emergency->cancel($purchase, $this->staff, 'Pelanggan batal');
        $warehouse = Warehouse::query()->firstOrFail();
        $supplier = Supplier::factory()->create();
        $order = PurchaseOrder::query()->create([
            'number' => 'PO-EM-1', 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'order_date' => today(),
            'status' => PurchaseOrderStatus::APPROVED, 'created_by' => $this->manager->id,
        ]);
        $poItem = $order->items()->create([
            'product_id' => $product->id, 'unit_id' => $this->unit->id,
            'product_sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name,
            'unit_name_snapshot' => $this->unit->name, 'conversion_factor_snapshot' => '1.000000',
            'quantity_ordered' => '1.0000', 'unit_price' => '150.00',
        ]);
        $this->emergency->forwardToWarehouse($purchase, $order, $this->manager);
        $this->assertSame('warehouse_pending', $purchase->fresh()->status);
        $this->assertDatabaseCount('stocks', 0);
        $receipt = GoodsReceipt::query()->create([
            'number' => 'GR-EM-1', 'purchase_order_id' => $order->id,
            'warehouse_id' => $warehouse->id, 'supplier_id' => $supplier->id,
            'received_at' => today(), 'received_by' => $this->manager->id,
            'status' => GoodsReceiptStatus::DRAFT,
        ]);
        $receipt->items()->create([
            'purchase_order_item_id' => $poItem->id, 'product_id' => $product->id,
            'unit_id' => $this->unit->id, 'product_sku_snapshot' => $product->sku,
            'product_name_snapshot' => $product->name, 'unit_name_snapshot' => $this->unit->name,
            'quantity_ordered' => '1.0000', 'quantity_accepted' => '1.0000',
            'conversion_factor_snapshot' => '1.000000',
        ]);
        try {
            $this->emergency->completeWarehouse($purchase, $receipt, $this->manager);
            $this->fail('Penerimaan draft tidak boleh menutup pembelian darurat.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('posted', $exception->getMessage());
        }
        $receipt->forceFill(['status' => GoodsReceiptStatus::POSTED, 'posted_at' => now()])->save();
        $this->emergency->completeWarehouse($purchase, $receipt, $this->manager);
        $this->assertSame('warehouse_received', $purchase->fresh()->status);
        $this->assertSame('1.0000', $purchase->items->first()->fresh()->warehouse_quantity);
        $this->assertDatabaseCount('stocks', 0);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role));
        $user->workLocations()->sync([$this->location->id => ['is_default' => true, 'is_active' => true]]);

        return $user;
    }

    private function shift(string $number): CashShift
    {
        return CashShift::query()->create([
            'number' => $number, 'branch_id' => $this->branch->id,
            'work_location_id' => $this->location->id, 'cashier_user_id' => $this->cashier->id,
            'opened_by' => $this->cashier->id, 'status' => CashShiftStatus::OPEN,
            'opening_cash_amount' => '1000.00', 'expected_cash_amount' => '1000.00',
            'opened_at' => now(),
        ]);
    }

    private function product(string $stock): Product
    {
        $product = Product::factory()->create(['base_unit_id' => $this->unit->id,
            'cost_price' => '100.00', 'minimum_price' => '0.00']);
        if ($stock !== '0') {
            app(InventoryService::class)->receive($product, $this->location, null, $stock,
                $this->cashier, ['type' => 'opening', 'no' => 'OPEN-EM']);
        }

        return $product;
    }

    private function request(Product $product, string $quantity): EmergencyPurchase
    {
        return $this->emergency->confirm(['branch_id' => $this->branch->id,
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id,
                'quantity' => $quantity, 'unit_cost' => '150.00']]], $this->staff);
    }

    private function buy(EmergencyPurchase $purchase, string $quantity, string $fund): void
    {
        $this->emergency->purchased($purchase, [
            'supplier_name' => 'Pemasok A', 'fund_source' => $fund,
            'receipt_path' => 'receipt.pdf',
            'items' => [['id' => $purchase->items->first()->id,
                'purchased_quantity' => $quantity, 'unit_cost' => '150.00']],
        ], $this->cashier);
    }

    private function checkout(Product $product, EmergencyPurchase $purchase, string $quantity)
    {
        return $this->pos->checkout([
            'branch_id' => $this->branch->id, 'emergency_purchase_id' => $purchase->id,
            'idempotency_key' => (string) str()->uuid(),
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id,
                'quantity' => $quantity]],
            'payments' => [['method' => 'cash', 'amount' => '100000.00']],
        ], $this->cashier);
    }

    private function checkoutWithoutRequest(Product $product, string $quantity)
    {
        return $this->pos->checkout([
            'branch_id' => $this->branch->id,
            'idempotency_key' => (string) str()->uuid(),
            'items' => [['product_id' => $product->id, 'unit_id' => $this->unit->id,
                'quantity' => $quantity]],
            'payments' => [['method' => 'cash', 'amount' => '1000.00']],
        ], $this->cashier);
    }
}
