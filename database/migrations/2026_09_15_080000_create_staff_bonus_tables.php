<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_bonus_programs', function (Blueprint $table): void {
            $table->id();
            $table->string('program_key', 100);
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('name');
            $table->string('role_name', 80)->index();
            $table->foreignId('work_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('maximum_bonus_amount', 18, 2);
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('legacy_sales_target_id')->nullable()->unique()->constrained('sales_targets')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->json('configuration_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['program_key', 'version'], 'sb_program_key_version_unique');
            $table->index(['year', 'month', 'role_name', 'work_location_id'], 'staff_bonus_program_period_idx');
        });

        Schema::create('staff_bonus_program_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_bonus_program_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key', 100);
            $table->string('label');
            $table->string('scope', 20);
            $table->decimal('target_value', 18, 4);
            $table->decimal('weight_percentage', 7, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['staff_bonus_program_id', 'metric_key'], 'sb_program_metric_unique');
        });

        Schema::create('staff_bonus_program_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_bonus_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('target_override', 18, 4)->nullable();
            $table->decimal('maximum_bonus_override', 18, 2)->nullable();
            $table->timestamps();

            $table->unique(['staff_bonus_program_id', 'user_id'], 'sb_program_user_unique');
        });

        Schema::create('staff_bonus_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_bonus_program_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('status', 30)->default('active')->index();
            $table->json('program_snapshot');
            $table->decimal('total_bonus_amount', 18, 2)->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['year', 'month', 'status']);
        });

        Schema::create('staff_bonus_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_bonus_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role_name', 80);
            $table->decimal('maximum_bonus_amount', 18, 2);
            $table->decimal('final_score', 7, 2)->default(0);
            $table->decimal('calculated_bonus_amount', 18, 2)->default(0);
            $table->decimal('adjustment_amount', 18, 2)->default(0);
            $table->decimal('bonus_amount', 18, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->json('employee_snapshot');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['staff_bonus_period_id', 'user_id'], 'sb_period_user_unique');
            $table->index(['user_id', 'payment_status'], 'sb_user_payment_status_idx');
        });

        Schema::create('staff_bonus_metric_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_bonus_result_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key', 100);
            $table->string('label');
            $table->string('scope', 20);
            $table->decimal('target_value', 18, 4);
            $table->decimal('actual_value', 18, 4)->default(0);
            $table->decimal('score', 7, 2)->default(0);
            $table->decimal('weight_percentage', 7, 4);
            $table->decimal('weighted_score', 7, 2)->default(0);
            $table->json('source_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['staff_bonus_result_id', 'metric_key'], 'sb_result_metric_unique');
        });

        Schema::create('staff_bonus_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_result_id')->constrained('staff_bonus_results')->restrictOnDelete();
            $table->foreignId('target_result_id')->constrained('staff_bonus_results')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['target_result_id', 'applied_at'], 'sb_adjustment_target_applied_idx');
        });

        Schema::create('staff_bonus_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_bonus_result_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('paid_on');
            $table->string('payment_method', 40);
            $table->string('reference_no', 120);
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        $nextMonth = now('Asia/Jakarta')->startOfMonth()->addMonth();
        DB::table('sales_targets')->orderBy('id')->get()->each(function ($target) use ($nextMonth): void {
            $period = Carbon::create((int) $target->year, (int) $target->month, 1, 0, 0, 0, 'Asia/Jakarta');
            if ($period->lt($nextMonth)) {
                return;
            }
            $programId = DB::table('staff_bonus_programs')->insertGetId([
                'program_key' => 'LEGACY-SALES-'.$target->id,
                'version' => 1,
                'name' => 'Konversi Target Sales '.sprintf('%02d/%04d', $target->month, $target->year),
                'role_name' => 'sales',
                'month' => $target->month,
                'year' => $target->year,
                'maximum_bonus_amount' => bcmul((string) $target->target_amount, bcdiv((string) $target->bonus_percentage, '100', 8), 2),
                'status' => 'draft',
                'created_by' => $target->created_by,
                'legacy_sales_target_id' => $target->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('staff_bonus_program_users')->insert(['staff_bonus_program_id' => $programId, 'user_id' => $target->sales_user_id, 'target_override' => $target->target_amount, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([['b2b_net_sales', 'Penjualan B2B bersih', 'individual', $target->target_amount, 80], ['attendance_rate', 'Kehadiran terverifikasi', 'individual', 100, 10], ['checklist_on_time', 'Checklist harian tepat waktu', 'individual', 100, 10]] as $index => [$key, $label, $scope, $metricTarget, $weight]) {
                DB::table('staff_bonus_program_metrics')->insert(['staff_bonus_program_id' => $programId, 'metric_key' => $key, 'label' => $label, 'scope' => $scope, 'target_value' => $metricTarget, 'weight_percentage' => $weight, 'sort_order' => $index + 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_bonus_payments');
        Schema::dropIfExists('staff_bonus_adjustments');
        Schema::dropIfExists('staff_bonus_metric_results');
        Schema::dropIfExists('staff_bonus_results');
        Schema::dropIfExists('staff_bonus_periods');
        Schema::dropIfExists('staff_bonus_program_users');
        Schema::dropIfExists('staff_bonus_program_metrics');
        Schema::dropIfExists('staff_bonus_programs');
    }
};
