<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('destination_work_location_id')->nullable()->after('warehouse_id')->constrained('work_locations')->restrictOnDelete();
        });
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('destination_work_location_id')->nullable()->after('warehouse_id')->constrained('work_locations')->restrictOnDelete();
        });

        DB::table('purchase_orders')->whereNull('destination_work_location_id')->update([
            'destination_work_location_id' => DB::raw('(SELECT work_location_id FROM warehouses WHERE warehouses.id = purchase_orders.warehouse_id)'),
        ]);
        DB::table('goods_receipts')->whereNull('destination_work_location_id')->update([
            'destination_work_location_id' => DB::raw('(SELECT work_location_id FROM warehouses WHERE warehouses.id = goods_receipts.warehouse_id)'),
        ]);

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->change();
        });
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $fallbackWarehouse = DB::table('warehouses')->orderBy('id')->value('id');
        if ($fallbackWarehouse !== null) {
            DB::table('purchase_orders')->whereNull('warehouse_id')->update(['warehouse_id' => $fallbackWarehouse]);
            DB::table('goods_receipts')->whereNull('warehouse_id')->update(['warehouse_id' => $fallbackWarehouse]);
        }

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('destination_work_location_id');
            $table->foreignId('warehouse_id')->nullable(false)->change();
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('destination_work_location_id');
            $table->foreignId('warehouse_id')->nullable(false)->change();
        });
    }
};
