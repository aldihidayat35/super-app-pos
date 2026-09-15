<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_purchases', function (Blueprint $table): void {
            $table->foreignId('regularized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('regularized_at')->nullable();
        });
        Schema::table('emergency_purchase_items', function (Blueprint $table): void {
            $table->decimal('regularized_quantity', 18, 4)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('emergency_purchase_items', fn (Blueprint $table) => $table->dropColumn('regularized_quantity'));
        Schema::table('emergency_purchases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('regularized_by');
            $table->dropColumn('regularized_at');
        });
    }
};
