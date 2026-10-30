<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->foreignId('source_batch_id')->nullable()->after('id')->constrained('stock_batches')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->after('stock_id')->constrained()->nullOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->after('work_location_id')->constrained()->nullOnDelete();
            $table->string('source_type', 80)->nullable()->after('goods_receipt_item_id')->index();
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->unsignedBigInteger('source_item_id')->nullable()->after('source_id');
            $table->foreignId('unit_id')->nullable()->after('source_item_id')->constrained()->nullOnDelete();
            $table->decimal('conversion_factor_snapshot', 18, 6)->default(1)->after('unit_id');
            $table->decimal('entry_sale_price_snapshot', 18, 2)->nullable()->after('cost_price');
            $table->decimal('quantity_damaged', 18, 4)->default(0)->after('quantity_reserved');
            $table->index(['work_location_id', 'product_id', 'status'], 'stock_batches_location_product_status_idx');
            $table->index(['source_type', 'source_id', 'source_item_id'], 'stock_batches_source_idx');
        });

        Schema::create('stock_layer_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_type', 120)->index();
            $table->unsignedBigInteger('reference_id')->index();
            $table->string('event_type', 40)->index();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('sale_price_snapshot', 18, 2)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id', 'event_type'], 'stock_layer_allocations_reference_idx');
            $table->index(['stock_batch_id', 'event_type', 'occurred_at'], 'stock_layer_allocations_batch_idx');
        });

        Schema::table('product_prices', function (Blueprint $table): void {
            $table->foreignId('work_location_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->index(['product_id', 'work_location_id', 'channel', 'status'], 'product_prices_location_lookup_idx');
        });

        Schema::table('price_histories', function (Blueprint $table): void {
            $table->foreignId('work_location_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });

        Schema::table('stock_transfer_items', function (Blueprint $table): void {
            $table->decimal('source_sale_price_snapshot', 18, 2)->nullable()->after('conversion_factor_snapshot');
            $table->decimal('destination_sale_price', 18, 2)->nullable()->after('source_sale_price_snapshot');
            $table->json('selected_stock_batch_ids')->nullable()->after('destination_sale_price');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->decimal('actual_unit_cost', 18, 2)->nullable()->after('unit_price');
            $table->decimal('sale_price', 18, 2)->nullable()->after('actual_unit_cost');
        });

        Schema::table('stock_direct_adjustment_items', function (Blueprint $table): void {
            $table->decimal('sale_price_snapshot', 18, 2)->nullable()->after('unit_cost_snapshot');
            $table->json('selected_stock_batch_ids')->nullable()->after('sale_price_snapshot');
        });

        // Harga cabang lama menjadi harga lokasi tanpa mengubah perilaku fallback global.
        if (Schema::hasColumn('branches', 'work_location_id')) {
            DB::table('branches')
                ->whereNotNull('work_location_id')
                ->select(['id', 'work_location_id'])
                ->get()
                ->each(function ($branch): void {
                    DB::table('product_prices')
                        ->where('branch_id', $branch->id)
                        ->whereNull('work_location_id')
                        ->update(['work_location_id' => $branch->work_location_id]);
                });
        }

        // Data lama tidak memiliki provenance per batch. Satu layer legacy per saldo/rak
        // adalah satu-satunya migrasi yang tidak mengarang histori pembelian.
        $now = now();
        DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->where('stocks.quantity_on_hand', '>', 0)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('stock_batches')->whereColumn('stock_batches.stock_id', 'stocks.id');
            })
            ->select([
                'stocks.id as stock_id', 'stocks.product_id', 'stocks.work_location_id',
                'stocks.warehouse_location_id', 'stocks.quantity_on_hand', 'stocks.quantity_reserved',
                'stocks.quantity_damaged', 'products.base_unit_id', 'products.cost_price',
            ])
            ->orderBy('stocks.id')
            ->chunkById(200, function ($rows) use ($now): void {
                $inserts = [];
                foreach ($rows as $row) {
                    $inserts[] = [
                        'product_id' => $row->product_id,
                        'stock_id' => $row->stock_id,
                        'work_location_id' => $row->work_location_id,
                        'warehouse_location_id' => $row->warehouse_location_id,
                        'source_type' => 'legacy_migration',
                        'source_id' => $row->stock_id,
                        'unit_id' => $row->base_unit_id,
                        'conversion_factor_snapshot' => 1,
                        'batch_no' => 'LEGACY-'.$row->stock_id,
                        'received_at' => $now->toDateString(),
                        'cost_price' => $row->cost_price,
                        'quantity_on_hand' => $row->quantity_on_hand,
                        'quantity_reserved' => $row->quantity_reserved,
                        'quantity_damaged' => $row->quantity_damaged,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('stock_batches')->insert($inserts);
            }, 'stocks.id', 'stock_id');
    }

    public function down(): void
    {
        Schema::table('stock_direct_adjustment_items', fn (Blueprint $table) => $table->dropColumn(['sale_price_snapshot', 'selected_stock_batch_ids']));
        Schema::table('goods_receipt_items', fn (Blueprint $table) => $table->dropColumn(['actual_unit_cost', 'sale_price']));
        Schema::table('stock_transfer_items', function (Blueprint $table): void {
            $table->dropColumn(['source_sale_price_snapshot', 'destination_sale_price', 'selected_stock_batch_ids']);
        });
        Schema::table('price_histories', fn (Blueprint $table) => $table->dropConstrainedForeignId('work_location_id'));
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->dropIndex('product_prices_location_lookup_idx');
            $table->dropConstrainedForeignId('work_location_id');
        });
        Schema::dropIfExists('stock_layer_allocations');
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropIndex('stock_batches_location_product_status_idx');
            $table->dropIndex('stock_batches_source_idx');
            $table->dropConstrainedForeignId('source_batch_id');
            $table->dropConstrainedForeignId('work_location_id');
            $table->dropConstrainedForeignId('warehouse_location_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['source_type', 'source_id', 'source_item_id', 'conversion_factor_snapshot', 'entry_sale_price_snapshot', 'quantity_damaged']);
        });
    }
};
