<?php

namespace Tests\Feature\StaffBonus;

use App\Enums\StaffBonusPeriodStatus;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkChecklist;
use App\Models\WorkLocation;
use App\Services\Control\ApprovalWorkflowService;
use App\Services\StaffBonus\StaffBonusService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffBonusModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        CarbonImmutable::setTestNow('2026-09-15 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_program_activation_creates_snapshot_and_caps_bonus_score(): void
    {
        [$owner, $staff, $location] = $this->fixtures();
        $this->completedChecklist($staff, $location);
        $service = app(StaffBonusService::class);
        $program = $service->createProgram([
            'program_key' => 'TOKO-SKOR-OKT', 'name' => 'Bonus Toko Oktober', 'role_name' => 'staf_toko',
            'work_location_id' => $location->id, 'month' => 10, 'year' => 2026, 'maximum_bonus_amount' => '1000000',
            'user_ids' => [$staff->id],
            'metrics' => [['metric_key' => 'checklist_on_time', 'target_value' => '50', 'weight_percentage' => '100']],
        ], $owner);

        $period = $service->activate($program, $owner);
        $result = $period->results->first();

        $this->assertSame('active', $period->status->value);
        $this->assertSame('100.00', (string) $result->final_score);
        $this->assertSame('1000000.00', (string) $result->bonus_amount);
        $this->assertSame('100.00', (string) $result->metrics->first()->score);
        $this->assertDatabaseHas('staff_bonus_programs', ['id' => $program->id, 'status' => 'active']);
    }

    public function test_period_uses_existing_approval_workflow_and_payment_is_final(): void
    {
        [$owner, $staff, $location] = $this->fixtures();
        $this->completedChecklist($staff, $location);
        $approver = User::factory()->create();
        $approver->assignRole('owner_approver');
        $service = app(StaffBonusService::class);
        $program = $service->createProgram([
            'program_key' => 'TOKO-OKT', 'name' => 'Bonus Toko Oktober', 'role_name' => 'staf_toko',
            'work_location_id' => $location->id, 'month' => 10, 'year' => 2026, 'maximum_bonus_amount' => '500000',
            'user_ids' => [$staff->id],
            'metrics' => [['metric_key' => 'checklist_on_time', 'target_value' => '100', 'weight_percentage' => '100']],
        ], $owner);
        $period = $service->activate($program, $owner);
        $nextProgram = $service->createProgram([
            'program_key' => 'TOKO-NOV', 'name' => 'Bonus Toko November', 'role_name' => 'staf_toko',
            'work_location_id' => $location->id, 'month' => 11, 'year' => 2026, 'maximum_bonus_amount' => '500000',
            'user_ids' => [$staff->id],
            'metrics' => [['metric_key' => 'checklist_on_time', 'target_value' => '100', 'weight_percentage' => '100']],
        ], $owner);
        $nextPeriod = $service->activate($nextProgram, $owner);

        CarbonImmutable::setTestNow('2026-11-02 10:00:00');
        $period = $service->submit($period, $owner);
        app(ApprovalWorkflowService::class)->approve($period->approvalRequest, $approver, 'Disetujui');
        $period->refresh();
        $this->assertSame(StaffBonusPeriodStatus::APPROVED, $period->status);

        $result = $period->results()->firstOrFail();
        $this->actingAs($approver)->post(route('staff-bonuses.results.pay', $result), [
            'paid_on' => '2026-11-02', 'payment_method' => 'bank_transfer', 'reference_no' => 'TRX-BONUS-001',
        ])->assertRedirect();
        $this->assertDatabaseHas('staff_bonus_payments', ['staff_bonus_result_id' => $result->id, 'amount' => '500000.00', 'reference_no' => 'TRX-BONUS-001']);
        $this->assertDatabaseHas('staff_bonus_periods', ['id' => $period->id, 'status' => 'closed']);
        $this->actingAs($approver)->post(route('staff-bonuses.results.pay', $result), [
            'paid_on' => '2026-11-02', 'payment_method' => 'cash', 'reference_no' => 'DUPLIKAT',
        ])->assertStatus(422);
        $this->actingAs($approver)->post(route('staff-bonuses.results.adjust', $result), [
            'target_result_id' => $nextPeriod->results->first()->id, 'amount' => '25000', 'reason' => 'Koreksi bonus periode Oktober.',
        ])->assertRedirect();
        $this->assertDatabaseHas('staff_bonus_adjustments', ['source_result_id' => $result->id, 'target_result_id' => $nextPeriod->results->first()->id, 'amount' => '25000.00']);
    }

    public function test_staff_cannot_open_another_users_result_and_head_is_location_scoped(): void
    {
        [$owner, $staff, $location] = $this->fixtures();
        $service = app(StaffBonusService::class);
        $program = $service->createProgram([
            'program_key' => 'AKSES-OKT', 'name' => 'Bonus Akses', 'role_name' => 'staf_toko',
            'work_location_id' => $location->id, 'month' => 10, 'year' => 2026, 'maximum_bonus_amount' => '100000',
            'user_ids' => [$staff->id], 'metrics' => [['metric_key' => 'attendance_rate', 'target_value' => '100', 'weight_percentage' => '100']],
        ], $owner);
        $result = $service->activate($program, $owner)->results->first();

        $other = User::factory()->create();
        $other->assignRole('staf_toko');
        $this->actingAs($other)->get(route('staff-bonuses.index', ['result_id' => $result->id]))->assertForbidden();

        $head = User::factory()->create();
        $head->assignRole('kepala_toko');
        $head->workLocations()->attach($location->id, ['is_default' => true, 'is_active' => true]);
        $this->actingAs($head)->get(route('staff-bonuses.index', ['tab' => 'team', 'result_id' => $result->id]))->assertOk();
    }

    /** @return array{User, User, WorkLocation} */
    private function fixtures(): array
    {
        $location = WorkLocation::factory()->create(['type' => 'branch']);
        $owner = User::factory()->create();
        $owner->assignRole('super_admin');
        $staff = User::factory()->create();
        $staff->assignRole('staf_toko');
        Employee::query()->create(['user_id' => $staff->id, 'work_location_id' => $location->id, 'employee_no' => 'EMP-001', 'name' => $staff->name, 'joined_at' => '2026-01-01', 'status' => 'active', 'is_active' => true]);

        return [$owner, $staff, $location];
    }

    private function completedChecklist(User $staff, WorkLocation $location): void
    {
        WorkChecklist::query()->create([
            'user_id' => $staff->id, 'work_location_id' => $location->id, 'scope_key' => 'location:'.$location->id,
            'frequency' => 'daily', 'period_start' => '2026-10-01', 'period_end' => '2026-10-01',
            'due_at' => '2026-10-01 23:59:59', 'status' => 'completed', 'role_snapshot' => ['staf_toko'],
            'template_snapshot' => [], 'first_completed_at' => '2026-10-01 10:00:00', 'completed_at' => '2026-10-01 10:00:00',
        ]);
    }
}
