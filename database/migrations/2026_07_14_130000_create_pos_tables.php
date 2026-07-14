<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('open')->index();
            $table->decimal('opening_cash_amount', 18, 2)->default(0);
            $table->decimal('expected_cash_amount', 18, 2)->default(0);
            $table->decimal('actual_cash_amount', 18, 2)->nullable();
            $table->timestamp('opened_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'cashier_user_id', 'status']);
        });

        Schema::create('pos_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 40)->default('completed')->index();
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('grand_total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('change_amount', 18, 2)->default(0);
            $table->decimal('total_margin_amount', 18, 2)->default(0);
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('completed_at')->nullable()->index();
            $table->foreignId('void_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('void_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedInteger('receipt_print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'cashier_user_id', 'completed_at']);
        });

        Schema::create('pos_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku_snapshot', 80);
            $table->string('product_name_snapshot');
            $table->string('unit_name_snapshot', 100);
            $table->decimal('conversion_factor_snapshot', 18, 6);
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('hpp_snapshot', 18, 2)->default(0);
            $table->decimal('minimum_price_snapshot', 18, 2)->default(0);
            $table->decimal('selected_price', 18, 2);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->decimal('margin_amount', 18, 2)->default(0);
            $table->string('price_source', 80)->nullable();
            $table->json('price_snapshot')->nullable();
            $table->decimal('returned_quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['pos_sale_id', 'product_id']);
        });

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->string('method', 40)->index();
            $table->decimal('amount', 18, 2);
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_holds', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 40)->default('held')->index();
            $table->json('cart_snapshot');
            $table->decimal('estimated_total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'cashier_user_id', 'status']);
        });

        Schema::create('pos_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('pos_sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 40)->default('completed')->index();
            $table->string('resolution', 40)->default('refund');
            $table->string('refund_method', 40)->nullable();
            $table->decimal('refund_amount', 18, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('condition', 40)->default('good');
            $table->decimal('refund_amount', 18, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_return_items');
        Schema::dropIfExists('pos_returns');
        Schema::dropIfExists('pos_holds');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('cash_shifts');
    }
};
