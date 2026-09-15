<?php

namespace Tests\Feature\WorkChecklist;

use App\Enums\WorkChecklistItemStatus;
use App\Enums\WorkChecklistStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkChecklist;
use App\Models\WorkChecklistTemplate;
use App\Models\WorkLocation;
use App\Services\WorkChecklist\WorkChecklistService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkChecklistFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function generator_makes_daily_location_checklist_and_is_idempotent(): void
    {
        $branch = WorkLocation::factory()->create(['type' => 'branch']);
        $cashier = $this->userWithRole('kasir', $branch);
        $service = app(WorkChecklistService::class);
        $moment = CarbonImmutable::parse('2026-09-15 10:00:00', 'Asia/Jakarta');

        $service->generateCurrentFor($cashier, $moment);
        $service->generateCurrentFor($cashier, $moment);

        $this->assertDatabaseCount('work_checklists', 1);
        $run = WorkChecklist::query()->with('items')->firstOrFail();
        $this->assertSame('daily', $run->frequency->value);
        $this->assertSame('location:'.$branch->id, $run->scope_key);
        $this->assertSame('2026-09-15', $run->period_start->toDateString());
        $this->assertSame('2026-09-15', $run->period_end->toDateString());
        $this->assertCount(12, $run->items);
    }

    #[Test]
    public function multiple_roles_are_merged_and_week_runs_monday_to_sunday(): void
    {
        $branch = WorkLocation::factory()->create(['type' => 'branch']);
        $user = $this->userWithRole('kasir', $branch);
        $user->assignRole(Role::findOrCreate('kepala_toko'));

        app(WorkChecklistService::class)->generateCurrentFor($user, CarbonImmutable::parse('2026-09-16 10:00:00', 'Asia/Jakarta'));

        $daily = WorkChecklist::query()->where('frequency', 'daily')->with('items')->firstOrFail();
        $weekly = WorkChecklist::query()->where('frequency', 'weekly')->firstOrFail();
        $this->assertEqualsCanonicalizing(['kasir', 'kepala_toko'], $daily->role_snapshot);
        $this->assertSame($daily->items->count(), $daily->items->pluck('item_key')->unique()->count());
        $this->assertSame('2026-09-14', $weekly->period_start->toDateString());
        $this->assertSame('2026-09-20', $weekly->period_end->toDateString());
    }

    #[Test]
    public function item_notes_completion_lateness_and_correction_are_audited(): void
    {
        $branch = WorkLocation::factory()->create(['type' => 'branch']);
        $cashier = $this->userWithRole('kasir', $branch);
        $service = app(WorkChecklistService::class);
        $service->generateCurrentFor($cashier, CarbonImmutable::parse('2026-09-15 10:00:00', 'Asia/Jakarta'));
        $run = WorkChecklist::query()->with('items')->firstOrFail();
        $item = $run->items->first();

        $this->actingAs($cashier)->patch(route('work-checklists.items.update', $item), ['status' => 'blocked'])
            ->assertSessionHasErrors('note');
        $this->actingAs($cashier)->patch(route('work-checklists.items.update', $item), ['status' => 'blocked', 'note' => 'Menunggu perangkat kasir'])
            ->assertSessionHasNoErrors();

        foreach ($run->items->where('id', '!=', $item->id) as $other) {
            $other->update(['status' => WorkChecklistItemStatus::DONE, 'responded_at' => now()]);
        }
        $this->travelTo(CarbonImmutable::parse('2026-09-16 00:15:00', 'Asia/Jakarta'));
        $this->actingAs($cashier)->post(route('work-checklists.complete', $run))->assertSessionHasNoErrors();
        $run->refresh();
        $this->assertSame(WorkChecklistStatus::COMPLETED, $run->status);
        $this->assertTrue($run->first_completed_at->greaterThan($run->due_at));

        $this->actingAs($cashier)->post(route('work-checklists.reopen', $run), ['reason' => 'Catatan kendala perlu diperbaiki'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('work_checklists', ['id' => $run->id, 'status' => 'open', 'completed_at' => null]);
        $this->assertTrue(AuditLog::query()->where('event', 'work_checklist.reopened')->exists());
    }

    #[Test]
    public function manager_recap_is_restricted_to_subordinates_in_assigned_location(): void
    {
        $branchA = WorkLocation::factory()->create(['type' => 'branch']);
        $branchB = WorkLocation::factory()->create(['type' => 'branch']);
        $manager = $this->userWithRole('kepala_toko', $branchA);
        $cashierA = $this->userWithRole('kasir', $branchA);
        $cashierB = $this->userWithRole('kasir', $branchB);
        $service = app(WorkChecklistService::class);
        $service->generateCurrentFor($manager);
        $service->generateCurrentFor($cashierA);
        $service->generateCurrentFor($cashierB);

        $response = $this->actingAs($manager)->get(route('work-checklists.index', ['tab' => 'recap']));
        $response->assertOk()->assertSee($cashierA->name)->assertDontSee($cashierB->name);

        $foreignRun = WorkChecklist::query()->where('user_id', $cashierB->id)->firstOrFail();
        $this->actingAs($manager)->get(route('work-checklists.index', ['tab' => 'recap', 'checklist_id' => $foreignRun->id]))->assertForbidden();
    }

    #[Test]
    public function overdue_open_checklist_stays_actionable_on_my_checklist_tab(): void
    {
        $branch = WorkLocation::factory()->create(['type' => 'branch']);
        $cashier = $this->userWithRole('kasir', $branch);
        $service = app(WorkChecklistService::class);
        $service->generateCurrentFor($cashier, CarbonImmutable::parse('2026-09-15 10:00:00', 'Asia/Jakarta'));
        $overdue = WorkChecklist::query()->where('user_id', $cashier->id)->firstOrFail();

        $this->travelTo(CarbonImmutable::parse('2026-09-16 08:00:00', 'Asia/Jakarta'));

        $this->actingAs($cashier)->get(route('work-checklists.index'))
            ->assertOk()
            ->assertSee('Terlambat')
            ->assertViewHas('currentRuns', fn ($runs): bool => $runs->contains('id', $overdue->id));
    }

    #[Test]
    public function b2b_only_account_cannot_access_internal_checklists(): void
    {
        $customer = $this->userWithRole('langganan_staff');

        $this->actingAs($customer)->get(route('work-checklists.index'))->assertRedirect(route('langganan.dashboard'));
        $this->assertDatabaseMissing('work_checklists', ['user_id' => $customer->id]);
    }

    #[Test]
    public function owner_can_filter_recap_and_export_csv(): void
    {
        $owner = $this->userWithRole('owner_viewer');
        app(WorkChecklistService::class)->generateCurrentFor($owner, CarbonImmutable::parse('2026-09-15 10:00:00', 'Asia/Jakarta'));

        $this->actingAs($owner)->get(route('work-checklists.index', ['tab' => 'recap', 'frequency' => 'weekly']))
            ->assertOk()->assertSee('Rekap Tim')->assertSee($owner->name);
        $this->actingAs($owner)->get(route('work-checklists.export', ['frequency' => 'weekly']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    #[Test]
    public function admin_config_can_schedule_a_new_template_version(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 09:00:00', 'Asia/Jakarta'));
        $admin = $this->userWithRole('admin_config');
        app(WorkChecklistService::class)->ensureDefaultTemplates();
        $source = WorkChecklistTemplate::query()->where('template_key', 'admin_config_daily')->firstOrFail();

        $this->actingAs($admin)->put(route('work-checklists.templates.version', $source), [
            'source_template_id' => $source->id,
            'template_key' => $source->template_key,
            'name' => 'Checklist Harian Admin Config Baru',
            'frequency' => 'daily',
            'scope' => 'global',
            'effective_from' => '2026-09-20',
            'roles' => ['admin_config'],
            'items' => [['item_key' => 'admin_config.review', 'label' => 'Review master data', 'is_required' => 1]],
        ])->assertRedirect(route('work-checklists.index', ['tab' => 'templates']));

        $this->assertSame('2026-09-20', WorkChecklistTemplate::query()->where('template_key', 'admin_config_daily')->where('version', 2)->firstOrFail()->effective_from->toDateString());
        $this->assertSame('2026-09-19', $source->fresh()->effective_until->toDateString());
    }

    private function userWithRole(string $role, ?WorkLocation $location = null): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::findOrCreate($role));
        if ($location) {
            $user->workLocations()->sync([$location->id => ['is_default' => true, 'is_active' => true]]);
        }

        return $user;
    }
}
