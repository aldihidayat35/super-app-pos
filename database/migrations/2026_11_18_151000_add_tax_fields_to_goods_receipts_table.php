<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->string('tax_invoice_number', 120)->nullable()->after('delivery_note_number')->index();
            $table->decimal('tax_rate', 8, 4)->default(0)->after('actual_additional_cost');
            $table->decimal('tax_amount', 18, 2)->default(0)->after('tax_rate');
            $table->boolean('is_tax_included')->default(false)->after('tax_amount');
            $table->boolean('is_tax_creditable')->default(true)->after('is_tax_included');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropColumn([
                'tax_invoice_number',
                'tax_rate',
                'tax_amount',
                'is_tax_included',
                'is_tax_creditable',
            ]);
        });
    }
};
