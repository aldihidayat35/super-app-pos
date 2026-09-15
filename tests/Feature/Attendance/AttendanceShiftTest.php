<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceVerificationStatus;
use App\Enums\CashShiftStatus;
use App\Exceptions\ServiceException;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkChecklist;
use App\Models\WorkChecklistItem;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $supervisor;

    private User $otherSupervisor;

    private WorkLocation $branchLocation;

    private WorkLocation $otherLocation;

    private Branch $branch;

    private Employee $cashierEmployee;

    private WorkShift $morningShift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-HR', 'name' => 'Toko HR']);
        $this->otherLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-OTHER', 'name' => 'Toko Lain']);
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-HR', 'name' => 'Gudang HR']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id]);
        $this->branch = Branch::factory()->create(['work_location_id' => $this->branchLocation->id, 'primary_warehouse_id' => $warehouse->id]);

        $this->cashier = User::factory()->create(['is_active' => true, 'username' => 'kasir-hr']);
        $this->cashier->assignRole(Role::findOrCreate('kasir'));
        $this->cashier->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->supervisor = User::factory()->create(['is_active' => true, 'username' => 'spv-hr']);
        $this->supervisor->assignRole(Role::findOrCreate('kepala_toko'));
        $this->supervisor->workLocations()->sync([$this->branchLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->otherSupervisor = User::factory()->create(['is_active' => true, 'username' => 'spv-other-hr']);
        $this->otherSupervisor->assignRole(Role::findOrCreate('kepala_toko'));
        $this->otherSupervisor->workLocations()->sync([$this->otherLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->cashierEmployee = Employee::query()->create([
            'user_id' => $this->cashier->id,
            'work_location_id' => $this->branchLocation->id,
            'employee_no' => 'EMP-HR-001',
            'name' => 'Kasir HR',
            'position' => 'Kasir',
            'whatsapp_number' => '081234567890',
            'joined_at' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->morningShift = WorkShift::query()->create([
            'work_location_id' => $this->branchLocation->id,
            'code' => 'PAGI-HR',
            'name' => 'Shift Pagi HR',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'tolerance_late_minutes' => 10,
            'tolerance_early_leave_minutes' => 10,
            'break_minutes' => 60,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_p22_pages_can_be_opened_and_scoped(): void
    {
        $this->createSchedule('2026-07-14');
        $this->actingAs($this->supervisor)->get(route('attendance.employees.index'))->assertOk()->assertSee('Kasir HR');
        $this->actingAs($this->supervisor)->get(route('attendance.employees.create'))->assertOk()->assertSee('Tambah Karyawan');
        $this->actingAs($this->supervisor)->get(route('attendance.work-shifts.index'))->assertOk()->assertSee('Shift Pagi HR');
        $this->actingAs($this->supervisor)->get(route('attendance.work-shifts.create'))->assertOk()->assertSee('Tambah Shift');
        $this->actingAs($this->supervisor)->get(route('attendance.schedules.index'))->assertOk()->assertSee('Jadwal Shift Karyawan');
        $this->actingAs($this->cashier)->get(route('attendance.check.show'))->assertOk()->assertSee('Kehadiran');
        $this->actingAs($this->cashier)->get(route('attendance.requests.index'))->assertOk()->assertSee('Pengajuan Izin');
        $this->actingAs($this->supervisor)->get(route('attendance.corrections.index'))->assertOk()->assertSee('Koreksi Absensi');
        $this->actingAs($this->supervisor)->get(route('reports.attendance.index'))->assertOk()->assertSee('Laporan Kehadiran');
        $this->actingAs($this->supervisor)->get(route('reports.shift-productivity.index'))->assertOk()->assertSee('Produktivitas Shift');

        $this->actingAs($this->otherSupervisor)->get(route('attendance.employees.index'))->assertOk()->assertDontSee('Kasir HR');
    }

    public function test_cross_midnight_late_duplicate_checkin_and_checkout_are_calculated(): void
    {
        $night = WorkShift::query()->create([
            'work_location_id' => $this->branchLocation->id,
            'code' => 'MLM-HR',
            'name' => 'Shift Malam HR',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'is_cross_midnight' => true,
            'tolerance_late_minutes' => 5,
            'tolerance_early_leave_minutes' => 10,
            'is_active' => true,
        ]);
        app(AttendanceService::class)->createSchedule([
            'employee_id' => $this->cashierEmployee->id,
            'work_shift_id' => $night->id,
            'work_location_id' => $this->branchLocation->id,
            'scheduled_date' => '2026-07-14',
        ], $this->supervisor);

        $attendance = app(AttendanceService::class)->checkIn($this->cashier, ['checked_at' => '2026-07-14 22:08:00', 'method' => 'login']);

        $this->assertSame(AttendanceStatus::LATE, $attendance->status);
        $this->assertSame(3, $attendance->late_minutes);
        try {
            app(AttendanceService::class)->checkIn($this->cashier, ['checked_at' => '2026-07-14 22:09:00']);
            $this->fail('Check-in kedua seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('sudah tercatat', $exception->getMessage());
        }

        $this->completeDailyChecklist('2026-07-14');
        $checkedOut = app(AttendanceService::class)->checkOut($this->cashier, ['checked_at' => '2026-07-15 05:40:00']);
        $this->assertSame(10, $checkedOut->early_leave_minutes);
        $this->assertSame(452, $checkedOut->worked_minutes);
    }

    public function test_leave_request_approval_creates_attendance_and_overlap_is_rejected(): void
    {
        Carbon::setTestNow('2026-07-14 09:00:00');
        $request = app(AttendanceService::class)->submitRequest($this->cashier, [
            'type' => 'sick',
            'start_at' => '2026-07-15 08:00:00',
            'end_at' => '2026-07-15 16:00:00',
            'reason' => 'Sakit demam.',
        ]);

        $this->assertSame(AttendanceRequestStatus::PENDING, $request->status);
        $this->expectException(ServiceException::class);
        try {
            app(AttendanceService::class)->submitRequest($this->cashier, [
                'type' => 'leave',
                'start_at' => '2026-07-15 10:00:00',
                'end_at' => '2026-07-15 12:00:00',
                'reason' => 'Bentrok.',
            ]);
        } finally {
            app(AttendanceService::class)->approveRequest($request, $this->supervisor, true, 'Cepat sembuh.');
            $this->assertDatabaseHas('attendances', [
                'employee_id' => $this->cashierEmployee->id,
                'attendance_date' => '2026-07-15 00:00:00',
                'status' => AttendanceStatus::SICK->value,
            ]);
        }
    }

    public function test_correction_approval_keeps_audit_and_updates_attendance(): void
    {
        $this->createSchedule('2026-07-14');
        $attendance = app(AttendanceService::class)->checkIn($this->cashier, ['checked_at' => '2026-07-14 08:20:00']);
        $this->completeDailyChecklist('2026-07-14');
        app(AttendanceService::class)->checkOut($this->cashier, ['checked_at' => '2026-07-14 16:00:00']);
        $correction = app(AttendanceService::class)->submitCorrection($attendance->fresh(), [
            'proposed_check_in_at' => '2026-07-14 08:00:00',
            'proposed_check_out_at' => '2026-07-14 16:05:00',
            'reason' => 'Mesin absen terlambat sinkron.',
        ], $this->supervisor);

        app(AttendanceService::class)->approveCorrection($correction, $this->supervisor, true, 'Audit OK.');

        $attendance = $attendance->fresh();
        $this->assertSame(0, $attendance->late_minutes);
        $this->assertSame('approved', AttendanceCorrection::query()->findOrFail($correction->id)->status->value);
        $this->assertNotNull(AttendanceCorrection::query()->findOrFail($correction->id)->before_snapshot);
        $this->assertNotNull(AttendanceCorrection::query()->findOrFail($correction->id)->after_snapshot);
    }

    public function test_cash_shift_requires_checked_in_schedule_and_allows_supervisor_override_with_reason(): void
    {
        Carbon::setTestNow('2026-07-14 08:00:00');
        $this->createSchedule('2026-07-14');

        $this->actingAs($this->cashier)->post(route('retail.shifts.store'), [
            'branch_id' => $this->branch->id,
            'opening_cash_amount' => 100000,
        ])->assertSessionHasErrors('shift');

        app(AttendanceService::class)->checkIn($this->cashier, ['checked_at' => '2026-07-14 08:00:00']);

        $this->actingAs($this->cashier)->post(route('retail.shifts.store'), [
            'branch_id' => $this->branch->id,
            'opening_cash_amount' => 100000,
            'terminal_code' => 'POS-HR-1',
        ])->assertRedirect(route('retail.shifts.current'));

        $this->assertDatabaseHas('cash_shifts', [
            'cashier_user_id' => $this->cashier->id,
            'status' => CashShiftStatus::OPEN->value,
            'attendance_override_reason' => null,
        ]);

        $supervisorEmployee = Employee::query()->create([
            'user_id' => $this->supervisor->id,
            'work_location_id' => $this->branchLocation->id,
            'employee_no' => 'EMP-HR-002',
            'name' => 'Supervisor HR',
            'position' => 'Kepala Toko',
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->assertNotNull($supervisorEmployee);
        CashShift::query()->where('cashier_user_id', $this->cashier->id)->update(['status' => CashShiftStatus::CLOSED->value, 'closed_at' => now()]);

        $this->actingAs($this->supervisor)->post(route('retail.shifts.store'), [
            'branch_id' => $this->branch->id,
            'opening_cash_amount' => 50000,
            'attendance_override_reason' => 'Absensi supervisor belum dijadwalkan karena pembukaan darurat.',
        ])->assertRedirect(route('retail.shifts.current'));

        $this->assertSame('Absensi supervisor belum dijadwalkan karena pembukaan darurat.', CashShift::query()->where('cashier_user_id', $this->supervisor->id)->latest('id')->firstOrFail()->attendance_override_reason);
    }

    public function test_checkout_waits_for_daily_checklist_and_head_verifies_without_changing_times(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        $this->createSchedule('2026-09-15');
        $attendance = app(AttendanceService::class)->checkIn($this->cashier, []);

        $this->assertSame('2026-09-15 08:00:00', $attendance->check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(AttendanceVerificationStatus::NOT_READY, $attendance->verification_status);

        try {
            app(AttendanceService::class)->checkOut($this->cashier, []);
            $this->fail('Absen pulang tanpa checklist seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('checklist kerja harian', $exception->getMessage());
        }

        $this->completeDailyChecklist('2026-09-15');
        Carbon::setTestNow('2026-09-15 16:05:00');
        $checkedOut = app(AttendanceService::class)->checkOut($this->cashier, []);
        $originalCheckIn = $checkedOut->check_in_at->toDateTimeString();
        $originalCheckOut = $checkedOut->check_out_at->toDateTimeString();
        $this->assertSame(AttendanceVerificationStatus::PENDING, $checkedOut->verification_status);

        try {
            app(AttendanceService::class)->verify($checkedOut, $this->otherSupervisor, true);
            $this->fail('Kepala toko dari lokasi lain seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('tidak berwenang', $exception->getMessage());
        }

        $approved = app(AttendanceService::class)->verify($checkedOut, $this->supervisor, true, 'Jam kerja sesuai.');
        $this->assertSame(AttendanceVerificationStatus::APPROVED, $approved->verification_status);
        $this->assertSame($originalCheckIn, $approved->check_in_at->toDateTimeString());
        $this->assertSame($originalCheckOut, $approved->check_out_at->toDateTimeString());
        $this->assertDatabaseHas('audit_logs', ['event' => 'attendance.approved', 'subject_id' => $approved->id]);
    }

    public function test_checklist_can_be_completed_and_checked_out_in_one_request(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        $this->createSchedule('2026-09-15');
        $attendance = app(AttendanceService::class)->checkIn($this->cashier, []);
        $checklist = WorkChecklist::query()->create([
            'user_id' => $this->cashier->id,
            'work_location_id' => $this->branchLocation->id,
            'scope_key' => 'location:'.$this->branchLocation->id,
            'frequency' => 'daily',
            'period_start' => '2026-09-15',
            'period_end' => '2026-09-15',
            'due_at' => '2026-09-15 23:59:59',
            'status' => 'open',
            'role_snapshot' => ['kasir'],
            'template_snapshot' => [],
        ]);
        WorkChecklistItem::query()->create([
            'work_checklist_id' => $checklist->id,
            'item_key' => 'kasir.penutupan',
            'label' => 'Pastikan pekerjaan kasir selesai',
            'role_snapshot' => ['kasir'],
            'sort_order' => 1,
            'is_required' => true,
            'status' => 'done',
            'responded_at' => now(),
        ]);

        Carbon::setTestNow('2026-09-15 16:00:00');
        $this->actingAs($this->cashier)
            ->post(route('work-checklists.complete-and-check-out', $checklist))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('work_checklists', ['id' => $checklist->id, 'status' => 'completed']);
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'verification_status' => 'pending']);
    }

    public function test_weekly_pattern_generates_28_day_window_and_keeps_manual_exception(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $days = array_fill(1, 7, 'off');
        $days[1] = $this->morningShift->id;
        $days[2] = $this->morningShift->id;
        $pattern = app(AttendanceService::class)->saveWeeklyPattern($this->cashierEmployee, [
            'work_location_id' => $this->branchLocation->id,
            'effective_from' => '2026-09-21',
            'days' => $days,
        ], $this->supervisor);

        $generatedCount = $this->cashierEmployee->schedules()->where('source', 'pattern')->count();
        $this->assertGreaterThan(0, $generatedCount);
        $this->assertDatabaseHas('employee_schedules', [
            'employee_id' => $this->cashierEmployee->id,
            'scheduled_date' => '2026-09-21 00:00:00',
            'status' => 'scheduled',
            'source' => 'pattern',
        ]);

        app(AttendanceService::class)->createSchedule([
            'employee_id' => $this->cashierEmployee->id,
            'work_shift_id' => $this->morningShift->id,
            'work_location_id' => $this->branchLocation->id,
            'scheduled_date' => '2026-09-22',
            'status' => 'day_off',
        ], $this->supervisor);
        app(AttendanceService::class)->generatePatternSchedules($pattern, 28);

        $this->assertSame($generatedCount, $this->cashierEmployee->schedules()->count());
        $this->assertDatabaseHas('employee_schedules', [
            'employee_id' => $this->cashierEmployee->id,
            'scheduled_date' => '2026-09-22 00:00:00',
            'status' => 'day_off',
            'source' => 'manual',
        ]);
    }

    public function test_rejection_needs_reason_and_approved_correction_returns_to_pending_verification(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        $this->createSchedule('2026-09-15');
        $attendance = app(AttendanceService::class)->checkIn($this->cashier, []);
        $this->completeDailyChecklist('2026-09-15');
        Carbon::setTestNow('2026-09-15 16:00:00');
        $attendance = app(AttendanceService::class)->checkOut($this->cashier, []);

        $this->actingAs($this->supervisor)
            ->post(route('attendance.records.reject', $attendance), ['note' => ''])
            ->assertSessionHasErrors('note');
        $this->actingAs($this->supervisor)
            ->post(route('attendance.records.reject', $attendance), ['note' => 'Jam pulang perlu diperiksa kembali.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(AttendanceVerificationStatus::REJECTED, $attendance->fresh()->verification_status);

        $correction = app(AttendanceService::class)->submitCorrection($attendance->fresh(), [
            'proposed_check_out_at' => '2026-09-15 16:05:00',
            'reason' => 'Jam pulang yang benar sesuai catatan operasional.',
        ], $this->supervisor);
        app(AttendanceService::class)->approveCorrection($correction, $this->supervisor, true, 'Waktu koreksi sesuai.');

        $corrected = $attendance->fresh();
        $this->assertSame(AttendanceVerificationStatus::PENDING, $corrected->verification_status);
        $this->assertNull($corrected->verified_by);
        $this->assertSame('16:05', $corrected->check_out_at->format('H:i'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'attendance.correction_approved', 'subject_id' => $correction->id]);
    }

    public function test_head_can_record_emergency_checkout_for_subordinate_without_completed_checklist(): void
    {
        Carbon::setTestNow('2026-09-15 08:00:00');
        $this->createSchedule('2026-09-15');
        $attendance = app(AttendanceService::class)->checkIn($this->cashier, []);
        Carbon::setTestNow('2026-09-15 16:15:00');

        try {
            app(AttendanceService::class)->supervisorCheckOut($attendance, $this->otherSupervisor, 'Gangguan sistem di toko lain.');
            $this->fail('Kepala dari lokasi lain seharusnya tidak dapat mencatat pulang.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('tidak berwenang', $exception->getMessage());
        }

        $checkedOut = app(AttendanceService::class)->supervisorCheckOut($attendance, $this->supervisor, 'Checklist tidak dapat dibuka karena gangguan sistem.');
        $this->assertSame('supervisor', $checkedOut->check_out_method);
        $this->assertSame(AttendanceVerificationStatus::PENDING, $checkedOut->verification_status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'attendance.supervisor_checked_out', 'subject_id' => $attendance->id]);
    }

    private function createSchedule(string $date): void
    {
        app(AttendanceService::class)->createSchedule([
            'employee_id' => $this->cashierEmployee->id,
            'work_shift_id' => $this->morningShift->id,
            'work_location_id' => $this->branchLocation->id,
            'scheduled_date' => $date,
        ], $this->supervisor);
    }

    private function completeDailyChecklist(string $date): WorkChecklist
    {
        return WorkChecklist::query()->create([
            'user_id' => $this->cashier->id,
            'work_location_id' => $this->branchLocation->id,
            'scope_key' => 'location:'.$this->branchLocation->id,
            'frequency' => 'daily',
            'period_start' => $date,
            'period_end' => $date,
            'due_at' => $date.' 23:59:59',
            'status' => 'completed',
            'role_snapshot' => ['kasir'],
            'template_snapshot' => [],
            'first_completed_at' => $date.' 16:00:00',
            'completed_at' => $date.' 16:00:00',
        ]);
    }
}
