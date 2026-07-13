<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('type', 30)->default('zone')->index();
            $table->string('code', 60);
            $table->string('full_code', 160)->unique();
            $table->string('name');
            $table->decimal('capacity', 18, 4)->nullable();
            $table->string('item_type')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'code']);
            $table->index(['warehouse_id', 'parent_id', 'type']);
        });

        Schema::create('stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('location_scope_key', 120);
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->decimal('quantity_damaged', 18, 4)->default(0);
            $table->decimal('cost_value', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'location_scope_key']);
            $table->index(['work_location_id', 'warehouse_location_id']);
        });

        Schema::create('stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_no', 100);
            $table->date('received_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();

            $table->unique(['product_id', 'batch_no']);
            $table->index(['product_id', 'expires_at', 'received_at']);
        });

        Schema::create('inventory_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('operation', 80);
            $table->json('response')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_mutations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('mutation_type', 40)->index();
            $table->string('direction', 20)->index();
            $table->decimal('quantity_on_hand_before', 18, 4);
            $table->decimal('quantity_on_hand_change', 18, 4);
            $table->decimal('quantity_on_hand_after', 18, 4);
            $table->decimal('quantity_reserved_before', 18, 4);
            $table->decimal('quantity_reserved_change', 18, 4);
            $table->decimal('quantity_reserved_after', 18, 4);
            $table->decimal('quantity_damaged_before', 18, 4);
            $table->decimal('quantity_damaged_change', 18, 4);
            $table->decimal('quantity_damaged_after', 18, 4);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('reference_type')->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no')->nullable()->index();
            $table->foreignId('source_work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->foreignId('source_warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('destination_work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->foreignId('destination_warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('idempotency_key')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['product_id', 'occurred_at']);
            $table->index(['work_location_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
        Schema::dropIfExists('inventory_idempotency_keys');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('warehouse_locations');
    }
};
