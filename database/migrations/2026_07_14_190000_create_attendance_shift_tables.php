<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_no', 80)->unique();
            $table->string('name', 160);
            $table->string('position', 120)->nullable()->index();
            $table->string('whatsapp_number', 40)->nullable();
            $table->date('joined_at')->nullable();
            $table->string('status', 40)->default('active')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('placement_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['work_location_id', 'status']);
        });

        Schema::create('work_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_cross_midnight')->default(false);
            $table->unsignedSmallInteger('tolerance_late_minutes')->default(0);
            $table->unsignedSmallInteger('tolerance_early_leave_minutes')->default(0);
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->json('work_days')->nullable();
            $table->date('effective_from')->nullable()->index();
            $table->date('effective_until')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['work_location_id', 'is_active']);
        });

        Schema::create('employee_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->date('scheduled_date')->index();
            $table->timestamp('scheduled_start_at')->nullable()->index();
            $table->timestamp('scheduled_end_at')->nullable()->index();
            $table->string('status', 40)->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'scheduled_date']);
            $table->index(['work_location_id', 'scheduled_date']);
        });

        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date')->index();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 40)->default('present')->index();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('check_in_method', 40)->nullable();
            $table->string('check_out_method', 40)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('device_info')->nullable();
            $table->string('location_note')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['work_location_id', 'attendance_date', 'status']);
        });

        Schema::create('attendance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->string('type', 40)->index();
            $table->timestamp('start_at')->index();
            $table->timestamp('end_at')->index();
            $table->text('reason');
            $table->string('proof_path')->nullable();
            $table->foreignId('replacement_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 40)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status', 'start_at']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('old_check_in_at')->nullable();
            $table->timestamp('old_check_out_at')->nullable();
            $table->timestamp('proposed_check_in_at')->nullable();
            $table->timestamp('proposed_check_out_at')->nullable();
            $table->text('reason');
            $table->string('proof_path')->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->foreignId('attendance_id')->nullable()->after('cashier_user_id')->constrained()->nullOnDelete();
            $table->foreignId('attendance_override_by')->nullable()->after('attendance_id')->constrained('users')->nullOnDelete();
            $table->text('attendance_override_reason')->nullable()->after('attendance_override_by');
        });
    }

    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attendance_id');
            $table->dropConstrainedForeignId('attendance_override_by');
            $table->dropColumn('attendance_override_reason');
        });
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employee_schedules');
        Schema::dropIfExists('work_shifts');
        Schema::dropIfExists('employees');
    }
};
