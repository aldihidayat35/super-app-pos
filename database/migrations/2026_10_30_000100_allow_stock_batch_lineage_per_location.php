<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            // Nomor batch pemasok boleh muncul di beberapa lokasi setelah transfer.
            // Identitas lapisan adalah id + lineage, bukan nomor batch global.
            $table->dropUnique(['product_id', 'batch_no']);
            $table->index(
                ['product_id', 'batch_no', 'work_location_id', 'warehouse_location_id'],
                'stock_batches_batch_location_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropIndex('stock_batches_batch_location_idx');
            $table->unique(['product_id', 'batch_no']);
        });
    }
};
