<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 80)->unique();
            $table->string('source_type', 40)->default('b2b_order')->index();
            $table->foreignId('b2b_order_id')->nullable()->constrained('b2b_orders')->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->date('issue_date')->nullable()->index();
            $table->date('due_date')->nullable()->index();
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('shipping_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'due_date']);
            $table->index(['b2b_order_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('b2b_order_item_id')->nullable()->constrained('b2b_order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('unit_name_snapshot', 100)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 80)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('method', 40)->index();
            $table->string('status', 40)->default('pending_verification')->index();
            $table->decimal('amount', 18, 2);
            $table->date('payment_date')->index();
            $table->string('bank_name', 120)->nullable();
            $table->string('reference_no', 120)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('payer_name', 120)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'payment_date']);
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 80)->unique();
            $table->foreignId('b2b_order_id')->constrained('b2b_orders')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->foreignId('destination_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->string('status', 40)->default('packing')->index();
            $table->string('delivery_method', 40)->default('courier')->index();
            $table->string('courier_name', 120)->nullable();
            $table->string('driver_name', 120)->nullable();
            $table->string('vehicle_no', 80)->nullable();
            $table->string('tracking_no', 120)->nullable();
            $table->date('scheduled_date')->nullable()->index();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->decimal('shipping_cost_amount', 18, 2)->default(0);
            $table->string('receiver_name', 120)->nullable();
            $table->text('delivery_note')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['b2b_order_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('b2b_order_item_id')->constrained('b2b_order_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_planned', 18, 4);
            $table->decimal('quantity_shipped', 18, 4)->default(0);
            $table->decimal('quantity_delivered', 18, 4)->default(0);
            $table->decimal('quantity_failed', 18, 4)->default(0);
            $table->string('status', 40)->default('planned')->index();
            $table->timestamps();
        });

        Schema::create('shipment_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('file_path')->nullable();
            $table->string('receiver_name', 120)->nullable();
            $table->text('signature_data')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('b2b_complaints', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 80)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('b2b_order_id')->nullable()->constrained('b2b_orders')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('b2b_order_item_id')->nullable()->constrained('b2b_order_items')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('requested_solution', 80)->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('status', 40)->default('submitted')->index();
            $table->string('evidence_path')->nullable();
            $table->text('message');
            $table->text('resolution_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_complaints');
        Schema::dropIfExists('shipment_proofs');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
