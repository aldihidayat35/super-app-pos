<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrinterSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function super_admin_can_view_printer_settings_page(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.printer.edit'))
            ->assertOk()
            ->assertSee('Pengaturan Printer')
            ->assertSee('Epson LX-310')
            ->assertSee('80 mm');
    }

    #[Test]
    public function super_admin_can_update_printer_settings(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->put(route('admin.settings.printer.update'), [
                'printer_thermal_width' => '80mm',
                'printer_dotmatrix_paper' => 'half_letter',
                'printer_auto_print_pos' => '1',
                'print_show_logo' => '1',
                'print_show_store_header' => '1',
                'print_show_cashier_name' => '1',
                'print_show_customer_name' => '1',
                'print_show_item_barcode' => '1',
                'print_show_payment_details' => '1',
                'print_show_signatures' => '1',
                'print_receipt_footer_note' => 'Terima kasih telah berbelanja di GudangToko.',
                'print_invoice_footer_note' => 'Pembayaran via transfer BCA 1234567890.',
            ])
            ->assertRedirect()
            ->assertSessionHas('notification');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'printer_thermal_width',
            'group' => 'printer',
        ]);

        $settings = SystemSetting::getPrinterSettings();
        $this->assertSame('80mm', $settings['printer_thermal_width']);
        $this->assertSame('half_letter', $settings['printer_dotmatrix_paper']);
        $this->assertTrue($settings['printer_auto_print_pos']);
        $this->assertSame('Terima kasih telah berbelanja di GudangToko.', $settings['print_receipt_footer_note']);
    }

    #[Test]
    public function validation_rejects_invalid_thermal_width(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->put(route('admin.settings.printer.update'), [
                'printer_thermal_width' => '310mm', // Invalid
                'printer_dotmatrix_paper' => 'half_letter',
            ])
            ->assertSessionHasErrors('printer_thermal_width');
    }

    #[Test]
    public function unauthorized_user_cannot_access_printer_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.settings.printer.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.settings.printer.update'), [
                'printer_thermal_width' => '80mm',
                'printer_dotmatrix_paper' => 'half_letter',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function super_admin_can_render_thermal_test_print(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.printer.test', 'thermal'))
            ->assertOk()
            ->assertSee('Uji Coba Cetak Thermal')
            ->assertSee('TOTAL BELANJA');
    }

    #[Test]
    public function super_admin_can_render_dotmatrix_test_print(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.printer.test', 'dotmatrix'))
            ->assertOk()
            ->assertSee('FAKTUR PENJUALAN')
            ->assertSee('Hormat Kami');
    }

    #[Test]
    public function super_admin_can_print_daily_report_in_thermal_and_dotmatrix(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('reports.daily.print', ['format' => 'thermal']))
            ->assertOk()
            ->assertSee('RINGKASAN LAPORAN HARIAN')
            ->assertSee('PERFORMA OMZET');

        $this->actingAs($admin)
            ->get(route('reports.daily.print', ['format' => 'dotmatrix']))
            ->assertOk()
            ->assertSee('REKAPITULASI LAPORAN HARIAN')
            ->assertSee('REKAPITULASI FINANSIAL');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super_admin'));

        return $user;
    }
}
