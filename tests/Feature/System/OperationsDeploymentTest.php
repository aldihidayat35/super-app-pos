<?php

namespace Tests\Feature\System;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationsDeploymentTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $adminConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole(Role::findOrCreate('super_admin'));

        $this->adminConfig = User::factory()->create(['is_active' => true]);
        $this->adminConfig->assignRole(Role::findOrCreate('admin_config'));
    }

    #[Test]
    public function super_admin_can_open_all_p28_operations_pages(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.system.backups.index'))->assertOk()->assertSee('Backup Database dan File');
        $this->actingAs($this->superAdmin)->get(route('admin.system.logs.index'))->assertOk()->assertSee('Log Aplikasi dan Queue');
        $this->actingAs($this->superAdmin)->get(route('admin.system.imports.index'))->assertOk()->assertSee('Import Data Awal');
        $this->actingAs($this->superAdmin)->get(route('admin.system.maintenance.index'))->assertOk()->assertSee('Maintenance dan Go-Live');
    }

    #[Test]
    public function non_super_admin_cannot_open_operations_pages(): void
    {
        $this->actingAs($this->adminConfig)->get(route('admin.system.backups.index'))->assertForbidden();
        $this->actingAs($this->adminConfig)->get(route('admin.system.logs.index'))->assertForbidden();
        $this->actingAs($this->adminConfig)->get(route('admin.system.imports.index'))->assertForbidden();
        $this->actingAs($this->adminConfig)->get(route('admin.system.maintenance.index'))->assertForbidden();
    }

    #[Test]
    public function backup_page_lists_encrypted_backup_and_requires_signed_download(): void
    {
        Storage::fake('local');
        config(['security.backup.disk' => 'local', 'security.backup.path' => 'private/backups']);
        Storage::disk('local')->put('private/backups/test.sql.enc', 'encrypted-payload');

        $this->actingAs($this->superAdmin)
            ->get(route('admin.system.backups.index'))
            ->assertOk()
            ->assertSee('test.sql.enc')
            ->assertSee(hash('sha256', 'encrypted-payload'));

        $this->actingAs($this->superAdmin)
            ->get(route('admin.system.backups.download', ['file' => rtrim(strtr(base64_encode('private/backups/test.sql.enc'), '+/', '-_'), '=')]))
            ->assertForbidden();

        $signedUrl = URL::temporarySignedRoute('admin.system.backups.download', now()->addMinutes(5), [
            'file' => rtrim(strtr(base64_encode('private/backups/test.sql.enc'), '+/', '-_'), '='),
        ]);

        $this->actingAs($this->superAdmin)->get($signedUrl)->assertOk();
    }

    #[Test]
    public function initial_import_template_and_dry_run_preview_work(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.system.imports.templates.download', 'suppliers'))
            ->assertOk()
            ->assertSee('code,name,phone_number,email,payment_term_days');

        $file = UploadedFile::fake()->createWithContent('suppliers.csv', "code,name,phone_number,email,payment_term_days\nSUP-001,Supplier Test,0812,supplier@example.test,30\n");

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.imports.preview'), [
                'type' => 'suppliers',
                'file' => $file,
                'dry_run' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('import_preview');
    }

    #[Test]
    public function maintenance_action_requires_explicit_confirmation(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.maintenance.run'), [
                'action' => 'queue_restart',
                'confirmation' => 'salah',
            ])
            ->assertSessionHasErrors('confirmation');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.maintenance.run'), [
                'action' => 'queue_restart',
                'confirmation' => 'SAYA MENGERTI',
                'message' => 'Restart worker setelah deploy.',
            ])
            ->assertRedirect();
    }
}
