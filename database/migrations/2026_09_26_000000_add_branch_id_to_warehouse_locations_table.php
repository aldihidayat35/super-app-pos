<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_locations', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->change();
            $table->foreignId('branch_id')->nullable()->after('warehouse_id')->constrained('branches')->cascadeOnDelete();
            $table->index(['branch_id', 'parent_id', 'type']);
            $table->index(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_locations', function (Blueprint $table): void {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id', 'parent_id', 'type']);
            $table->dropIndex(['branch_id', 'code']);
            $table->dropColumn('branch_id');
            $table->foreignId('warehouse_id')->nullable(false)->change();
        });
    }
};
