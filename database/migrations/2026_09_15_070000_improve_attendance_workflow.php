<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendances', 'verification_status')) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->string('verification_status', 30)->default('not_required')->after('status')->index();
            });
        }
        if (! Schema::hasColumn('attendances', 'verified_by')) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->foreignId('verified_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('attendances', 'verified_at')) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            });
        }
        if (! Schema::hasColumn('attendances', 'verification_note')) {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->text('verification_note')->nullable()->after('verified_at');
            });
        }

        if (! Schema::hasTable('employee_schedule_patterns')) {
            Schema::create('employee_schedule_patterns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained()->restrictOnDelete();
                $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
                $table->date('effective_from')->index();
                $table->date('effective_until')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('employee_schedules', 'source')) {
            Schema::table('employee_schedules', function (Blueprint $table): void {
                $table->string('source', 30)->default('manual')->after('status')->index();
            });
        }
        if (! Schema::hasColumn('employee_schedules', 'employee_schedule_pattern_id')) {
            Schema::table('employee_schedules', function (Blueprint $table): void {
                $table->foreignId('employee_schedule_pattern_id')->nullable()->after('source')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasTable('employee_schedule_pattern_days')) {
            Schema::create('employee_schedule_pattern_days', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_schedule_pattern_id');
                $table->unsignedTinyInteger('weekday');
                $table->unsignedBigInteger('work_shift_id')->nullable();
                $table->string('status', 30)->default('scheduled');
                $table->unique(['employee_schedule_pattern_id', 'weekday'], 'schedule_pattern_weekday_unique');
                $table->foreign('employee_schedule_pattern_id', 'esp_days_pattern_fk')->references('id')->on('employee_schedule_patterns')->cascadeOnDelete();
                $table->foreign('work_shift_id', 'esp_days_shift_fk')->references('id')->on('work_shifts')->nullOnDelete();
            });
        } else {
            $foreignNames = collect(Schema::getForeignKeys('employee_schedule_pattern_days'))->pluck('name');
            $indexNames = collect(Schema::getIndexes('employee_schedule_pattern_days'))->pluck('name');
            Schema::table('employee_schedule_pattern_days', function (Blueprint $table) use ($foreignNames, $indexNames): void {
                if (! $indexNames->contains('schedule_pattern_weekday_unique')) {
                    $table->unique(['employee_schedule_pattern_id', 'weekday'], 'schedule_pattern_weekday_unique');
                }
                if (! $foreignNames->contains('esp_days_pattern_fk')) {
                    $table->foreign('employee_schedule_pattern_id', 'esp_days_pattern_fk')->references('id')->on('employee_schedule_patterns')->cascadeOnDelete();
                }
                if (! $foreignNames->contains('esp_days_shift_fk')) {
                    $table->foreign('work_shift_id', 'esp_days_shift_fk')->references('id')->on('work_shifts')->nullOnDelete();
                }
            });
        }

        $attendanceCheck = Permission::query()->where('name', 'attendance.check')->first();
        $supervisorShift = Role::query()->where('name', 'supervisor_shift')->first();
        if ($attendanceCheck instanceof Permission && $supervisorShift instanceof Role) {
            $supervisorShift->givePermissionTo($attendanceCheck);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        $attendanceCheck = Permission::query()->where('name', 'attendance.check')->first();
        $supervisorShift = Role::query()->where('name', 'supervisor_shift')->first();
        if ($attendanceCheck instanceof Permission && $supervisorShift instanceof Role) {
            $supervisorShift->revokePermissionTo($attendanceCheck);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Schema::dropIfExists('employee_schedule_pattern_days');
        Schema::table('employee_schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_schedule_pattern_id');
            $table->dropColumn('source');
        });
        Schema::dropIfExists('employee_schedule_patterns');
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verification_status', 'verified_at', 'verification_note']);
        });
    }
};
