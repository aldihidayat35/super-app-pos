<?php

namespace Tests\Feature\Tax;

use App\Enums\CashShiftStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProductPriceStatus;
use App\Enums\TaxPeriodStatus;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\TaxDocument;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\TaxRule;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Retail\PosService;
use App\Services\Tax\TaxComplianceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaxComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole(Role::findByName('owner_approver'));
    }

    public function test_hanya_owner_approver_dan_super_admin_yang_dapat_mengakses_modul_pajak(): void
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(Role::findByName('super_admin'));
        $ownerViewer = User::factory()->create(['is_active' => true]);
        $ownerViewer->assignRole(Role::findByName('owner_viewer'));
        $adminConfig = User::factory()->create(['is_active' => true]);
        $adminConfig->assignRole(Role::findByName('admin_config'));

        $this->actingAs($this->owner)->get(route('tax.index'))->assertOk()->assertSee('Pajak &amp; Kepatuhan', false);
        $this->actingAs($this->owner)->get(route('tax.settings'))->assertOk()->assertSee('Profil Pajak Perusahaan');
        $this->actingAs($superAdmin)->get(route('tax.index'))->assertOk();
        $this->actingAs($ownerViewer)->get(route('tax.index'))->assertForbidden();
        $this->actingAs($adminConfig)->get(route('tax.index'))->assertForbidden();

        $this->assertTrue($this->owner->can('tax.access'));
        $this->assertTrue($this->owner->can('tax.manage'));
        $this->assertTrue($this->owner->can('tax.approve'));
        $this->assertTrue($this->owner->can('tax.export'));
        $this->assertFalse($ownerViewer->can('tax.access'));
        $this->assertFalse($adminConfig->can('tax.access'));
    }

    public function test_profil_aturan_dan_klasifikasi_mengaktifkan_kalkulasi_ppn_bertanggal_efektif(): void
    {
        $this->actingAs($this->owner)->post(route('tax.rules.store'), [
            'code' => 'PPN-NONMEWAH-2025',
            'name' => 'PPN BKP/JKP Nonmewah',
            'tax_type' => 'ppn',
            'direction' => 'both',
            'rate' => 12,
            'dpp_factor' => '0.91666667',
            'luxury_tax_rate' => 0,
            'is_creditable' => 1,
            'effective_from' => now()->startOfYear()->toDateString(),
            'is_active' => 1,
        ])->assertRedirect();
        $rule = TaxRule::query()->where('code', 'PPN-NONMEWAH-2025')->firstOrFail();
        $product = Product::factory()->create();

        $this->actingAs($this->owner)->post(route('tax.profile.store'), [
            'legal_name' => 'PT Gudang Toko',
            'tax_number' => '1234567890123456',
            'is_pkp' => 1,
            'pkp_effective_date' => now()->startOfYear()->toDateString(),
            'calculation_enabled' => 1,
            'default_output_tax_rule_id' => $rule->id,
        ])->assertRedirect();
        $this->actingAs($this->owner)->put(route('tax.products.update', $product), [
            'tax_rule_id' => $rule->id,
            'tax_category_code' => 'BKP-NONMEWAH',
            'is_taxable' => 1,
        ])->assertRedirect();

        $calculation = app(TaxComplianceService::class)->calculate($product->fresh(), '100000.00');

        $this->assertTrue($calculation['enabled']);
        $this->assertSame('91666.67', $calculation['dpp_amount']);
        $this->assertSame('11000.00', $calculation['tax_amount']);
        $this->assertSame('111000.00', $calculation['total_amount']);
        $this->assertDatabaseHas('tax_profiles', ['scope_key' => 'company', 'calculation_enabled' => 1, 'is_pkp' => 1]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_taxable' => 1, 'tax_category_code' => 'BKP-NONMEWAH']);
    }

    public function test_pos_baru_menyimpan_snapshot_pajak_saat_kalkulasi_diaktifkan(): void
    {
        $rule = $this->taxRule();
        TaxProfile::query()->create([
            'scope_key' => 'company',
            'legal_name' => 'PT Gudang Toko',
            'is_pkp' => true,
            'calculation_enabled' => true,
            'default_output_tax_rule_id' => $rule->id,
        ]);

        $cashier = User::factory()->create(['is_active' => true]);
        $cashier->assignRole(Role::findByName('kasir'));
        $branchLocation = WorkLocation::factory()->create(['type' => 'branch']);
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $branch = Branch::factory()->create(['work_location_id' => $branchLocation->id, 'primary_warehouse_id' => $warehouse->id]);
        $cashier->workLocations()->sync([$branchLocation->id => ['is_default' => true, 'is_active' => true]]);
        $unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'cost_price' => 50, 'minimum_price' => 0, 'tax_rule_id' => $rule->id, 'is_taxable' => true]);
        PriceRule::query()->create([
            'name' => 'Aturan Harga POS Pajak', 'channel' => 'all', 'margin_method' => 'percent',
            'minimum_margin_percent' => 0, 'minimum_margin_amount' => 0, 'overpricing_tolerance_percent' => 100,
            'max_discount_percent' => 100, 'priority' => 1, 'is_active' => true,
        ]);
        CashShift::query()->create([
            'number' => 'SHIFT-TAX-1', 'branch_id' => $branch->id, 'work_location_id' => $branchLocation->id,
            'cashier_user_id' => $cashier->id, 'opened_by' => $cashier->id, 'status' => CashShiftStatus::OPEN,
            'opening_cash_amount' => 0, 'expected_cash_amount' => 0, 'opened_at' => now(),
        ]);
        app(InventoryService::class)->receive($product, $branchLocation, null, '10', $cashier, ['type' => 'opening', 'no' => 'OPEN-TAX']);

        $sale = app(PosService::class)->checkout([
            'branch_id' => $branch->id,
            'idempotency_key' => (string) str()->uuid(),
            'items' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => 1, 'selected_price' => 100, 'discount_percent' => 0]],
            'payments' => [['method' => 'cash', 'amount' => 111]],
        ], $cashier);

        $this->assertSame('11.00', $sale->tax_amount);
        $this->assertSame('111.00', $sale->grand_total_amount);
        $this->assertSame('11.00', $sale->items->first()->tax_amount);
        $this->assertSame($rule->id, $sale->items->first()->price_snapshot['tax']['rule_id']);
    }

    public function test_order_sales_b2b_baru_menyimpan_pajak_dan_grand_total_setelah_pajak(): void
    {
        $rule = $this->taxRule();
        TaxProfile::query()->create([
            'scope_key' => 'company',
            'legal_name' => 'PT Gudang Toko',
            'is_pkp' => true,
            'calculation_enabled' => true,
            'default_output_tax_rule_id' => $rule->id,
        ]);
        $sales = User::factory()->create(['is_active' => true]);
        $sales->assignRole(Role::findByName('sales'));
        $customer = Customer::factory()->create(['sales_user_id' => $sales->id]);
        $address = CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'label' => 'Utama',
            'address' => 'Jl. Pajak',
            'is_primary' => true,
            'primary_scope' => 'primary',
        ]);
        $unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $product = Product::factory()->create([
            'base_unit_id' => $unit->id,
            'minimum_order' => 1,
            'minimum_price' => 8000,
            'tax_rule_id' => $rule->id,
            'is_taxable' => true,
        ]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'channel' => 'b2b',
            'price_ring' => 'grosir',
            'customer_category' => 'grosir',
            'recommended_price' => 10000,
            'minimum_qty' => 1,
            'status' => ProductPriceStatus::ACTIVE,
        ]);

        $this->actingAs($sales)->post(route('sales.orders.store'), [
            'customer_id' => $customer->id,
            'customer_address_id' => $address->id,
            'delivery_method' => 'courier',
            'payment_preference' => 'credit',
            'terms_accepted' => 1,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertRedirect();

        $this->assertDatabaseHas('b2b_orders', [
            'customer_id' => $customer->id,
            'sales_user_id' => $sales->id,
            'subtotal_amount' => 20000,
            'tax_amount' => 2200,
            'grand_total_amount' => 22200,
        ]);
        $this->assertDatabaseHas('b2b_order_items', ['product_id' => $product->id, 'tax_amount' => 2200, 'line_total' => 22200]);
    }

    public function test_dokumen_manual_menghitung_rekap_dan_masa_mengikuti_state_machine_sampai_dikunci(): void
    {
        $date = now()->startOfMonth()->toDateString();
        $output = $this->manualDocumentPayload($date, 'output', 'FP-OUT-001', 2000000, 240000);
        $input = $this->manualDocumentPayload($date, 'input', 'FP-IN-001', 1000000, 120000);

        $this->actingAs($this->owner)->post(route('tax.documents.store'), $output)->assertRedirect();
        $this->actingAs($this->owner)->post(route('tax.documents.store'), $input)->assertRedirect();
        $period = TaxPeriod::query()->firstOrFail();

        $this->assertSame('240000.00', $period->output_tax_amount);
        $this->assertSame('120000.00', $period->creditable_input_tax_amount);
        $this->assertSame('120000.00', $period->payable_amount);

        foreach (['review', 'approve'] as $action) {
            $this->actingAs($this->owner)->post(route('tax.periods.transition', $period), ['action' => $action])->assertRedirect();
            $period->refresh();
        }
        $this->actingAs($this->owner)->post(route('tax.periods.transition', $period), ['action' => 'report', 'filing_reference' => 'SPT-PPN-001'])->assertRedirect();
        $period->refresh();
        $this->actingAs($this->owner)->post(route('tax.periods.transition', $period), ['action' => 'pay', 'payment_reference' => 'NTPN-001'])->assertRedirect();
        $period->refresh();
        $this->actingAs($this->owner)->post(route('tax.periods.transition', $period), ['action' => 'lock'])->assertRedirect();

        $this->assertSame(TaxPeriodStatus::LOCKED, $period->fresh()->status);
        $this->actingAs($this->owner)->from(route('tax.index'))->post(route('tax.documents.store'), $this->manualDocumentPayload($date, 'input', 'FP-IN-LOCKED', 100, 12))
            ->assertRedirect(route('tax.index'))
            ->assertSessionHasErrors('document');

        $this->actingAs($this->owner)->post(route('tax.periods.transition', $period), ['action' => 'reopen', 'notes' => 'Pembetulan SPT'])->assertRedirect();
        $period->refresh();
        $this->assertSame(TaxPeriodStatus::OPEN, $period->status);
        $this->assertNotNull($period->reopened_at);
    }

    public function test_sinkronisasi_invoice_membuat_register_dan_export_csv(): void
    {
        $customer = Customer::factory()->create([
            'business_name' => 'PT Pembeli Pajak',
            'tax_number' => '1234567890123456',
            'tax_address' => 'Jakarta',
        ]);
        $invoice = Invoice::query()->create([
            'number' => 'INV-TAX-001',
            'source_type' => 'b2b_order',
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::ISSUED,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal_amount' => 100000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 11000,
            'total_amount' => 111000,
            'paid_amount' => 0,
            'outstanding_amount' => 111000,
            'issued_at' => now(),
            'created_by' => $this->owner->id,
            'issued_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->post(route('tax.sync', ['month' => now()->month, 'year' => now()->year]))->assertRedirect();
        $period = TaxPeriod::query()->firstOrFail();

        $this->assertDatabaseHas('tax_documents', [
            'source_key' => 'invoice:'.$invoice->id,
            'document_number' => 'INV-TAX-001',
            'tax_amount' => 11000,
        ]);
        $this->assertSame('11000.00', $period->output_tax_amount);
        $export = $this->actingAs($this->owner)->get(route('tax.periods.export', $period));
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('INV-TAX-001', $export->streamedContent());

        $document = TaxDocument::query()->firstOrFail();
        $this->actingAs($this->owner)->post(route('tax.documents.reconcile', $document), ['coretax_reference' => 'CORETAX-001'])->assertRedirect();
        $this->assertDatabaseHas('tax_documents', ['id' => $document->id, 'reconciliation_status' => 'matched', 'coretax_reference' => 'CORETAX-001']);
    }

    private function taxRule(): TaxRule
    {
        return TaxRule::query()->create([
            'code' => 'PPN-NONMEWAH-2025',
            'name' => 'PPN BKP/JKP Nonmewah',
            'tax_type' => 'ppn',
            'direction' => 'both',
            'rate' => '12.0000',
            'dpp_factor' => '0.91666667',
            'luxury_tax_rate' => '0.0000',
            'is_creditable' => true,
            'effective_from' => now()->startOfYear()->toDateString(),
            'is_active' => true,
            'created_by' => $this->owner->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function manualDocumentPayload(string $date, string $direction, string $number, int $dpp, int $tax): array
    {
        return [
            'direction' => $direction,
            'tax_type' => 'ppn',
            'document_type' => $direction === 'input' ? 'supplier_invoice' : 'tax_invoice',
            'document_number' => $number,
            'counterparty_type' => $direction === 'input' ? 'supplier' : 'customer',
            'counterparty_name' => 'PT Lawan Transaksi',
            'counterparty_tax_number' => '1234567890123456',
            'issue_date' => $date,
            'tax_date' => $date,
            'dpp_amount' => $dpp,
            'tax_rate' => 12,
            'dpp_factor' => 1,
            'tax_amount' => $tax,
            'is_creditable' => 1,
        ];
    }
}
