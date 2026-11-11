<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->change();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->foreignId('purchase_order_item_id')->nullable()->change();
            $table->decimal('quantity_ordered', 18, 4)->default(0)->change();
            $table->decimal('previously_received', 18, 4)->default(0)->change();
            $table->decimal('outstanding_before', 18, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->foreignId('purchase_order_item_id')->nullable(false)->change();
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable(false)->change();
        });
    }
};
