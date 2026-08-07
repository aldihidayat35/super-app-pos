<?php

namespace Tests\Feature\Admin;

use App\Models\B2bOrder;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Receivable;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PartyMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(Role::findOrCreate('admin_config'));
    }

    public function test_admin_can_open_supplier_and_customer_pages(): void
    {
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.suppliers.index'))->assertOk()->assertSee('Daftar Supplier');
        $this->actingAs($this->admin)->get(route('admin.suppliers.show', $supplier))->assertOk()->assertSee($supplier->name);
        $this->actingAs($this->admin)->get(route('admin.customers.index'))->assertOk()->assertSee('Pelanggan');
        $this->actingAs($this->admin)->get(route('admin.customers.show', $customer))->assertOk()->assertSee($customer->business_name);
        $this->actingAs($this->admin)->get(route('admin.customers.access.edit', $customer))->assertOk()->assertSee('Alamat Kirim');
        $this->actingAs($this->admin)->get(route('admin.customers.settings.edit', $customer))->assertOk()->assertSee('Status, Ring, dan Kredit');
    }

    public function test_customer_create_page_is_authorized(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.create'))
            ->assertOk()
            ->assertSee('Dokumen Usaha / Dokumen Pendukung')
            ->assertSee('Otomatis saat disimpan')
            ->assertSee('Cetak Formulir Pendaftaran');

        $unauthorized = User::factory()->create(['is_active' => true]);

        $this->actingAs($unauthorized)
            ->get(route('admin.customers.create'))
            ->assertForbidden();
    }

    public function test_customer_codes_are_generated_by_customer_type(): void
    {
        $general = $this->createCustomer(['type' => 'general', 'business_name' => 'Customer Umum']);
        $retailCredit = $this->createCustomer(['type' => 'retail_credit', 'business_name' => 'Customer Retail Tempo']);
        $b2b = $this->createCustomer(['type' => 'b2b', 'business_name' => 'Customer B2B']);
        $secondB2b = $this->createCustomer(['type' => 'b2b', 'business_name' => 'Customer B2B Kedua']);
        $date = now()->format('Ymd');

        $this->assertSame("UM-{$date}-0001", $general->code);
        $this->assertSame("RP-T-{$date}-0001", $retailCredit->code);
        $this->assertSame("B2B-{$date}-0001", $b2b->code);
        $this->assertSame("B2B-{$date}-0002", $secondB2b->code);
        $this->assertSame(4, Customer::query()->distinct('code')->count('code'));
    }

    public function test_customer_can_be_created_without_documents(): void
    {
        $customer = $this->createCustomer(['type' => 'general', 'business_name' => 'Customer Tanpa Dokumen']);

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'business_name' => 'Customer Tanpa Dokumen']);
        $this->assertSame(0, $customer->documents()->count());

        $customerWithDefaultDocumentRows = $this->createCustomer([
            'type' => 'general',
            'business_name' => 'Customer Dengan Baris Dokumen Default',
            'documents' => [
                ['type' => 'nib'],
                ['type' => 'npwp'],
                ['type' => 'owner_id_card'],
            ],
        ]);

        $this->assertSame(0, $customerWithDefaultDocumentRows->documents()->count());
    }

    public function test_customer_can_be_created_with_multiple_documents(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerPayload([
                'type' => 'b2b',
                'business_name' => 'Customer Dokumen Lengkap',
                'documents' => [
                    [
                        'type' => 'nib',
                        'name' => 'NIB Customer',
                        'document_number' => 'NIB-123',
                        'issued_at' => now()->subYear()->toDateString(),
                        'expires_at' => now()->addYear()->toDateString(),
                        'file' => UploadedFile::fake()->create('nib.pdf', 100, 'application/pdf'),
                        'notes' => 'Dokumen NIB awal.',
                    ],
                    [
                        'type' => 'npwp',
                        'name' => 'NPWP Customer',
                        'document_number' => 'NPWP-456',
                        'file' => UploadedFile::fake()->image('npwp.jpg'),
                    ],
                ],
            ]))
            ->assertRedirect();

        $customer = Customer::query()->where('business_name', 'Customer Dokumen Lengkap')->firstOrFail();
        $this->assertSame(2, $customer->documents()->count());
        $this->assertDatabaseHas('customer_documents', ['customer_id' => $customer->id, 'type' => 'nib', 'document_number' => 'NIB-123', 'notes' => 'Dokumen NIB awal.']);
        $this->assertDatabaseHas('customer_documents', ['customer_id' => $customer->id, 'type' => 'npwp', 'document_number' => 'NPWP-456']);

        foreach ($customer->documents as $document) {
            Storage::disk('public')->assertExists($document->path);
        }
    }

    public function test_customer_document_upload_rejects_invalid_and_oversized_files(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerPayload([
                'documents' => [
                    ['type' => 'nib', 'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')],
                ],
            ]))
            ->assertSessionHasErrors('documents.0.file');

        $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerPayload([
                'business_name' => 'Customer File Besar',
                'documents' => [
                    ['type' => 'npwp', 'file' => UploadedFile::fake()->create('npwp.pdf', 5000, 'application/pdf')],
                ],
            ]))
            ->assertSessionHasErrors('documents.0.file');
    }

    public function test_registration_form_print_uses_system_settings_and_does_not_create_customer(): void
    {
        SystemSetting::query()->create(['key' => 'company_name', 'value' => 'PT Formulir Test', 'group' => 'general']);
        SystemSetting::query()->create(['key' => 'company_address', 'value' => 'Jl. Pengujian No. 1', 'group' => 'general']);
        SystemSetting::query()->create(['key' => 'company_phone', 'value' => '021-123456', 'group' => 'general']);
        SystemSetting::query()->create(['key' => 'company_email', 'value' => 'admin@example.test', 'group' => 'general']);

        $this->assertSame(0, Customer::query()->count());

        $this->actingAs($this->admin)
            ->get(route('admin.customers.registration-form'))
            ->assertOk()
            ->assertSee('PT Formulir Test')
            ->assertSee('Jl. Pengujian No. 1')
            ->assertSee('Formulir Pendaftaran Pelanggan')
            ->assertSee('Checklist Dokumen Usaha');

        $this->assertSame(0, Customer::query()->count());

        $unauthorized = User::factory()->create(['is_active' => true]);
        $this->actingAs($unauthorized)
            ->get(route('admin.customers.registration-form'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_customer_dashboard_data_and_prefilled_actions(): void
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(Role::findOrCreate('super_admin'));
        $customer = Customer::factory()->create([
            'business_name' => 'PT Pelanggan Dashboard',
            'credit_limit' => 10000000,
            'receivable_balance' => 2500000,
        ]);
        $order = B2bOrder::query()->create([
            'number' => 'B2B-DASH-001',
            'customer_id' => $customer->id,
            'requested_by' => $superAdmin->id,
            'status' => 'approved_credit',
            'grand_total_amount' => 4000000,
            'submitted_at' => now(),
        ]);
        Invoice::query()->create([
            'number' => 'INV-DASH-001',
            'b2b_order_id' => $order->id,
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'total_amount' => 4000000,
            'outstanding_amount' => 2500000,
        ]);
        Payment::query()->create([
            'number' => 'PAY-DASH-001',
            'customer_id' => $customer->id,
            'method' => 'bank_transfer',
            'status' => 'verified',
            'amount' => 1500000,
            'payment_date' => now()->toDateString(),
        ]);
        Receivable::query()->create([
            'number' => 'AR-DASH-001',
            'customer_id' => $customer->id,
            'source_type' => 'invoice',
            'source_id' => 1,
            'source_no' => 'INV-DASH-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'principal_amount' => 4000000,
            'paid_amount' => 1500000,
            'outstanding_amount' => 2500000,
            'status' => 'open',
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.customers.show', $customer));

        $response->assertOk()
            ->assertSee('PT Pelanggan Dashboard')
            ->assertSee('Ringkasan Pelanggan')
            ->assertSee('Pesanan B2B Terbaru')
            ->assertSee('Invoice & Piutang', false)
            ->assertSee('Pembayaran Terbaru')
            ->assertSee('B2B-DASH-001')
            ->assertSee('INV-DASH-001')
            ->assertSee('PAY-DASH-001')
            ->assertSee('AR-DASH-001')
            ->assertSee(route('warehouse.b2b-orders.index', ['customer_id' => $customer->id]))
            ->assertSee(route('shipments.index', ['customer_id' => $customer->id]))
            ->assertSee(route('shipments.create', ['order_id' => $order->id, 'customer_id' => $customer->id]))
            ->assertSee(route('receivables.payments.create', ['customer_id' => $customer->id]))
            ->assertSee(route('pricing.special-prices.index', ['customer_id' => $customer->id]))
            ->assertDontSee('fase berikutnya');
    }

    public function test_customer_dashboard_related_lists_respect_customer_filter(): void
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(Role::findOrCreate('super_admin'));
        $selectedCustomer = Customer::factory()->create(['business_name' => 'Pelanggan Terpilih']);
        $otherCustomer = Customer::factory()->create(['business_name' => 'Pelanggan Lain']);

        $selectedOrder = B2bOrder::query()->create([
            'number' => 'B2B-FILTER-SELECTED',
            'customer_id' => $selectedCustomer->id,
            'requested_by' => $superAdmin->id,
            'status' => 'approved_credit',
        ]);
        $otherOrder = B2bOrder::query()->create([
            'number' => 'B2B-FILTER-OTHER',
            'customer_id' => $otherCustomer->id,
            'requested_by' => $superAdmin->id,
            'status' => 'approved_credit',
        ]);
        Shipment::query()->create([
            'number' => 'SHP-FILTER-SELECTED',
            'b2b_order_id' => $selectedOrder->id,
            'customer_id' => $selectedCustomer->id,
            'status' => 'waiting',
        ]);
        Shipment::query()->create([
            'number' => 'SHP-FILTER-OTHER',
            'b2b_order_id' => $otherOrder->id,
            'customer_id' => $otherCustomer->id,
            'status' => 'waiting',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('warehouse.b2b-orders.index', ['customer_id' => $selectedCustomer->id]))
            ->assertOk()
            ->assertSee('B2B-FILTER-SELECTED')
            ->assertDontSee('B2B-FILTER-OTHER');

        $this->actingAs($superAdmin)
            ->get(route('shipments.index', ['customer_id' => $selectedCustomer->id]))
            ->assertOk()
            ->assertSee('SHP-FILTER-SELECTED')
            ->assertDontSee('SHP-FILTER-OTHER');

        $this->actingAs($superAdmin)
            ->get(route('pricing.special-prices.index', ['customer_id' => $selectedCustomer->id]))
            ->assertOk()
            ->assertSee('value="'.$selectedCustomer->id.'" selected', false);
    }

    public function test_supplier_and_customer_import_preview_reports_invalid_rows(): void
    {
        $supplierFile = UploadedFile::fake()->createWithContent('suppliers.csv', "code,name,email\nSUP-1,Supplier Salah,bukan-email\n");
        $customerFile = UploadedFile::fake()->createWithContent('customers.csv', "type,code,business_name,email\nb2b,CUS-1,Customer Salah,bukan-email\n");

        $this->actingAs($this->admin)
            ->post(route('admin.parties.import.preview', 'suppliers'), ['file' => $supplierFile])
            ->assertRedirect(route('admin.parties.import.index', 'suppliers'))
            ->assertSessionHas('suppliers_import_preview');
        $this->assertNotEmpty(session('suppliers_import_preview')['errors']);

        $this->actingAs($this->admin)
            ->post(route('admin.parties.import.preview', 'customers'), ['file' => $customerFile])
            ->assertRedirect(route('admin.parties.import.index', 'customers'))
            ->assertSessionHas('customers_import_preview');
        $this->assertNotEmpty(session('customers_import_preview')['errors']);
    }

    public function test_b2b_user_only_sees_own_customer(): void
    {
        $owned = Customer::factory()->create(['business_name' => 'Customer Milik Saya']);
        $blocked = Customer::factory()->create(['business_name' => 'Customer Orang Lain']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate('langganan_owner'));
        $owned->users()->attach($user->id, ['role' => 'langganan_owner', 'is_active' => true]);

        $this->actingAs($user)->get(route('admin.customers.index'))->assertRedirect(route('langganan.dashboard'));
        $this->actingAs($user)->get(route('admin.customers.show', $owned))->assertRedirect(route('langganan.dashboard'));
        $this->actingAs($user)->get(route('admin.customers.show', $blocked))->assertRedirect(route('langganan.dashboard'));
    }

    public function test_customer_access_keeps_single_primary_address_and_creates_b2b_user(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.customers.access.update', $customer), [
            'primary_address_index' => 1,
            'addresses' => [
                ['label' => 'Gudang', 'recipient_name' => 'A', 'phone_number' => '081111111111', 'address' => 'Alamat A'],
                ['label' => 'Toko', 'recipient_name' => 'B', 'phone_number' => '082222222222', 'address' => 'Alamat B'],
            ],
            'users' => [
                ['name' => 'B2B Baru', 'username' => 'b2bbaru', 'email' => 'b2bbaru@gudangtoko.test', 'role' => 'langganan_staff', 'is_active' => 1],
            ],
        ])->assertRedirect(route('admin.customers.access.edit', $customer));

        $this->assertSame(1, $customer->addresses()->where('is_primary', true)->count());
        $this->assertDatabaseHas('users', ['email' => 'b2bbaru@gudangtoko.test']);
        $this->assertDatabaseHas('customer_users', ['customer_id' => $customer->id, 'role' => 'langganan_staff', 'is_active' => true]);
    }

    public function test_customer_settings_uploads_document_and_price_override(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();
        $category = ProductCategory::factory()->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'base_unit_id' => $unit->id]);

        $this->actingAs($this->admin)->put(route('admin.customers.settings.update', $customer), [
            'verification_status' => 'active',
            'account_status' => 'frozen',
            'price_category' => 'special',
            'minimum_order' => 100000,
            'payment_term_days' => 21,
            'credit_limit' => 9000000,
            'status_reason' => 'Dokumen perlu review ulang.',
            'document_type' => 'npwp',
            'document_name' => 'NPWP Customer',
            'document' => UploadedFile::fake()->create('npwp.pdf', 100, 'application/pdf'),
            'price_overrides' => [
                ['product_id' => $product->id, 'price' => 15000, 'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(), 'notes' => 'Harga kontrak'],
            ],
        ])->assertRedirect(route('admin.customers.settings.edit', $customer));

        $this->assertDatabaseHas('customer_documents', ['customer_id' => $customer->id, 'type' => 'npwp', 'name' => 'NPWP Customer']);
        $this->assertDatabaseHas('customer_price_overrides', ['customer_id' => $customer->id, 'product_id' => $product->id, 'price' => 15000]);
        $this->assertDatabaseHas('credit_limits', ['customer_id' => $customer->id, 'credit_limit' => 9000000]);
    }

    public function test_purchasing_can_manage_suppliers_but_not_customers(): void
    {
        $purchasing = User::factory()->create(['is_active' => true]);
        $purchasing->assignRole(Role::findOrCreate('purchasing'));

        $this->actingAs($purchasing)->get(route('admin.suppliers.index'))->assertOk();
        $this->actingAs($purchasing)->get(route('admin.customers.index'))->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function createCustomer(array $overrides = []): Customer
    {
        $this->actingAs($this->admin)
            ->post(route('admin.customers.store'), $this->customerPayload($overrides))
            ->assertRedirect();

        return Customer::query()->where('business_name', $overrides['business_name'] ?? 'Customer Baru')->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function customerPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'type' => 'general',
            'business_name' => 'Customer Baru',
            'owner_name' => 'Pemilik Customer',
            'pic_name' => 'PIC Customer',
            'whatsapp_number' => '081234567890',
            'email' => 'customer-baru@example.test',
            'business_address' => 'Alamat Customer',
            'city' => 'Jakarta',
            'price_category' => 'retail',
            'minimum_order' => 0,
            'payment_term_days' => 0,
            'credit_limit' => 0,
            'verification_status' => 'pending_verification',
            'account_status' => 'pending_verification',
            'notes' => null,
            'is_active' => 1,
        ], $overrides);
    }
}
