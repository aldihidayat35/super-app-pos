<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_location_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_until')->nullable()->index();
            $table->text('reason')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_access_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('stock_adjustment_access_id');
            $table->foreignId('work_location_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->boolean('is_active');
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_until')->nullable();
            $table->text('reason');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->foreign('stock_adjustment_access_id', 'saah_access_fk')->references('id')->on('stock_adjustment_accesses')->cascadeOnDelete();
        });

        Schema::create('stock_direct_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['work_location_id', 'status']);
        });

        Schema::create('stock_direct_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('stock_direct_adjustment_id');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_sku_snapshot');
            $table->string('product_name_snapshot');
            $table->decimal('system_qty_snapshot', 18, 4);
            $table->decimal('physical_qty', 18, 4);
            $table->decimal('difference_qty', 18, 4);
            $table->decimal('reserved_qty_snapshot', 18, 4)->default(0);
            $table->decimal('damaged_qty_snapshot', 18, 4)->default(0);
            $table->decimal('unit_cost_snapshot', 18, 2)->default(0);
            $table->decimal('difference_value', 18, 2)->default(0);
            $table->decimal('posted_qty_before', 18, 4)->nullable();
            $table->decimal('posted_qty_after', 18, 4)->nullable();
            $table->text('note');
            $table->timestamps();
            $table->unique(['stock_direct_adjustment_id', 'product_id', 'warehouse_location_id'], 'stock_direct_adjustment_item_unique');
            $table->foreign('stock_direct_adjustment_id', 'sdai_adjustment_fk')->references('id')->on('stock_direct_adjustments')->cascadeOnDelete();
        });

        Schema::create('stock_direct_adjustment_approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('stock_direct_adjustment_id');
            $table->foreignId('approver_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('notes');
            $table->timestamp('decided_at')->index();
            $table->timestamps();
            $table->foreign('stock_direct_adjustment_id', 'sdaa_adjustment_fk')->references('id')->on('stock_direct_adjustments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_direct_adjustment_approvals');
        Schema::dropIfExists('stock_direct_adjustment_items');
        Schema::dropIfExists('stock_direct_adjustments');
        Schema::dropIfExists('stock_adjustment_access_histories');
        Schema::dropIfExists('stock_adjustment_accesses');
    }
};
