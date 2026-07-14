<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_limits', function (Blueprint $table): void {
            $table->string('status', 40)->default('active')->after('current_balance')->index();
            $table->decimal('approval_threshold_amount', 18, 2)->default(0)->after('payment_term_days');
            $table->unsignedSmallInteger('max_overdue_days')->default(0)->after('approval_threshold_amount');
            $table->timestamp('blocked_at')->nullable()->after('effective_from');
            $table->foreignId('blocked_by')->nullable()->after('blocked_at')->constrained('users')->nullOnDelete();
            $table->text('blocked_reason')->nullable()->after('blocked_by');
        });

        Schema::create('receivables', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 80)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 50)->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('source_no', 120)->nullable();
            $table->string('channel', 40)->default('warehouse')->index();
            $table->date('issue_date')->index();
            $table->date('due_date')->index();
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('adjustment_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0);
            $table->string('aging_bucket', 40)->default('not_due')->index();
            $table->string('status', 40)->default('open')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'due_date']);
            $table->index(['channel', 'aging_bucket', 'status']);
        });

        Schema::create('receivable_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receivable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 40)->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_before', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->string('source_type', 50)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_no', 120)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['customer_id', 'occurred_at']);
        });

        Schema::create('receivable_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 80)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 40)->index();
            $table->decimal('amount', 18, 2);
            $table->date('payment_date')->index();
            $table->string('reference_no', 120)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('receivable_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receivable_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receivable_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['receivable_payment_id', 'receivable_id']);
        });

        Schema::create('collection_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receivable_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 40)->default('manual')->index();
            $table->string('contact_person', 120)->nullable();
            $table->text('note');
            $table->date('next_follow_up_date')->nullable()->index();
            $table->string('delivery_status', 40)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 80)->unique();
            $table->foreignId('receivable_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('type', 40)->default('credit_note')->index();
            $table->decimal('amount', 18, 2);
            $table->string('status', 40)->default('pending')->index();
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('collection_notes');
        Schema::dropIfExists('receivable_payment_allocations');
        Schema::dropIfExists('receivable_payments');
        Schema::dropIfExists('receivable_entries');
        Schema::dropIfExists('receivables');

        Schema::table('credit_limits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('blocked_by');
            $table->dropColumn(['status', 'approval_threshold_amount', 'max_overdue_days', 'blocked_at', 'blocked_reason']);
        });
    }
};
