<?php

namespace Tests\Feature\System;

use App\Models\B2bOrder;
use App\Models\DailyReport;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\User;
use Database\Seeders\DemoFullApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoFullApplicationSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $roleAccounts = [
        'super_admin' => 'super_admin@gudangtoko.test',
        'owner_viewer' => 'owner_viewer@gudangtoko.test',
        'owner_approver' => 'owner_approver@gudangtoko.test',
        'admin_user' => 'admin_user@gudangtoko.test',
        'admin_config' => 'admin_config@gudangtoko.test',
        'kepala_gudang' => 'kepala_gudang@gudangtoko.test',
        'staff_gudang' => 'staff_gudang@gudangtoko.test',
        'picker_packer' => 'picker_packer@gudangtoko.test',
        'purchasing' => 'purchasing@gudangtoko.test',
        'kepala_toko' => 'kepala_toko@gudangtoko.test',
        'kasir' => 'kasir@gudangtoko.test',
        'supervisor_shift' => 'supervisor_shift@gudangtoko.test',
        'langganan_owner' => 'langganan_owner@gudangtoko.test',
        'langganan_staff' => 'langganan_staff@gudangtoko.test',
    ];

    #[Test]
    public function demo_full_application_seeder_creates_role_accounts_and_cross_module_data(): void
    {
        $this->seed(DemoFullApplicationSeeder::class);

        foreach ($this->roleAccounts as $role => $email) {
            $user = User::query()->where('email', $email)->first();

            $this->assertInstanceOf(User::class, $user);
            $this->assertTrue(Hash::check('password', (string) $user->password));
            $this->assertTrue($user->hasRole($role));
        }

        $this->assertGreaterThanOrEqual(3, Product::query()->where('sku', 'like', 'DEMO-%')->count());
        $this->assertGreaterThanOrEqual(2, Stock::query()->count());
        $this->assertGreaterThanOrEqual(2, StockMutation::query()->where('reference_type', 'demo_seed')->count());

        $this->assertDatabaseHas('purchase_orders', ['number' => 'PO-DEMO-0001', 'status' => 'partially_received']);
        $this->assertDatabaseHas('goods_receipts', ['number' => 'RCV-DEMO-0001', 'status' => 'posted']);
        $this->assertDatabaseHas('restock_requests', ['number' => 'RST-DEMO-0001', 'status' => 'approved']);
        $this->assertDatabaseHas('stock_transfers', ['number' => 'TRF-DEMO-0001', 'status' => 'pending_approval']);
        $this->assertDatabaseHas('stock_transfer_items', ['quantity_reserved' => 0, 'quantity_picked' => 0]);

        $this->assertTrue(GoodsReceipt::query()->where('number', 'RCV-DEMO-0001')->exists());
        $this->assertTrue(PosSale::query()->where('number', 'POS-DEMO-0001')->exists());
        $this->assertTrue(B2bOrder::query()->where('number', 'B2B-DEMO-0001')->exists());
        $this->assertTrue(Invoice::query()->where('number', 'INV-DEMO-0001')->exists());
        $this->assertTrue(Receivable::query()->where('number', 'AR-DEMO-0001')->exists());
        $this->assertTrue(StockTransfer::query()->where('number', 'TRF-DEMO-0001')->exists());
        $this->assertTrue(DailyReport::query()->where('idempotency_key', 'demo-daily-report')->exists());
    }
}
