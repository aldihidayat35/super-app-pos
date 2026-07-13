<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->string('priority', 30)->default('normal')->index();
            $table->string('status', 40)->default('draft')->index();
            $table->text('reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('converted_purchase_order_id')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['requester_user_id', 'created_at']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'purchase_request_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_at')->nullable();
            $table->unsignedInteger('payment_term_days')->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->decimal('items_subtotal', 18, 2)->default(0);
            $table->decimal('header_discount', 18, 2)->default(0);
            $table->decimal('freight_cost', 18, 2)->default(0);
            $table->decimal('additional_cost', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['supplier_id', 'order_date']);
            $table->index(['created_by', 'created_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('product_sku_snapshot');
            $table->string('product_name_snapshot');
            $table->string('unit_name_snapshot');
            $table->decimal('conversion_factor_snapshot', 18, 6)->default(1);
            $table->decimal('quantity_ordered', 18, 4);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['purchase_order_id', 'product_id']);
        });

        Schema::create('document_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 80);
            $table->unsignedBigInteger('document_id');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id', 'created_at']);
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 80);
            $table->unsignedBigInteger('document_id');
            $table->string('status', 40)->default('approved')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
        });

        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 80);
            $table->unsignedBigInteger('document_id');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('document_status_histories');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
