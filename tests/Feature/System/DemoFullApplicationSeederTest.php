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

    /** @var array<string, array{email: string, roles: list<string>}> */
    private array $roleAccounts = [
        'owner' => ['email' => 'owner@gudangtoko.test', 'roles' => ['owner_approver']],
        'super_admin' => ['email' => 'superadmin@gudangtoko.test', 'roles' => ['super_admin']],
        'manajemen_gudang' => ['email' => 'manajemen-gudang@gudangtoko.test', 'roles' => ['kepala_gudang', 'purchasing']],
        'staff_gudang' => ['email' => 'staff-gudang@gudangtoko.test', 'roles' => ['staff_gudang']],
        'toko_internal' => ['email' => 'toko@gudangtoko.test', 'roles' => ['kepala_toko']],
        'kasir_kepala_toko' => ['email' => 'kasir@gudangtoko.test', 'roles' => ['kasir', 'kepala_toko']],
        'langganan_b2b' => ['email' => 'langganan-b2b@gudangtoko.test', 'roles' => ['langganan_owner']],
        'akun_pelanggan' => ['email' => 'pelanggan@gudangtoko.test', 'roles' => ['langganan_staff']],
    ];

    #[Test]
    public function demo_full_application_seeder_creates_role_accounts_and_cross_module_data(): void
    {
        $this->seed(DemoFullApplicationSeeder::class);

        $this->assertSame(8, User::query()->count());

        foreach ($this->roleAccounts as $account) {
            $user = User::query()->where('email', $account['email'])->first();

            $this->assertInstanceOf(User::class, $user);
            $this->assertTrue(Hash::check('password', (string) $user->password));

            foreach ($account['roles'] as $role) {
                $this->assertTrue($user->hasRole($role));
            }
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
