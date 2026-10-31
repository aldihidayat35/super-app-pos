<?php

namespace Tests\Feature\Warehouse;

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

class StockTransferM3UiStressChallengeTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private StockTransferService $transfers;

    private User $warehouseHead;

    private User $warehouseStaff;

    private User $storeHead;

    private User $cashier;

    private User $viewerOnlyUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->inventory = app(InventoryService::class);
        $this->transfers = app(StockTransferService::class);

        $this->warehouseHead = User::factory()->create(['is_active' => true]);
        $this->warehouseHead->assignRole(Role::findOrCreate('kepala_gudang'));

        $this->warehouseStaff = User::factory()->create(['is_active' => true]);
        $this->warehouseStaff->assignRole(Role::findOrCreate('staff_gudang'));

        $this->storeHead = User::factory()->create(['is_active' => true]);
        $this->storeHead->assignRole(Role::findOrCreate('kepala_toko'));

        $this->cashier = User::factory()->create(['is_active' => true]);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));

        $this->viewerOnlyUser = User::factory()->create(['is_active' => true]);
        $this->viewerOnlyUser->assignRole(Role::findOrCreate('owner_viewer'));

        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);
    }

    /**
     * Uji 1: Render HTML Lengkap & Ukuran Halaman > 50KB untuk Seluruh Siklus Status Dokumen
     * Memastikan tidak ada blank render (0-byte), tidak ada kebocoran sintaks Blade, dan layout utuh.
     */
    public function test_html_render_completeness_and_body_size_exceeds_50kb(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '50', $this->warehouseHead);

        // 1. Buat Transfer di status DRAFT
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

        // Ambil respon DRAFT
        $response = $this->actingAs($this->warehouseStaff)->get(route('warehouse.stock-transfers.show', $transfer));
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertGreaterThan(50000, strlen($html), 'Ukuran render HTML harus melebihi 50KB untuk halaman penuh');
        $this->assertStringContainsStringIgnoringCase('<!doctype html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('<body', $html);
        $this->assertStringContainsString('Detail Transfer dan Timeline', $html);
        $this->assertStringContainsString('Aksi Utama Sekarang', $html);
        $this->assertStringContainsString('Perjalanan Transfer', $html);
        $this->assertStringContainsString('Produk & Satuan', $html);
        $this->assertStringContainsString('Rencana (Diminta / Disetujui)', $html);
        $this->assertStringContainsString('Pengiriman (Diambil / Dikirim)', $html);
        $this->assertStringContainsString('Penerimaan & Status', $html);

        // Pastikan tidak ada sintaks Blade mentah yang bocor keluar
        $this->assertDoesNotMatchRegularExpression('/@(if|foreach|can|php|endcan|endif|endforeach)\b/', $html);

        // 2. Transisi ke PENDING_APPROVAL
        $this->transfers->submit($transfer, $this->warehouseStaff);
        $respPending = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer))->assertOk();
        $this->assertGreaterThan(50000, strlen((string) $respPending->getContent()));

        // 3. Transisi ke APPROVED
        $this->transfers->approve($transfer, $this->warehouseHead);
        $respApproved = $this->actingAs($this->warehouseStaff)->get(route('warehouse.stock-transfers.show', $transfer))->assertOk();
        $this->assertGreaterThan(50000, strlen((string) $respApproved->getContent()));

        // 4. Transisi ke PACKING lalu SHIPPED
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '10']]], $this->warehouseStaff);
        $this->transfers->ship($transfer, ['carrier' => 'Armada Sendiri'], $this->warehouseStaff);
        $respShipped = $this->actingAs($this->storeHead)->get(route('warehouse.stock-transfers.show', $transfer))->assertOk();
        $this->assertGreaterThan(50000, strlen((string) $respShipped->getContent()));

        // 5. Transisi ke FULLY_RECEIVED lalu COMPLETED
        $this->transfers->receive($transfer, [
            'idempotency_key' => 'm3-stress-full-receive',
            'items' => [$item->id => ['quantity_received' => '10', 'quantity_damaged' => '0', 'quantity_discrepancy' => '0']],
        ], $this->storeHead);
        $this->transfers->complete($transfer, $this->storeHead);

        $respCompleted = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer))->assertOk();
        $this->assertGreaterThan(50000, strlen((string) $respCompleted->getContent()));
        $respCompleted->assertSee('Transfer Selesai');
        $respCompleted->assertSee('Cetak Surat Jalan');
    }

    /**
     * Uji 2: Skenario Stok Tidak Cukup saat PENDING_APPROVAL
     * Memastikan banner peringatan merah muncul, tombol approval disabled, dan ketika stok terpenuhi tombol aktif kembali.
     */
    public function test_insufficient_stock_at_pending_approval_shows_red_alert_and_disables_submit(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        // Sediakan 2 unit produk pertama dan 1 unit produk kedua (tapi transfer meminta 5 unit produk kedua)
        $shortProduct = Product::factory()->create(['base_unit_id' => $product->base_unit_id, 'status' => 'active', 'sku' => 'SKU-INSUFFICIENT']);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);
        $this->inventory->receive($shortProduct, $warehouse->workLocation, $sourceBin, '1', $this->warehouseHead);

        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_requested' => '5',
                    'quantity_approved' => '5',
                    'source_warehouse_location_id' => $sourceBin->id,
                    'destination_warehouse_location_id' => $destinationBin->id,
                ],
                [
                    'product_id' => $shortProduct->id,
                    'quantity_requested' => '5',
                    'quantity_approved' => '5',
                    'source_warehouse_location_id' => $sourceBin->id,
                    'destination_warehouse_location_id' => $destinationBin->id,
                ],
            ],
            'action' => 'submit',
        ], $this->warehouseStaff);

        $this->assertSame(StockTransferStatus::PENDING_APPROVAL, $transfer->status);

        // Buka halaman sebagai Kepala Gudang
        $response = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $response->assertOk();
        $html = (string) $response->getContent();

        // 1. Verifikasi kehadiran banner merah peringatan stok kurang pada Hero Card
        $response->assertSee('Peringatan Stok Kurang:');
        $response->assertSee('Beberapa produk di gudang asal tidak mencukupi untuk dialokasikan.');

        // 2. Verifikasi warna border / styling hero card mengindikasikan perhatian (border-warning bg-light-warning)
        $this->assertStringContainsString('border-warning bg-light-warning', $html);

        // 3. Verifikasi tombol submit "Setujui Transfer" pada Hero Action Card dalam keadaan disabled
        $this->assertMatchesRegularExpression(
            '/<button[^>]+type="submit"[^>]+class="[^"]*btn-success[^"]*"[^>]*disabled/i',
            $html,
            'Tombol Setujui Transfer pada Hero Card harus berstatus disabled saat stok kurang'
        );

        // 4. Verifikasi form approval sidebar juga disabled dan mencantumkan pesan peringatan
        $response->assertSee('Approval dinonaktifkan sampai stok sumber mencukupi.');
        $this->assertMatchesRegularExpression(
            '/<button[^>]+class="[^"]*btn-success[^"]*w-100"[^>]*disabled/i',
            $html,
            'Tombol Setujui & Alokasikan Stok pada sidebar harus berstatus disabled saat stok kurang'
        );

        // 5. Tambahkan stok produk kedua ke gudang sumber agar mencukupi (tambah 10 unit sehingga total 11 >= 5)
        $this->inventory->receive($shortProduct, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        // Buka kembali halaman
        $responseAfterStock = $this->actingAs($this->warehouseHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $responseAfterStock->assertOk();
        $htmlAfterStock = (string) $responseAfterStock->getContent();

        // Banner peringatan merah tidak boleh muncul lagi
        $responseAfterStock->assertDontSee('Peringatan Stok Kurang:');
        $responseAfterStock->assertDontSee('Approval dinonaktifkan sampai stok sumber mencukupi.');

        // Hero card berubah menjadi aksen hijau (border-success bg-light-success)
        $this->assertStringContainsString('border-success bg-light-success', $htmlAfterStock);

        // Tombol submit Setujui Transfer TIDAK lagi berstatus disabled
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]+type="submit"[^>]+class="[^"]*btn-success btn-lg[^"]*"[^>]*disabled/i',
            $htmlAfterStock,
            'Tombol Setujui Transfer tidak boleh disabled setelah stok mencukupi'
        );
    }

    /**
     * Uji 3: Skenario Barang Rusak, Selisih Pengiriman, In-Transit, dan Kurang Ambil
     * Memastikan banner "Perhatian Operasional" muncul dengan badge-badge indikator yang tepat,
     * serta visual baris tabel 4 kolom menampilkan rincian kerusakan dan selisih secara akurat.
     */
    public function test_damaged_goods_and_discrepancy_shows_operational_attention_and_table_badges(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '30', $this->warehouseHead);

        // 1. Buat transfer 15 unit
        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '15');
        $item = $transfer->items->firstOrFail();

        // 2. Pengepakan: pick 12 unit saja (artinya quantity_short = 3)
        $this->transfers->pack($transfer, [
            'items' => [$item->id => ['quantity_picked' => '12']],
        ], $this->warehouseStaff);

        // 3. Kirim 12 unit
        $this->transfers->ship($transfer, ['carrier' => 'Kurir Express'], $this->warehouseStaff);

        // 4. Penerimaan di cabang dengan selisih & barang rusak:
        // Dari 12 dikirim: 7 diterima baik, 2 rusak, 1 selisih, 2 masih in-transit
        $this->transfers->receive($transfer, [
            'idempotency_key' => 'partial-discrepancy-receive',
            'items' => [
                $item->id => [
                    'quantity_received' => '7',
                    'quantity_damaged' => '2',
                    'quantity_discrepancy' => '1',
                    'notes' => '2 dus pecah, 1 dus hilang saat bongkar muat',
                ],
            ],
        ], $this->storeHead);

        $this->assertSame(StockTransferStatus::PARTIALLY_RECEIVED, $transfer->fresh()->status);

        // Buka halaman show
        $response = $this->actingAs($this->storeHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $response->assertOk();

        // Verifikasi Banner Perhatian Operasional
        $response->assertSee('Perhatian Operasional:');
        $response->assertSee('Ada barang rusak');
        $response->assertSee('Ada selisih pengiriman');
        $response->assertSee('Masih ada barang dalam perjalanan');
        $response->assertSee('Jumlah diambil kurang dari yang disetujui');

        // Verifikasi Hero Action Card mengarahkan ke penyelesaian selisih
        $response->assertSee('Selesaikan Selisih Penerimaan');
        $response->assertSee('Ada selisih yang belum dipertanggungjawabkan');
        $response->assertSee('Selesaikan Selisih');
        $response->assertSee('#penyelesaian-selisih');

        // Verifikasi baris tabel 4 kolom:
        // Kolom 2 Rencana: Disetujui 15, Diminta 15
        $response->assertSee('15');
        $response->assertSee('disetujui');
        $response->assertSee('Diminta: 15');
        // Kolom 3 Pengiriman: Dikirim 12, Diambil 12, Kurang 3
        $response->assertSee('Dikirim:');
        $response->assertSee('12');
        $response->assertSee('Diambil: 12');
        $response->assertSee('Kurang: 3');
        // Kolom 4 Penerimaan: Dikirim 12 -> Diterima 7, 2 Rusak, 1 Selisih, 2 In-Transit
        $response->assertSee('Dikirim: 12');
        $response->assertSee('Diterima: 7');
        $response->assertSee('2 Rusak');
        $response->assertSee('1 Selisih');
        $response->assertSee('2 In-Transit');

        // Sempurna TIDAK boleh muncul karena ada selisih dan rusak
        $response->assertDontSee('Sempurna');
    }

    /**
     * Uji 4: Skenario Penerimaan Sempurna (Perfect Receiving)
     * Memastikan badge "Sempurna" muncul di tabel dan tidak ada banner peringatan operasional.
     */
    public function test_perfect_receiving_shows_perfect_badge_and_no_operational_warning(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '20', $this->warehouseHead);

        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '8');
        $item = $transfer->items->firstOrFail();

        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '8']]], $this->warehouseStaff);
        $this->transfers->ship($transfer, ['carrier' => 'Internal'], $this->warehouseStaff);

        // Penerimaan penuh sempurna
        $this->transfers->receive($transfer, [
            'idempotency_key' => 'perfect-receive-key',
            'items' => [$item->id => ['quantity_received' => '8', 'quantity_damaged' => '0', 'quantity_discrepancy' => '0']],
        ], $this->storeHead);

        $response = $this->actingAs($this->storeHead)->get(route('warehouse.stock-transfers.show', $transfer));
        $response->assertOk();

        // Tidak ada banner peringatan operasional
        $response->assertDontSee('Perhatian Operasional:');
        $response->assertDontSee('Ada barang rusak');
        $response->assertDontSee('Ada selisih pengiriman');

        // Badge Sempurna muncul di baris tabel
        $response->assertSee('Sempurna');
        $response->assertSee('Dikirim: 8');
        $response->assertSee('Diterima: 8');
    }

    /**
     * Uji 5: Matriks Tombol Aksi Multi-Role (Kepala Gudang vs Staf Gudang vs Kepala Toko vs Kasir/Unauthorized)
     * Memastikan hak otorisasi @can mengontrol visibilitas tombol aksi dan fallback badge/state dengan tepat.
     */
    public function test_action_buttons_multi_role_matrix(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '30', $this->warehouseHead);

        // Tugaskan viewerOnlyUser ke warehouse & branch
        $this->viewerOnlyUser->workLocations()->syncWithoutDetaching([
            $warehouse->work_location_id => ['is_default' => true, 'is_active' => true],
            $branch->work_location_id => ['is_default' => false, 'is_active' => true],
        ]);

        // TAHAP 1: DRAFT
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
            'action' => 'draft',
        ], $this->warehouseStaff);

        // Staf Gudang (punya hak update/create): tombol Ajukan untuk Persetujuan aktif
        $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Ajukan untuk Persetujuan');

        // User tanpa permission stock_transfers.view (kasir atau unauthorized) ditolak dengan 403 Forbidden
        $this->actingAs($this->cashier)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertForbidden();

        // User yang punya view tapi bukan creator/updater melihat placeholder "Menunggu Pembuat Transfer" dan tidak ada form submit
        $this->actingAs($this->viewerOnlyUser)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Menunggu Pembuat Transfer')
            ->assertDontSee(route('warehouse.stock-transfers.submit', $transfer), false);

        // TAHAP 2: PENDING_APPROVAL
        $this->transfers->submit($transfer, $this->warehouseStaff);

        // Kepala Gudang: punya tombol "Setujui Transfer"
        $this->actingAs($this->warehouseHead)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Setujui Transfer')
            ->assertDontSee('Menunggu Kepala Gudang');

        // Staf Gudang: TIDAK punya tombol "Setujui Transfer", melihat placeholder status
        $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Menunggu Kepala Gudang');

        // Kepala Toko: TIDAK bisa menyetujui transfer dari gudang sumber
        $this->actingAs($this->storeHead)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Menunggu Kepala Gudang');

        // TAHAP 3: APPROVED
        $this->transfers->approve($transfer, $this->warehouseHead);

        // Staf Gudang: melihat tombol "Buka Halaman Packing"
        $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Buka Halaman Packing')
            ->assertSee(route('warehouse.stock-transfers.packing', $transfer), false);

        // Kepala Toko: tidak berwenang packing di gudang sumber, melihat "Menunggu Petugas Gudang"
        $this->actingAs($this->storeHead)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Menunggu Petugas Gudang');

        // TAHAP 4: SHIPPED
        $item = $transfer->items->firstOrFail();
        $this->transfers->pack($transfer, ['items' => [$item->id => ['quantity_picked' => '5']]], $this->warehouseStaff);
        $this->transfers->ship($transfer, ['carrier' => 'Mobil Box'], $this->warehouseStaff);

        // Kepala Toko (di lokasi tujuan): melihat tombol "Proses Terima Barang"
        $this->actingAs($this->storeHead)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Proses Terima Barang')
            ->assertSee(route('retail.stock-transfers.receive-form', $transfer), false);

        // Staf Gudang Asal: bukan staf cabang tujuan, melihat "Menunggu Petugas Cabang Tujuan"
        $this->actingAs($this->warehouseStaff)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Menunggu Petugas Cabang Tujuan');

        // TAHAP 5: FULLY_RECEIVED
        $this->transfers->receive($transfer, [
            'idempotency_key' => 'matrix-full-receive',
            'items' => [$item->id => ['quantity_received' => '5', 'quantity_damaged' => '0', 'quantity_discrepancy' => '0']],
        ], $this->storeHead);

        // Kepala Toko: melihat tombol "Selesaikan Transfer"
        $this->actingAs($this->storeHead)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Selesaikan Transfer');

        // Viewer only user (tanpa complete/approve permission): melihat "Menunggu Penanggung Jawab Tujuan"
        $this->actingAs($this->viewerOnlyUser)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Menunggu Penanggung Jawab Tujuan');

        // TAHAP 6: COMPLETED
        $this->transfers->complete($transfer, $this->storeHead);

        // Kepala Toko & Kepala Gudang: melihat "Transfer Selesai" & "Cetak Surat Jalan"
        $this->actingAs($this->storeHead)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Transfer Selesai')
            ->assertSee('Cetak Surat Jalan')
            ->assertSee(route('warehouse.stock-transfers.print', $transfer), false);

        $this->actingAs($this->warehouseHead)
            ->get(route('warehouse.stock-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Transfer Selesai')
            ->assertSee('Cetak Surat Jalan');
    }

    /** @return array{Warehouse, Branch, Product, WarehouseLocation, WarehouseLocation} */
    private function fixture(): array
    {
        [$warehouse, $branch] = $this->warehouseAndBranch('A');
        $unit = Unit::factory()->create(['code' => 'PCS']);
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
