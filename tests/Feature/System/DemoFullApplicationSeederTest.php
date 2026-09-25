<?php

namespace Tests\Feature\System;

use App\Models\Product;
use App\Models\Stock;
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
        'staf_toko' => ['email' => 'staf-toko@gudangtoko.test', 'roles' => ['staf_toko']],
        'kasir_kepala_toko' => ['email' => 'kasir@gudangtoko.test', 'roles' => ['kasir', 'kepala_toko']],
        'langganan_b2b' => ['email' => 'langganan-b2b@gudangtoko.test', 'roles' => ['langganan_owner']],
        'akun_pelanggan' => ['email' => 'pelanggan@gudangtoko.test', 'roles' => ['langganan_staff']],
    ];

    #[Test]
    public function demo_full_application_seeder_creates_role_accounts_without_business_data(): void
    {
        $this->seed(DemoFullApplicationSeeder::class);

        $this->assertSame(9, User::query()->count());

        foreach ($this->roleAccounts as $account) {
            $user = User::query()->where('email', $account['email'])->first();

            $this->assertInstanceOf(User::class, $user);
            $this->assertTrue(Hash::check('password', (string) $user->password));

            foreach ($account['roles'] as $role) {
                $this->assertTrue($user->hasRole($role));
            }
        }

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, Stock::query()->count());
        $this->assertDatabaseCount('warehouses', 0);
        $this->assertDatabaseCount('branches', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseCount('b2b_orders', 0);
        $this->assertDatabaseCount('purchase_orders', 0);
    }
}
