<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'user_id', 'status']);
        });

        Schema::create('b2b_cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_cart_id')->constrained('b2b_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('price_snapshot', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->string('price_source', 80)->nullable();
            $table->string('availability_snapshot', 40)->default('unknown');
            $table->json('price_metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['b2b_cart_id', 'product_id', 'unit_id']);
            $table->index(['product_id', 'unit_id']);
        });

        Schema::create('b2b_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 80)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->string('status', 40)->default('submitted')->index();
            $table->date('requested_delivery_date')->nullable();
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('grand_total_amount', 18, 2)->default(0);
            $table->decimal('credit_limit_snapshot', 18, 2)->default(0);
            $table->decimal('receivable_balance_snapshot', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'submitted_at']);
            $table->index(['requested_by', 'submitted_at']);
        });

        Schema::create('b2b_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained('b2b_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('sku_snapshot', 100);
            $table->string('product_name_snapshot');
            $table->string('unit_name_snapshot', 100);
            $table->decimal('conversion_factor_snapshot', 18, 6);
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('minimum_price_snapshot', 18, 2)->default(0);
            $table->decimal('selected_price', 18, 2);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->string('price_source', 80)->nullable();
            $table->decimal('available_stock_snapshot', 18, 4)->default(0);
            $table->json('price_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'unit_id']);
        });

        Schema::create('b2b_favorite_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_favorite_products');
        Schema::dropIfExists('b2b_order_items');
        Schema::dropIfExists('b2b_orders');
        Schema::dropIfExists('b2b_cart_items');
        Schema::dropIfExists('b2b_carts');
    }
};
