<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_purchases', function (Blueprint $table): void {
            $table->string('purpose', 30)->default('customer_request')->after('confirmation_key')->index();
            $table->index(['branch_id', 'purpose', 'status'], 'emergency_purchases_branch_purpose_status_idx');
        });

        Schema::table('retail_stockout_events', function (Blueprint $table): void {
            $table->decimal('emergency_available_quantity', 18, 4)->default(0)->after('available_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('retail_stockout_events', function (Blueprint $table): void {
            $table->dropColumn('emergency_available_quantity');
        });

        Schema::table('emergency_purchases', function (Blueprint $table): void {
            $table->dropIndex('emergency_purchases_branch_purpose_status_idx');
            $table->dropIndex(['purpose']);
            $table->dropColumn('purpose');
        });
    }
};
