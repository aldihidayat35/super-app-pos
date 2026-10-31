<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockTransferDiscrepancyResolutionType;
use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Warehouse\StockTransferService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockTransferM3EmpiricalChallengeTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private StockTransferService $transfers;

    private User $warehouseHead;

    private User $warehouseStaff;

    private User $storeHead;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->inventory = app(InventoryService::class);
        $this->transfers = app(StockTransferService::class);

        $this->warehouseHead = User::factory()->create(['name' => 'Kepala Gudang Budi', 'is_active' => true]);
        $this->warehouseHead->assignRole(Role::findOrCreate('kepala_gudang'));

        $this->warehouseStaff = User::factory()->create(['name' => 'Staff Gudang Siti', 'is_active' => true]);
        $this->warehouseStaff->assignRole(Role::findOrCreate('staff_gudang'));

        $this->storeHead = User::factory()->create(['name' => 'Kepala Toko Joko', 'is_active' => true]);
        $this->storeHead->assignRole(Role::findOrCreate('kepala_toko'));

        $this->cashier = User::factory()->create(['name' => 'Kasir Rina', 'is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
    }

    /**
     * Requirement 1: Verifikasi empiris rendering show.blade.php pada SEMUA status transfer
     * (DRAFT, PENDING_APPROVAL, APPROVED, PACKING, SHIPPED, PARTIALLY_RECEIVED, FULLY_RECEIVED, COMPLETED, CANCELLED).
     */
    public function test_show_page_renders_all_stages_successfully(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '50', $this->warehouseHead);

        // 1. DRAFT
        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => '10',
                'quantity_approved' => '10',
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_warehouse_location_id' => $destinationBin->id,
            ]],
            'action' => 'draft',
        ], $this->warehouseStaff);

        $responseDraft = $this->actingAs($this->warehouseStaff)->get(route('warehouse.stock-transfers.show', $transfer));
        $responseDraft->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Ajukan untuk Persetujuan')
            ->assertSee('Penanggung Jawab Berikutnya')
            ->assertSee('Staff Gudang')
            ->assertSee('Staff Gudang Siti')
            ->assertSee('Produk & Satuan', false)
            ->assertSee('Rencana (Diminta / Disetujui)')
            ->assertSee('Pengiriman (Diambil / Dikirim)')
            ->assertSee('Penerimaan & Status', false)
            ->assertSee('Belum ada pengiriman');

        // 2. PENDING_APPROVAL
        $this->transfers->submit($transfer, $this->warehouseStaff);
        $responsePending = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $responsePending->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Periksa dan Setujui Transfer')
            ->assertSee('Kepala Gudang')
            ->assertSee('Kepala Gudang Budi')
            ->assertSee('Setujui Transfer');

        // 3. APPROVED
        $this->transfers->approve($transfer, $this->warehouseHead);
        $responseApproved = $this->actingAs($this->warehouseStaff)->get(route('warehouse.stock-transfers.show', $transfer));
        $responseApproved->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Mulai Pengepakan (Packing)')
            ->assertSee('Buka Halaman Packing');

        // 4. PACKING
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, [
            'package_no' => 'PKG-EXP-01',
            'items' => [$item->id => ['quantity_picked' => '10']],
        ], $this->warehouseStaff);
        $responsePacking = $this->actingAs($this->warehouseStaff)->get(route('warehouse.stock-transfers.show', $transfer));
        $responsePacking->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Kirim Barang (Dispatch/Ship)')
            ->assertSee('Buka Halaman Pengiriman')
            ->assertSee('Perbarui Packing');

        // 5. SHIPPED
        $this->transfers->ship($transfer, [
            'carrier' => 'Ekspedisi Express',
            'tracking_number' => 'EXP-999888',
        ], $this->warehouseStaff);
        $responseShipped = $this->actingAs($this->storeHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $responseShipped->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Terima Barang di Lokasi Tujuan')
            ->assertSee('Proses Terima Barang')
            ->assertSee(route('retail.stock-transfers.receive-form', $transfer), false);

        // 6. PARTIALLY_RECEIVED (with discrepancy)
        $this->transfers->receive($transfer, [
            'idempotency_key' => 'm3-rec-part-1',
            'items' => [$item->id => [
                'quantity_received' => '6',
                'quantity_damaged' => '1',
                'quantity_discrepancy' => '3',
            ]],
        ], $this->storeHead);
        $responsePartial = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $responsePartial->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Selesaikan Selisih Penerimaan')
            ->assertSee('Selesaikan Selisih')
            ->assertSee('Perhatian Operasional')
            ->assertSee('Ada barang rusak')
            ->assertSee('Ada selisih pengiriman')
            ->assertSee('1 Rusak')
            ->assertSee('3 Selisih');

        // Selesaikan selisih agar dokumen dapat dituntaskan
        $this->actingAs($this->warehouseHead)->post(route('warehouse.stock-transfers.resolve-discrepancy', $transfer), [
            'stock_transfer_item_id' => $item->id,
            'idempotency_key' => 'm3-resolve-discrepancy-test',
            'resolution_type' => StockTransferDiscrepancyResolutionType::INVENTORY_LOSS->value,
            'quantity' => 3,
            'notes' => 'Penyelesaian selisih 3 unit hilang di jalan',
        ])->assertRedirect();

        // 7. FULLY_RECEIVED
        $transferFresh = $transfer->fresh();
        $this->assertSame(StockTransferStatus::FULLY_RECEIVED, $transferFresh->status);
        $responseFullyReceived = $this->actingAs($this->storeHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $responseFullyReceived->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Selesaikan Transfer');

        // 8. COMPLETED
        $this->transfers->complete($transferFresh, $this->storeHead);
        $responseCompleted = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $responseCompleted->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Transfer Selesai')
            ->assertSee('Cetak Surat Jalan');

        // 9. CANCELLED (pada transfer lain yang dibatalkan)
        $cancelTransfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => '5',
                'quantity_approved' => '5',
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_warehouse_location_id' => $destinationBin->id,
            ]],
            'action' => 'draft',
        ], $this->warehouseStaff);
        $this->transfers->cancel($cancelTransfer, $this->warehouseHead, 'Dibatalkan karena salah pesan');
        $responseCancelled = $this->actingAs($this->warehouseStaff)->get(route('warehouse.stock-transfers.show', $cancelTransfer));
        $responseCancelled->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Transfer Dibatalkan')
            ->assertSee('Buat Transfer Baru')
            ->assertSee('Dokumen ini telah dibatalkan');
    }

    /**
     * Requirement 2: Saat status PENDING_APPROVAL dan stok sumber KURANG:
     * - Tombol submit harus memiliki atribut disabled (@disabled($approvalBlocked))
     * - Pesan peringatan stok kurang harus muncul
     * - Hero Card beraksen border-warning bg-light-warning
     */
    public function test_hero_card_action_button_behavior_on_pending_approval_insufficient_stock(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        // Hanya isi stok 4 unit di sumber
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '4', $this->warehouseHead);

        // Buat transfer meminta 10 unit (stok kurang 6 unit)
        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => '10',
                'quantity_approved' => '10',
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_warehouse_location_id' => $destinationBin->id,
            ]],
            'action' => 'submit',
        ], $this->warehouseStaff);

        $this->assertSame(StockTransferStatus::PENDING_APPROVAL, $transfer->status);

        $response = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertIsString($content);

        // 1. Assert hero card styling shows warning accent
        $this->assertStringContainsString('border-warning bg-light-warning', $content);

        // 2. Assert warning alert appears
        $this->assertStringContainsString('Peringatan Stok Kurang', $content);
        $this->assertStringContainsString('Beberapa produk di gudang asal tidak mencukupi untuk dialokasikan', $content);

        // 3. Assert hero card submit button is disabled
        // Pattern: <button type="submit" class="btn btn-success btn-lg fs-5 fw-bolder px-8 py-4 shadow-sm" disabled>
        $this->assertMatchesRegularExpression('/<button[^>]*class="[^"]*btn-success[^"]*"[^>]*disabled/i', $content);

        // 4. Assert sidebar also indicates approval disabled
        $this->assertStringContainsString('Approval dinonaktifkan sampai stok sumber mencukupi', $content);
        $this->assertStringContainsString('Kurang 6', $content);
    }

    /**
     * Requirement 2b: Saat status PENDING_APPROVAL dan stok sumber CUKUP:
     * - Tombol submit aktif (tidak ada disabled)
     * - Hero Card beraksen border-success bg-light-success
     * - Tidak ada alert "Peringatan Stok Kurang"
     */
    public function test_hero_card_action_button_behavior_on_pending_approval_sufficient_stock(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        // Isi stok 25 unit (kebutuhan 10 unit)
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '25', $this->warehouseHead);

        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => '10',
                'quantity_approved' => '10',
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_warehouse_location_id' => $destinationBin->id,
            ]],
            'action' => 'submit',
        ], $this->warehouseStaff);

        $response = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertIsString($content);

        // Hero card styling shows success accent
        $this->assertStringContainsString('border-success bg-light-success', $content);

        // No warning alert
        $this->assertStringNotContainsString('Peringatan Stok Kurang', $content);

        // Tombol submit Setujui Transfer tidak boleh memiliki atribut disabled
        // Cek secara spesifik pada form hero card
        $this->assertMatchesRegularExpression('/<button type="submit" class="btn btn-success btn-lg fs-5 fw-bolder px-8 py-4 shadow-sm"\s*>/i', $content);
    }

    /**
     * Requirement 2c: Saat status SHIPPED:
     * - Tombol "Proses Terima Barang" mengarah ke route retail.stock-transfers.receive-form
     */
    public function test_hero_card_action_button_on_shipped_points_to_retail_receive_form(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '20', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $this->transfers->ship($transfer, ['carrier' => 'Armada Sendiri'], $this->warehouseStaff);

        $this->assertSame(StockTransferStatus::SHIPPED, $transfer->fresh()->status);

        $expectedUrl = route('retail.stock-transfers.receive-form', $transfer);

        $response = $this->actingAs($this->storeHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $response->assertOk()
            ->assertSee('Proses Terima Barang')
            ->assertSee($expectedUrl, false);
    }

    /**
     * Requirement 3: Verifikasi rendering tabel kuantitas 4 kolom pada skenario transfer
     * multi-item dan multi-unit.
     */
    public function test_four_column_quantity_table_multi_item_and_multi_unit_rendering(): void
    {
        [$warehouse, $branch] = $this->warehouseAndBranch('MULTI');
        $this->assignScope($warehouse, $branch);

        // Item 1: Satuan Dasar PCS
        $pcsUnit = Unit::factory()->create(['code' => 'PCS', 'name' => 'PCS']);
        $product1 = Product::factory()->create([
            'sku' => 'PRD-PCS-01',
            'name' => 'Kopi Robusta 250g',
            'base_unit_id' => $pcsUnit->id,
            'status' => 'active',
        ]);
        ProductUnit::query()->create([
            'product_id' => $product1->id,
            'unit_id' => $pcsUnit->id,
            'name' => 'PCS',
            'conversion_factor' => '1',
            'is_base' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        // Item 2: Satuan Multi-unit Dus (1 Dus = 12 PCS)
        $dusUnit = Unit::factory()->create(['code' => 'DUS', 'name' => 'Dus']);
        $product2 = Product::factory()->create([
            'sku' => 'PRD-DUS-02',
            'name' => 'Teh Melati Celup',
            'base_unit_id' => $pcsUnit->id,
            'status' => 'active',
        ]);
        ProductUnit::query()->create([
            'product_id' => $product2->id,
            'unit_id' => $pcsUnit->id,
            'name' => 'PCS',
            'conversion_factor' => '1',
            'is_base' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);
        ProductUnit::query()->create([
            'product_id' => $product2->id,
            'unit_id' => $dusUnit->id,
            'name' => 'Dus',
            'conversion_factor' => '12',
            'is_base' => false,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $sourceBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'BIN-SRC-01']);
        $destBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'BIN-DST-01']);

        // Persiapkan stok
        $this->inventory->receive($product1, $warehouse->workLocation, $sourceBin, '50', $this->warehouseHead);
        $this->inventory->receive($product2, $warehouse->workLocation, $sourceBin, '100', $this->warehouseHead);

        // Buat transfer 2 item:
        // Item 1: 10 PCS diminta, disetujui 8 PCS (ada selisih persetujuan untuk menguji <del>)
        // Item 2: 2 Dus (24 PCS base) diminta, disetujui 2 Dus
        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product1->id,
                    'unit_id' => $pcsUnit->id,
                    'quantity_requested' => '10',
                    'quantity_approved' => '8',
                    'source_warehouse_location_id' => $sourceBin->id,
                    'destination_warehouse_location_id' => $destBin->id,
                ],
                [
                    'product_id' => $product2->id,
                    'unit_id' => $dusUnit->id,
                    'quantity_requested' => '2',
                    'quantity_approved' => '2',
                    'source_warehouse_location_id' => $sourceBin->id,
                    'destination_warehouse_location_id' => $destBin->id,
                ],
            ],
            'action' => 'submit',
        ], $this->warehouseStaff);

        $transfer = $this->transfers->approve($transfer, $this->warehouseHead);

        // Pack & Ship:
        // Item 1: diambil 8, dikirim 8
        // Item 2: diambil 2, dikirim 2
        $item1 = $transfer->items->where('product_id', $product1->id)->firstOrFail();
        $item2 = $transfer->items->where('product_id', $product2->id)->firstOrFail();

        $this->transfers->pack($transfer, [
            'package_no' => 'PKG-MULTI-01',
            'items' => [
                $item1->id => ['quantity_picked' => '8'],
                $item2->id => ['quantity_picked' => '2'],
            ],
        ], $this->warehouseStaff);

        $this->transfers->ship($transfer, ['carrier' => 'Truk Logistik Internal'], $this->warehouseStaff);

        // Receive parsial:
        // Item 1: terima 6, rusak 1, selisih 1 (in-transit 0)
        // Item 2: terima 2 sempurna (rusak 0, selisih 0)
        $this->transfers->receive($transfer, [
            'idempotency_key' => 'm3-rec-multi-1',
            'items' => [
                $item1->id => [
                    'quantity_received' => '6',
                    'quantity_damaged' => '1',
                    'quantity_discrepancy' => '1',
                ],
                $item2->id => [
                    'quantity_received' => '2',
                    'quantity_damaged' => '0',
                    'quantity_discrepancy' => '0',
                ],
            ],
        ], $this->storeHead);

        // Render show page
        $response = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertIsString($content);

        // 1. Verifikasi Header 4 Kolom
        $this->assertStringContainsString('Produk & Satuan', $content);
        $this->assertStringContainsString('Rencana (Diminta / Disetujui)', $content);
        $this->assertStringContainsString('Pengiriman (Diambil / Dikirim)', $content);
        $this->assertStringContainsString('Penerimaan & Status', $content);

        // 2. Verifikasi Kolom 1: Produk & Satuan Snapshots & Route Rak
        $this->assertStringContainsString('PRD-PCS-01', $content);
        $this->assertStringContainsString('Kopi Robusta 250g', $content);
        $this->assertStringContainsString('PRD-DUS-02', $content);
        $this->assertStringContainsString('Teh Melati Celup', $content);
        $this->assertStringContainsString('BIN-SRC-01', $content);
        $this->assertStringContainsString('BIN-DST-01', $content);

        // Satuan snapshot badge Dus
        $this->assertStringContainsString('<span class="badge badge-light-secondary fs-8">Dus</span>', $content);

        // 3. Verifikasi Kolom 2: Rencana (Diminta / Disetujui) & Coretan (<del>)
        // Item 1 diminta 10 disetujui 8 -> ada <del>Diminta: 10</del>
        $this->assertStringContainsString('<del>Diminta: 10</del>', $content);
        $this->assertStringContainsString('8 <span class="fs-8 text-muted fw-normal">disetujui</span>', $content);

        // Item 2 diminta 2 disetujui 2 -> tidak dicoret
        $this->assertStringContainsString('2 <span class="fs-8 text-muted fw-normal">disetujui</span>', $content);

        // 4. Verifikasi Kolom 3: Pengiriman (Diambil / Dikirim)
        $this->assertStringContainsString('Dikirim:</span>', $content);
        $this->assertStringContainsString('Diambil: 8', $content);
        $this->assertStringContainsString('Diambil: 2', $content);

        // 5. Verifikasi Kolom 4: Visual Status Penerimaan
        // Item 1: Dikirim: 8 -> Diterima: 6, 1 Rusak, 1 Selisih
        $this->assertStringContainsString('Dikirim: 8', $content);
        $this->assertStringContainsString('Diterima: 6', $content);
        $this->assertStringContainsString('1 Rusak', $content);
        $this->assertStringContainsString('1 Selisih', $content);

        // Item 2: Diterima 2 sempurna -> Badge Sempurna
        $this->assertStringContainsString('Dikirim: 2', $content);
        $this->assertStringContainsString('Diterima: 2', $content);
        $this->assertStringContainsString('Sempurna', $content);
    }

    /**
     * Requirement 4: Verifikasi state hero card ketika user login memiliki izin melihat tetapi tidak memiliki otorisasi tindakan
     * (misal: Staff Gudang melihat transfer pending_approval dan shipped).
     */
    public function test_hero_card_displays_waiting_state_when_user_lacks_permission(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '20', $this->warehouseHead);

        // 1. Status PENDING_APPROVAL: Staff Gudang punya izin view, tetapi tidak punya izin approve
        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => '5',
                'quantity_approved' => '5',
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_warehouse_location_id' => $destinationBin->id,
            ]],
            'action' => 'submit',
        ], $this->warehouseStaff);

        $responsePending = $this->actingAs($this->warehouseStaff)->get(route('warehouse.stock-transfers.show', $transfer));
        $responsePending->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Menunggu Kepala Gudang')
            ->assertDontSee('button type="submit" class="btn btn-success btn-lg', false);

        // 2. Status SHIPPED: Staff Gudang asal punya izin view, tetapi tidak punya izin receive barang di tujuan
        $transfer = $this->transfers->approve($transfer, $this->warehouseHead);
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Ekspedisi'], $this->warehouseStaff);

        $responseShipped = $this->actingAs($this->warehouseStaff)->get(route('warehouse.stock-transfers.show', $transfer));
        $responseShipped->assertOk()
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Menunggu Petugas Cabang Tujuan')
            ->assertDontSee('Proses Terima Barang');
    }

    /**
     * Requirement 5: Robustness & edge cases:
     * - Transfer tanpa restock request
     * - Transfer tanpa bin spesifik (auto/null)
     * - Transfer tanpa tracking number & vehicle number
     * Menghasilkan tampilan yang bersih tanpa error atau exception PHP.
     */
    public function test_show_page_handles_null_optional_relationships_without_error(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => null, // null bin
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => null, // null bin
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => '5',
                'quantity_approved' => '5',
                'source_warehouse_location_id' => null,
                'destination_warehouse_location_id' => null,
            ]],
            'action' => 'draft',
        ], $this->warehouseStaff);

        // Pastikan tracking dan vehicle null
        $transfer->update([
            'tracking_number' => null,
            'vehicle_number' => null,
            'restock_request_id' => null,
        ]);

        $response = $this->actingAs($this->warehouseStaff)->get(route('warehouse.stock-transfers.show', $transfer));
        $response->assertOk()
            ->assertSee('Detail Transfer dan Timeline')
            ->assertSee('Aksi Utama Sekarang')
            ->assertSee('Ajukan untuk Persetujuan');
    }

    /** @return array{Warehouse, Branch, Product, WarehouseLocation, WarehouseLocation} */
    private function fixture(): array
    {
        [$warehouse, $branch] = $this->warehouseAndBranch('T1');
        $unit = Unit::factory()->create(['code' => 'PCS', 'name' => 'PCS']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'status' => 'active']);
        ProductUnit::query()->create(['product_id' => $product->id, 'unit_id' => $unit->id, 'name' => 'PCS', 'conversion_factor' => '1', 'is_base' => true, 'is_sellable' => true, 'is_active' => true]);
        $sourceBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'SRC-BIN']);
        $destinationBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'DST-BIN']);

        return [$warehouse, $branch, $product, $sourceBin, $destinationBin];
    }

    /** @return array{Warehouse, Branch} */
    private function warehouseAndBranch(string $suffix): array
    {
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => "GDG-$suffix"]);
        $branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => "TKO-$suffix"]);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id, 'code' => "GDG-$suffix"]);
        $branch = Branch::factory()->create(['work_location_id' => $branchLocation->id, 'primary_warehouse_id' => $warehouse->id, 'code' => "TKO-$suffix"]);

        return [$warehouse, $branch];
    }

    private function assignScope(Warehouse $warehouse, Branch $branch): void
    {
        foreach ([$this->warehouseHead, $this->warehouseStaff] as $user) {
            $user->workLocations()->syncWithoutDetaching([$warehouse->work_location_id => ['is_default' => true, 'is_active' => true]]);
        }

        $this->warehouseHead->workLocations()->syncWithoutDetaching([$branch->work_location_id => ['is_default' => false, 'is_active' => true]]);
        $this->storeHead->workLocations()->syncWithoutDetaching([$branch->work_location_id => ['is_default' => true, 'is_active' => true]]);
    }

    private function makeApprovedTransfer(Warehouse $warehouse, Branch $branch, Product $product, WarehouseLocation $sourceBin, WarehouseLocation $destinationBin, string $qty): StockTransfer
    {
        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => $qty,
                'quantity_approved' => $qty,
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_warehouse_location_id' => $destinationBin->id,
            ]],
            'action' => 'submit',
        ], $this->warehouseStaff);

        return $this->transfers->approve($transfer, $this->warehouseHead);
    }
}
