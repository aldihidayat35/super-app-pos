<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->softDeletes()->index();
        });

        Schema::table('customer_price_overrides', function (Blueprint $table): void {
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('customer_price_overrides', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
