<?php

namespace Database\Seeders;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\SalePayment;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Seeder deterministik khusus Toko Kedaung (work_location_id = 3).
 *
 * Skenario:
 *   - Pastikan kasir@kedaung.test ada, aktif, punya role `kasir` dan akses ke WL=3.
 *   - Buka 1 cash shift "open" dengan modal awal di kasir kedaung.
 *   - Tambahkan 3 POS sale (cash + qris + active draft) agar dashboard & laporan retail punya data.
 *   - Pastikan stok retail di WL=3 minimal tersedia sebelum POS sale dilampirkan (stok cabang
 *     dikirim dari gudang via transfer, direkam sebagai stock_mutation dengan direction `in`).
 *   - Semua nomor surat & idempotency key pakai prefix `RT-KED-` agar mudah difilter.
 *
 * Seeder ini idempotent (aman dipanggil ulang): gunakan `updateOrCreate` berdasarkan nomor
 * surat atau key yang unik.
 */
class RetailKedaungSeeder extends Seeder
{
    public const KEDAUNG_WORK_LOCATION_ID = 3;

    public const KEDAUNG_BRANCH_CODE = 'TKO-kedaung2';

    public const CASHIER_EMAIL = 'kasir.kedaung@gudangtoko.test';

    public const KEPALA_TOKO_EMAIL = 'kepalacabang.kedaung@gudangtoko.test';

    private const PASSWORD = 'password';

    public function run(): void
    {
        $workLocation = WorkLocation::query()->find(self::KEDAUNG_WORK_LOCATION_ID);
        if ($workLocation === null) {
            $this->command?->warn(sprintf(
                '[RetailKedaungSeeder] WorkLocation id=%d tidak ditemukan, lewati.',
                self::KEDAUNG_WORK_LOCATION_ID,
            ));

            return;
        }

        // Branch code TKO-kedaung2 -> cari branch dengan code tsb.
        $branch = DB::table('branches')->where('code', self::KEDAUNG_BRANCH_CODE)->first();
        if ($branch === null) {
            // Fallback: gunakan branch id=2 (Toko Kedaung Aur) yang sudah ada.
            $branch = DB::table('branches')->where('id', 2)->first();
        }
        if ($branch === null) {
            $this->command?->warn('[RetailKedaungSeeder] Branch retail tidak ditemukan, lewati.');

            return;
        }

        DB::transaction(function () use ($workLocation, $branch): void {
            $cashier = $this->ensureUser(
                email: self::CASHIER_EMAIL,
                name: 'Kasir Kedaung',
                username: 'kasir.kedaung',
                roles: ['kasir'],
                workLocationId: $workLocation->id,
                employeeNo: 'EMP-KED-001',
                position: 'Kasir Toko Kedaung',
            );

            $kepalaToko = $this->ensureUser(
                email: self::KEPALA_TOKO_EMAIL,
                name: 'Kepala Toko Kedaung',
                username: 'kepalacabang.kedaung',
                roles: ['kasir', 'kepala_toko'],
                workLocationId: $workLocation->id,
                employeeNo: 'EMP-KED-002',
                position: 'Kepala Toko Kedaung',
            );

            // Produk retail: pakai produk global yang sudah ada. Pilih 3 SKU fashion.
            $products = Product::query()
                ->whereIn('sku', ['DEMO-TSHIRT-001', 'DEMO-BAG-001', 'DEMO-CAP-001'])
                ->orderBy('id')
                ->get();

            if ($products->isEmpty()) {
                $this->command?->warn('[RetailKedaungSeeder] Produk DEMO tidak ditemukan, lewati seeder.');

                return;
            }

            $unitPcs = Unit::query()->where('name', 'Pieces')->first() ?? Unit::query()->first();

            // Pastikan ada stok di Toko Kedaung (WL=3) untuk tiap produk yang akan dijual.
            // Sumber stok: warehouse_location id=13 (BIN-MAIN Gudang Jambu Air 2). Stok cabang
            // tidak punya warehouse_location sendiri, jadi gunakan NULL dan location_scope_key
            // khusus retail.
            foreach ($products as $product) {
                $stock = Stock::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'work_location_id' => $workLocation->id,
                        'warehouse_location_id' => null,
                    ],
                    [
                        'location_scope_key' => 'retail:'.(string) $workLocation->id,
                        'quantity_on_hand' => 100,
                        'quantity_reserved' => 0,
                        'quantity_damaged' => 0,
                        'cost_value' => bcmul((string) $product->cost_price, '100', 2),
                    ],
                );

                $existingMutation = StockMutation::query()
                    ->where('reference_no', 'RT-KED-OPENING-'.$product->sku)
                    ->exists();

                if (! $existingMutation) {
                    StockMutation::query()->create([
                        'product_id' => $product->id,
                        'stock_id' => $stock->id,
                        'work_location_id' => $workLocation->id,
                        'warehouse_location_id' => null,
                        'mutation_type' => 'receive',
                        'direction' => 'in',
                        'quantity_on_hand_before' => 0,
                        'quantity_on_hand_change' => 100,
                        'quantity_on_hand_after' => 100,
                        'quantity_reserved_before' => 0,
                        'quantity_reserved_change' => 0,
                        'quantity_reserved_after' => 0,
                        'quantity_damaged_before' => 0,
                        'quantity_damaged_change' => 0,
                        'quantity_damaged_after' => 0,
                        'unit_id' => $unitPcs?->id,
                        'reference_type' => 'retail_opening_balance',
                        'reference_no' => 'RT-KED-OPENING-'.$product->sku,
                        'source_work_location_id' => null,
                        'destination_work_location_id' => $workLocation->id,
                        'actor_user_id' => $kepalaToko->id,
                        'reason' => 'Saldo pembuka retail Toko Kedaung.',
                        'idempotency_key' => 'rt-ked-opening-'.$product->sku,
                        'metadata' => json_encode(['source' => 'RetailKedaungSeeder']),
                        'occurred_at' => now()->subDay(),
                    ]);
                }
            }

            // Customer walk-in: pakai pelanggan B2B dummy yang sudah ada (id=1) atau buat baru
            // tipe "walk_in" agar tidak bergantung B2B. Untuk sederhana, gunakan id=1.
            $customerId = Customer::query()->value('id');

            // 1) Cash shift OPEN untuk kasir kedaung.
            $cashShift = CashShift::query()->updateOrCreate(
                ['number' => 'SHIFT-KED-0001'],
                [
                    'branch_id' => $branch->id,
                    'work_location_id' => $workLocation->id,
                    'cashier_user_id' => $cashier->id,
                    'opened_by' => $cashier->id,
                    'status' => 'open',
                    'opening_cash_amount' => 300000,
                    'expected_cash_amount' => 300000,
                    'terminal_code' => 'POS-KED-01',
                    'cash_sales_amount' => 0,
                    'non_cash_sales_amount' => 0,
                    'refund_amount' => 0,
                    'expense_amount' => 0,
                    'receivable_amount' => 0,
                    'difference_amount' => 0,
                    'discrepancy_threshold_amount' => 50000,
                    'opened_at' => now()->subHours(2),
                    'notes' => 'Shift terbuka Retail Kedaung (demo).',
                ],
            );

            // 2) POS Sale #1: TUNAI, completed (1 kaos).
            $this->seedPosSale(
                number: 'POS-KED-0001',
                branchId: $branch->id,
                workLocationId: $workLocation->id,
                cashShiftId: $cashShift->id,
                cashierId: $cashier->id,
                customerId: $customerId,
                status: 'completed',
                product: $products->first(),
                unitId: $unitPcs?->id,
                qty: 1,
                unitPrice: 59000,
                paidAmount: 60000,
                paymentMethod: 'cash',
                paymentReference: 'CASH-KED-0001',
                notes: 'Penjualan tunai demo Toko Kedaung.',
                idempotencyKey: 'rt-ked-pos-sale-0001',
                completedAt: now()->subHours(1),
                shiftIdempotencyKey: 'rt-ked-shift-sale-0001',
            );

            // 3) POS Sale #2: QRIS, completed (1 tas).
            $this->seedPosSale(
                number: 'POS-KED-0002',
                branchId: $branch->id,
                workLocationId: $workLocation->id,
                cashShiftId: $cashShift->id,
                cashierId: $cashier->id,
                customerId: $customerId,
                status: 'completed',
                product: $products->get(1) ?? $products->first(),
                unitId: $unitPcs?->id,
                qty: 1,
                unitPrice: 119000,
                paidAmount: 119000,
                paymentMethod: 'qris',
                paymentReference: 'QRIS-KED-0002',
                notes: 'Penjualan QRIS demo Toko Kedaung.',
                idempotencyKey: 'rt-ked-pos-sale-0002',
                completedAt: now()->subMinutes(45),
                shiftIdempotencyKey: 'rt-ked-shift-sale-0002',
            );

            // 4) POS Sale #3: BANK_TRANSFER, active draft (belum dibayar).
            $this->seedPosSale(
                number: 'POS-KED-0003',
                branchId: $branch->id,
                workLocationId: $workLocation->id,
                cashShiftId: $cashShift->id,
                cashierId: $cashier->id,
                customerId: $customerId,
                status: 'active',
                product: $products->get(2) ?? $products->first(),
                unitId: $unitPcs?->id,
                qty: 1,
                unitPrice: 79000,
                paidAmount: 0,
                paymentMethod: 'bank_transfer',
                paymentReference: 'TRF-KED-0003',
                notes: 'Transaksi aktif menunggu pembayaran transfer.',
                idempotencyKey: 'rt-ked-pos-sale-0003',
                completedAt: null,
                shiftIdempotencyKey: null,
            );
        });
    }

    /**
     * Buat user + employee + role + work_location pivot. Idempotent.
     *
     * @param  array<int, string>  $roles
     */
    private function ensureUser(
        string $email,
        string $name,
        string $username,
        array $roles,
        int $workLocationId,
        string $employeeNo,
        string $position,
    ): User {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => $username,
                'phone_number' => '628'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'password' => self::PASSWORD,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // Sinkronkan role (hapus role lama yang tidak ada di daftar, pasang ulang yang diminta).
        $roleModels = array_map(static fn (string $roleName) => Role::findOrCreate($roleName), $roles);
        $user->syncRoles($roleModels);

        // Attach work location.
        $user->workLocations()->syncWithoutDetaching([
            $workLocationId => ['is_default' => true, 'is_active' => true],
        ]);

        Employee::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_no' => $employeeNo,
                'work_location_id' => $workLocationId,
                'name' => $name,
                'position' => $position,
                'whatsapp_number' => $user->phone_number,
                'joined_at' => now()->subMonth()->toDateString(),
                'status' => 'active',
                'is_active' => true,
            ],
        );

        return $user;
    }

    private function seedPosSale(
        string $number,
        int $branchId,
        int $workLocationId,
        int $cashShiftId,
        int $cashierId,
        ?int $customerId,
        string $status,
        Product $product,
        ?int $unitId,
        int $qty,
        int $unitPrice,
        int $paidAmount,
        string $paymentMethod,
        string $paymentReference,
        string $notes,
        string $idempotencyKey,
        ?Carbon $completedAt,
        ?string $shiftIdempotencyKey,
    ): void {
        $lineTotal = bcmul((string) $unitPrice, (string) $qty, 2);
        $grandTotal = (string) $lineTotal;
        $changeAmount = $paidAmount > 0 ? bcsub((string) $paidAmount, $grandTotal, 2) : '0';
        $hpp = bcmul((string) $product->cost_price, (string) $qty, 2);
        $margin = bcsub($grandTotal, $hpp, 2);

        $sale = PosSale::query()->updateOrCreate(
            ['number' => $number],
            [
                'branch_id' => $branchId,
                'work_location_id' => $workLocationId,
                'cash_shift_id' => $cashShiftId,
                'cashier_user_id' => $cashierId,
                'customer_id' => $customerId,
                'status' => $status,
                'subtotal_amount' => $lineTotal,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'grand_total_amount' => $grandTotal,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'total_margin_amount' => $margin,
                'idempotency_key' => $idempotencyKey,
                'completed_at' => $completedAt,
                'notes' => $notes,
            ],
        );

        PosSaleItem::query()->updateOrCreate(
            ['pos_sale_id' => $sale->id, 'product_id' => $product->id],
            [
                'unit_id' => $unitId,
                'warehouse_location_id' => null,
                'sku_snapshot' => $product->sku,
                'product_name_snapshot' => $product->name,
                'unit_name_snapshot' => 'Pieces',
                'conversion_factor_snapshot' => 1,
                'quantity' => $qty,
                'base_quantity' => $qty,
                'hpp_snapshot' => $product->cost_price,
                'minimum_price_snapshot' => $product->minimum_price,
                'selected_price' => $unitPrice,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => $lineTotal,
                'margin_amount' => $margin,
                'price_source' => 'retail_kedaung_seed',
                'price_snapshot' => json_encode(['source' => 'retail_kedaung_seed']),
                'returned_quantity' => 0,
            ],
        );

        if ($paidAmount > 0) {
            SalePayment::query()->updateOrCreate(
                ['pos_sale_id' => $sale->id, 'method' => $paymentMethod],
                [
                    'amount' => $paidAmount,
                    'reference_no' => $paymentReference,
                    'notes' => $notes,
                ],
            );
        }
    }
}
