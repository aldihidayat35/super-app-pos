<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_purchase_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('approval_above_amount', 18, 2)->nullable();
            $table->string('required_role', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('emergency_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('confirmation_key')->nullable()->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('purchased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pos_sale_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('shift_expense_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 40)->index();
            $table->string('supplier_name')->nullable();
            $table->string('fund_source', 40)->nullable();
            $table->string('receipt_path')->nullable();
            $table->decimal('supplier_refund_amount', 18, 2)->default(0);
            $table->string('supplier_return_reference')->nullable();
            $table->string('supplier_refund_recipient', 30)->nullable();
            $table->string('supplier_refund_method', 30)->nullable();
            $table->foreignId('supplier_refund_cash_shift_id')->nullable()->constrained('cash_shifts')->nullOnDelete();
            $table->timestamp('supplier_returned_at')->nullable();
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->timestamp('purchased_at')->nullable()->index();
            $table->string('reimbursement_status', 40)->default('none');
            $table->decimal('reimbursement_amount', 18, 2)->nullable();
            $table->string('reimbursement_reference')->nullable();
            $table->string('reimbursement_proof_path')->nullable();
            $table->timestamp('reimbursed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('emergency_purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('emergency_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('requested_quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('available_at_request', 18, 4);
            $table->decimal('shortage_at_request', 18, 4);
            $table->decimal('purchased_quantity', 18, 4)->default(0);
            $table->decimal('allocated_quantity', 18, 4)->default(0);
            $table->foreignId('assigned_source_item_id')->nullable()->constrained('emergency_purchase_items')->nullOnDelete();
            $table->decimal('assigned_quantity', 18, 4)->default(0);
            $table->decimal('supplier_returned_quantity', 18, 4)->default(0);
            $table->decimal('warehouse_quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('expected_sale_price', 18, 2)->default(0);
            $table->timestamps();
            $table->index(['product_id', 'emergency_purchase_id']);
        });

        Schema::create('retail_stockout_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('emergency_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->decimal('requested_quantity', 18, 4);
            $table->decimal('available_quantity', 18, 4);
            $table->decimal('shortage_quantity', 18, 4);
            $table->string('confirmation_key')->unique();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('emergency_purchase_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('emergency_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_sale_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emergency_purchase_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source', 20);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('returned_quantity', 18, 4)->default(0);
            $table->decimal('normal_hpp_unit', 18, 2);
            $table->decimal('actual_cost_unit', 18, 2);
            $table->decimal('revenue_amount', 18, 2);
            $table->decimal('normal_cogs_amount', 18, 2);
            $table->decimal('actual_cogs_amount', 18, 2);
            $table->decimal('actual_margin_amount', 18, 2);
            $table->decimal('lost_margin_amount', 18, 2);
            $table->timestamps();
            $table->index(['pos_sale_item_id', 'source']);
        });

        Schema::table('pos_return_items', function (Blueprint $table): void {
            $table->decimal('normal_quantity', 18, 4)->nullable();
            $table->decimal('emergency_quantity', 18, 4)->nullable();
            $table->decimal('reversed_cogs_amount', 18, 2)->nullable();
            $table->decimal('reversed_margin_amount', 18, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pos_return_items', function (Blueprint $table): void {
            $table->dropColumn(['normal_quantity', 'emergency_quantity', 'reversed_cogs_amount', 'reversed_margin_amount']);
        });
        Schema::dropIfExists('pos_sale_allocations');
        Schema::dropIfExists('emergency_purchase_histories');
        Schema::dropIfExists('retail_stockout_events');
        Schema::dropIfExists('emergency_purchase_items');
        Schema::dropIfExists('emergency_purchases');
        Schema::dropIfExists('emergency_purchase_rules');
    }
};
