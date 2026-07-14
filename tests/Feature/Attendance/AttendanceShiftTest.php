<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Enums\CashShiftStatus;
use App\Exceptions\ServiceException;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\User;
use App\Models\Warehouse;
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
        $this->actingAs($this->cashier)->get(route('attendance.check.show'))->assertOk()->assertSee('Check-in/Check-out');
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
        $this->expectException(ServiceException::class);
        try {
            app(AttendanceService::class)->checkIn($this->cashier, ['checked_at' => '2026-07-14 22:09:00']);
        } finally {
            $checkedOut = app(AttendanceService::class)->checkOut($this->cashier, ['checked_at' => '2026-07-15 05:40:00']);
            $this->assertSame(10, $checkedOut->early_leave_minutes);
            $this->assertSame(452, $checkedOut->worked_minutes);
        }
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

    private function createSchedule(string $date): void
    {
        app(AttendanceService::class)->createSchedule([
            'employee_id' => $this->cashierEmployee->id,
            'work_shift_id' => $this->morningShift->id,
            'work_location_id' => $this->branchLocation->id,
            'scheduled_date' => $date,
        ], $this->supervisor);
    }
}
