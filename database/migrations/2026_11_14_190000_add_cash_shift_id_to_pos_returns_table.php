<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pos_returns', function (Blueprint $table) {
            $table->foreignId('cash_shift_id')->nullable()->after('cashier_user_id')->constrained('cash_shifts')->nullOnDelete();
            $table->index(['cash_shift_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_returns', function (Blueprint $table) {
            $table->dropForeign(['cash_shift_id']);
            $table->dropIndex(['cash_shift_id', 'status']);
            $table->dropColumn('cash_shift_id');
        });
    }
};
