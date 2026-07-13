<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('work_location_id')->constrained('work_locations')->restrictOnDelete();
            $table->string('source_type', 60)->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_name')->nullable();
            $table->string('destination_type', 60)->nullable()->index();
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->string('destination_name')->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no')->nullable()->index();
            $table->string('reason', 80)->index();
            $table->string('requested_resolution', 80)->index();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('checker_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('return_date')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->decimal('total_quantity', 18, 4)->default(0);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->decimal('total_loss_value', 18, 2)->default(0);
            $table->boolean('requires_approval')->default(false);
            $table->string('evidence_path')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['work_location_id', 'status', 'return_date']);
        });

        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('source_item_type', 80)->nullable();
            $table->unsignedBigInteger('source_item_id')->nullable();
            $table->string('product_sku_snapshot');
            $table->string('product_name_snapshot');
            $table->string('unit_name_snapshot')->nullable();
            $table->decimal('conversion_factor_snapshot', 18, 6)->default(1);
            $table->decimal('source_quantity', 18, 4)->default(0);
            $table->decimal('quantity_requested', 18, 4);
            $table->decimal('quantity_accepted_good', 18, 4)->default(0);
            $table->decimal('quantity_accepted_damaged', 18, 4)->default(0);
            $table->decimal('quantity_rejected', 18, 4)->default(0);
            $table->decimal('unit_cost_snapshot', 18, 2)->default(0);
            $table->decimal('line_value', 18, 2)->default(0);
            $table->decimal('loss_value', 18, 2)->default(0);
            $table->string('condition', 80)->default('good');
            $table->string('reason', 80)->nullable();
            $table->string('resolution', 80)->nullable();
            $table->text('notes')->nullable();
            $table->string('evidence_path')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'condition']);
            $table->index(['source_item_type', 'source_item_id']);
        });

        Schema::create('return_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('return_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checker_user_id')->constrained('users')->restrictOnDelete();
            $table->string('qc_result', 40)->index();
            $table->string('condition', 80)->index();
            $table->decimal('quantity_good', 18, 4)->default(0);
            $table->decimal('quantity_damaged', 18, 4)->default(0);
            $table->decimal('quantity_rejected', 18, 4)->default(0);
            $table->decimal('loss_value', 18, 2)->default(0);
            $table->string('responsible_party', 80)->nullable();
            $table->string('evidence_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('inspected_at');
            $table->timestamps();

            $table->index(['return_id', 'qc_result']);
        });

        Schema::create('return_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('settled_by')->constrained('users')->restrictOnDelete();
            $table->string('resolution', 80)->index();
            $table->string('document_no')->nullable()->index();
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at');
            $table->timestamps();
        });

        Schema::create('inventory_losses', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('work_location_id')->constrained('work_locations')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('loss_type', 80)->index();
            $table->string('disposition', 40)->default('damage')->index();
            $table->string('status', 40)->default('draft')->index();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost_snapshot', 18, 2)->default(0);
            $table->decimal('loss_value', 18, 2)->default(0);
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('evidence_path')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('reported_at')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['work_location_id', 'status', 'reported_at']);
            $table->index(['product_id', 'loss_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_losses');
        Schema::dropIfExists('return_settlements');
        Schema::dropIfExists('return_inspections');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
