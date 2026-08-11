<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_cost_histories', function (Blueprint $table): void {
            $table->unsignedBigInteger('goods_receipt_id')->nullable()->change();
            $table->unsignedBigInteger('goods_receipt_item_id')->nullable()->change();
            $table->string('source_type', 40)->default('goods_receipt')->after('method')->index();
            $table->string('source_reference', 120)->nullable()->after('source_type');
            $table->foreignId('changed_by')->nullable()->after('source_reference')->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable()->after('changed_by');
        });
    }

    public function down(): void
    {
        DB::table('product_cost_histories')->where('source_type', 'opening_stock')->delete();

        Schema::table('product_cost_histories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('changed_by');
            $table->dropColumn(['source_type', 'source_reference', 'reason']);
            $table->unsignedBigInteger('goods_receipt_id')->nullable(false)->change();
            $table->unsignedBigInteger('goods_receipt_item_id')->nullable(false)->change();
        });
    }
};
