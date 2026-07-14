<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->string('terminal_code', 80)->nullable()->index();
            $table->decimal('cash_sales_amount', 18, 2)->default(0);
            $table->decimal('non_cash_sales_amount', 18, 2)->default(0);
            $table->decimal('refund_amount', 18, 2)->default(0);
            $table->decimal('expense_amount', 18, 2)->default(0);
            $table->decimal('receivable_amount', 18, 2)->default(0);
            $table->decimal('difference_amount', 18, 2)->default(0);
            $table->decimal('discrepancy_threshold_amount', 18, 2)->default(50000);
            $table->timestamp('closing_submitted_at')->nullable();
            $table->foreignId('closing_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('discrepancy_reason')->nullable();
            $table->text('handover_notes')->nullable();
            $table->text('approval_notes')->nullable();
        });

        Schema::create('shift_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 80)->index();
            $table->string('payment_method', 40)->default('cash')->index();
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamp('spent_at')->nullable()->index();
            $table->timestamps();

            $table->index(['cash_shift_id', 'category']);
        });

        Schema::create('cash_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_shift_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('denomination');
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['cash_shift_id', 'denomination']);
        });

        Schema::create('shift_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40)->index();
            $table->text('notes')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_approvals');
        Schema::dropIfExists('cash_counts');
        Schema::dropIfExists('shift_expenses');
        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closing_submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'terminal_code',
                'cash_sales_amount',
                'non_cash_sales_amount',
                'refund_amount',
                'expense_amount',
                'receivable_amount',
                'difference_amount',
                'discrepancy_threshold_amount',
                'closing_submitted_at',
                'approved_at',
                'rejected_at',
                'discrepancy_reason',
                'handover_notes',
                'approval_notes',
            ]);
        });
    }
};
