<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsNavigationCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'name' => 'Super Administrator',
            'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_general_settings_renders_navigation_cards_with_active_general(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.general.edit'));

        $response->assertOk();
        $response->assertSee('Identitas &amp; Preferensi', false);
        $response->assertSee('Pengaturan Printer', false);
        $response->assertSee('Nomor Dokumen', false);
        $response->assertSee('Akses Koreksi Stok', false);
        $response->assertSee('Koneksi WhatsApp', false);
        $response->assertSee('border-primary bg-light-primary bg-opacity-25', false);
    }

    public function test_printer_settings_renders_navigation_cards_with_active_printer(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.printer.edit'));

        $response->assertOk();
        $response->assertSee('Identitas &amp; Preferensi', false);
        $response->assertSee('Pengaturan Printer', false);
        $response->assertSee('border-success bg-light-success bg-opacity-25', false);
    }

    public function test_document_numbers_renders_navigation_cards_with_active_docnum(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.document-numbers.index'));

        $response->assertOk();
        $response->assertSee('Nomor Dokumen', false);
        $response->assertSee('border-info bg-light-info bg-opacity-25', false);
    }

    public function test_stock_adjustment_access_renders_navigation_cards_with_active_stock_adj(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.stock-adjustment-access.index'));

        $response->assertOk();
        $response->assertSee('Akses Koreksi Stok', false);
        $response->assertSee('border-warning bg-light-warning bg-opacity-25', false);
    }

    public function test_whatsapp_connection_renders_navigation_cards_with_active_whatsapp(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.whatsapp.index'));

        $response->assertOk();
        $response->assertSee('Koneksi WhatsApp', false);
    }

    public function test_general_settings_update_redirects_cleanly(): void
    {
        $response = $this->actingAs($this->admin)->put(route('admin.settings.general.update'), [
            'company_name' => 'PT Toko Makmur Sejahtera',
            'company_address' => 'Jl. Jendral Sudirman No. 45',
            'company_phone' => '021-1234567',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'upload_limit_mb' => 5,
            'default_minimum_margin_percent' => 15,
            'overpricing_tolerance_percent' => 25,
            'invoice_template' => 'default',
            'receipt_template' => 'default',
        ]);

        $response->assertRedirect(route('admin.settings.general.edit'));
        $this->assertEquals('PT Toko Makmur Sejahtera', SystemSetting::getCompanyName());
    }
}
