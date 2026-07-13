<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
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

        $this->actingAs($user)->get(route('admin.customers.index'))->assertOk()->assertSee('Customer Milik Saya')->assertDontSee('Customer Orang Lain');
        $this->actingAs($user)->get(route('admin.customers.show', $owned))->assertOk();
        $this->actingAs($user)->get(route('admin.customers.show', $blocked))->assertForbidden();
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
}
