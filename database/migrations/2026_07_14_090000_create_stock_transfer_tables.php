<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->date('needed_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['source_warehouse_id', 'status']);
        });

        Schema::create('restock_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restock_request_id')->constrained('restock_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_requested', 18, 4);
            $table->decimal('quantity_approved', 18, 4)->default(0);
            $table->string('priority', 20)->default('normal');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['restock_request_id', 'product_id'], 'restock_request_product_unique');
            $table->index(['product_id', 'priority']);
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('restock_request_id')->nullable()->constrained('restock_requests')->nullOnDelete();
            $table->foreignId('source_work_location_id')->constrained('work_locations')->restrictOnDelete();
            $table->foreignId('source_warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('destination_work_location_id')->constrained('work_locations')->restrictOnDelete();
            $table->foreignId('destination_warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('picker_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipper_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('receiver_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->date('transfer_date')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('packing_started_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('carrier')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('tracking_number')->nullable();
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['source_work_location_id', 'status']);
            $table->index(['destination_work_location_id', 'status']);
            $table->index(['transfer_date', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('restock_request_item_id')->nullable()->constrained('restock_request_items')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('source_warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('destination_warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('product_sku_snapshot');
            $table->string('product_name_snapshot');
            $table->string('unit_name_snapshot')->nullable();
            $table->decimal('conversion_factor_snapshot', 18, 6)->default(1);
            $table->decimal('quantity_requested', 18, 4);
            $table->decimal('quantity_approved', 18, 4)->default(0);
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->decimal('quantity_picked', 18, 4)->default(0);
            $table->decimal('quantity_short', 18, 4)->default(0);
            $table->decimal('quantity_shipped', 18, 4)->default(0);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('quantity_damaged', 18, 4)->default(0);
            $table->decimal('quantity_discrepancy', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'stock_transfer_id']);
            $table->index(['source_warehouse_location_id', 'destination_warehouse_location_id'], 'stock_transfer_item_location_index');
        });

        Schema::create('stock_transfer_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->string('package_no');
            $table->foreignId('checker_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'package_no']);
        });

        Schema::create('stock_transfer_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['stock_transfer_id', 'received_at']);
        });

        Schema::create('stock_transfer_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_receipt_id')->constrained('stock_transfer_receipts')->cascadeOnDelete();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->cascadeOnDelete();
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('quantity_damaged', 18, 4)->default(0);
            $table->decimal('quantity_discrepancy', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_receipt_items');
        Schema::dropIfExists('stock_transfer_receipts');
        Schema::dropIfExists('stock_transfer_packages');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('restock_request_items');
        Schema::dropIfExists('restock_requests');
    }
};
