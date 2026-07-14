<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->string('delivery_method', 40)->default('courier')->after('requested_delivery_date')->index();
            $table->string('courier_name', 120)->nullable()->after('delivery_method');
            $table->string('payment_preference', 40)->default('credit')->after('courier_name')->index();
            $table->boolean('terms_accepted')->default(false)->after('payment_preference');
            $table->decimal('shipping_cost_amount', 18, 2)->default(0)->after('tax_amount');
            $table->timestamp('reservation_expires_at')->nullable()->after('cancelled_at')->index();
            $table->timestamp('packed_at')->nullable()->after('reservation_expires_at');
            $table->timestamp('shipped_at')->nullable()->after('packed_at');
            $table->timestamp('received_at')->nullable()->after('shipped_at');
            $table->timestamp('completed_at')->nullable()->after('received_at');
            $table->timestamp('rejected_at')->nullable()->after('completed_at');
            $table->text('cancel_reason')->nullable()->after('rejected_at');
            $table->text('reject_reason')->nullable()->after('cancel_reason');
            $table->text('internal_note')->nullable()->after('reject_reason');
        });

        Schema::table('b2b_order_items', function (Blueprint $table): void {
            $table->decimal('approved_quantity', 18, 4)->nullable()->after('quantity');
            $table->decimal('reserved_quantity', 18, 4)->default(0)->after('base_quantity');
            $table->decimal('issued_quantity', 18, 4)->default(0)->after('reserved_quantity');
            $table->decimal('shortage_quantity', 18, 4)->default(0)->after('issued_quantity');
            $table->string('fulfillment_status', 40)->default('pending')->after('shortage_quantity')->index();
        });

        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained('b2b_orders')->cascadeOnDelete();
            $table->foreignId('b2b_order_item_id')->constrained('b2b_order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('quantity_reserved', 18, 4);
            $table->decimal('quantity_released', 18, 4)->default(0);
            $table->decimal('quantity_issued', 18, 4)->default(0);
            $table->string('status', 40)->default('active')->index();
            $table->timestamp('reserved_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status', 'expires_at']);
            $table->index(['b2b_order_id', 'status']);
        });

        Schema::create('b2b_order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained('b2b_orders')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['b2b_order_id', 'created_at']);
        });

        Schema::create('b2b_order_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained('b2b_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visibility', 30)->default('internal')->index();
            $table->text('message');
            $table->timestamps();
        });

        DB::table('b2b_orders')->where('status', 'submitted')->update(['status' => 'pending_confirmation']);
    }

    public function down(): void
    {
        DB::table('b2b_orders')->where('status', 'pending_confirmation')->update(['status' => 'submitted']);

        Schema::dropIfExists('b2b_order_messages');
        Schema::dropIfExists('b2b_order_status_histories');
        Schema::dropIfExists('stock_reservations');

        Schema::table('b2b_order_items', function (Blueprint $table): void {
            $table->dropColumn(['approved_quantity', 'reserved_quantity', 'issued_quantity', 'shortage_quantity', 'fulfillment_status']);
        });

        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_method',
                'courier_name',
                'payment_preference',
                'terms_accepted',
                'shipping_cost_amount',
                'reservation_expires_at',
                'packed_at',
                'shipped_at',
                'received_at',
                'completed_at',
                'rejected_at',
                'cancel_reason',
                'reject_reason',
                'internal_note',
            ]);
        });
    }
};
