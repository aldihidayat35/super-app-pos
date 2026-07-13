<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('work_location_id')->constrained('work_locations')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->string('method', 20)->default('manual');
            $table->boolean('freeze_stock')->default(false);
            $table->boolean('blind_count')->default(false);
            $table->boolean('requires_owner_approval')->default(false);
            $table->date('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('threshold_qty', 18, 4)->default(10);
            $table->decimal('threshold_value', 18, 2)->default(1000000);
            $table->decimal('total_difference_qty', 18, 4)->default(0);
            $table->decimal('total_difference_value', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['work_location_id', 'status']);
            $table->index(['scheduled_at', 'status']);
        });

        Schema::create('stock_opname_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('counter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('product_sku_snapshot');
            $table->string('product_name_snapshot');
            $table->decimal('system_qty_snapshot', 18, 4)->default(0);
            $table->decimal('counted_qty', 18, 4)->nullable();
            $table->decimal('difference_qty', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('estimated_value', 18, 2)->default(0);
            $table->string('reason', 80)->nullable();
            $table->text('note')->nullable();
            $table->string('evidence_path')->nullable();
            $table->boolean('has_transaction_after_snapshot')->default(false);
            $table->timestamp('counted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'product_id', 'warehouse_location_id'], 'stock_opname_product_location_unique');
            $table->index(['product_id', 'reason']);
        });

        Schema::create('stock_opname_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_opname_item_id')->constrained('stock_opname_items')->cascadeOnDelete();
            $table->foreignId('counter_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('counted_qty', 18, 4);
            $table->string('reason', 80)->nullable();
            $table->text('note')->nullable();
            $table->string('evidence_path')->nullable();
            $table->timestamp('counted_at');
            $table->timestamps();

            $table->index(['stock_opname_item_id', 'counter_user_id']);
        });

        Schema::create('stock_opname_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('stock_opname_item_id')->nullable()->constrained('stock_opname_items')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->restrictOnDelete();
            $table->string('approval_level', 40)->default('warehouse_head');
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->index(['stock_opname_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_approvals');
        Schema::dropIfExists('stock_opname_counts');
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
