<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('received_at');
            $table->string('delivery_note_number')->nullable()->index();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('actual_freight_cost', 18, 2)->default(0);
            $table->decimal('actual_additional_cost', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['warehouse_id', 'status', 'received_at']);
            $table->index(['supplier_id', 'received_at']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('product_sku_snapshot');
            $table->string('product_name_snapshot');
            $table->string('unit_name_snapshot');
            $table->decimal('conversion_factor_snapshot', 18, 6)->default(1);
            $table->decimal('quantity_ordered', 18, 4);
            $table->decimal('previously_received', 18, 4)->default(0);
            $table->decimal('outstanding_before', 18, 4)->default(0);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('quantity_accepted', 18, 4)->default(0);
            $table->decimal('quantity_rejected', 18, 4)->default(0);
            $table->decimal('quantity_damaged', 18, 4)->default(0);
            $table->decimal('quantity_returned_to_supplier', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('landed_cost_allocated', 18, 2)->default(0);
            $table->string('batch_no')->nullable()->index();
            $table->text('qc_notes')->nullable();
            $table->decimal('hpp_before', 18, 2)->nullable();
            $table->decimal('incoming_cost', 18, 2)->default(0);
            $table->decimal('hpp_after', 18, 2)->nullable();
            $table->timestamps();

            $table->index(['goods_receipt_id', 'product_id']);
        });

        Schema::create('receipt_qc_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_item_id')->constrained()->cascadeOnDelete();
            $table->string('qc_status', 40)->index();
            $table->decimal('quantity', 18, 4);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('product_cost_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->constrained()->restrictOnDelete();
            $table->string('method', 40)->default('moving_average')->index();
            $table->decimal('qty_before', 18, 4)->default(0);
            $table->decimal('qty_incoming', 18, 4)->default(0);
            $table->decimal('qty_after', 18, 4)->default(0);
            $table->decimal('hpp_before', 18, 2)->default(0);
            $table->decimal('incoming_cost', 18, 2)->default(0);
            $table->decimal('landed_cost_allocated', 18, 2)->default(0);
            $table->decimal('hpp_after', 18, 2)->default(0);
            $table->timestamp('effective_at')->index();
            $table->timestamps();

            $table->index(['product_id', 'effective_at']);
        });

        Schema::create('supplier_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('quantity_accepted', 18, 4)->default(0);
            $table->decimal('quantity_rejected', 18, 4)->default(0);
            $table->decimal('quantity_damaged', 18, 4)->default(0);
            $table->decimal('quality_score', 8, 2)->default(0);
            $table->decimal('delivery_score', 8, 2)->default(0);
            $table->decimal('price_score', 8, 2)->default(0);
            $table->decimal('total_score', 8, 2)->default(0);
            $table->date('received_at')->index();
            $table->timestamps();

            $table->index(['supplier_id', 'received_at']);
        });

        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->foreignId('goods_receipt_id')->nullable()->after('stock_id')->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->after('goods_receipt_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('goods_receipt_item_id');
            $table->dropConstrainedForeignId('goods_receipt_id');
        });

        Schema::dropIfExists('supplier_scores');
        Schema::dropIfExists('product_cost_histories');
        Schema::dropIfExists('receipt_qc_results');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
