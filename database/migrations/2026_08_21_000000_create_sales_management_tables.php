<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('sales_user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['sales_user_id', 'is_active']);
        });

        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->foreignId('sales_user_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['sales_user_id', 'status', 'completed_at']);
        });

        Schema::create('sales_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('target_amount', 18, 2);
            $table->decimal('bonus_percentage', 7, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sales_user_id', 'month', 'year']);
            $table->index(['year', 'month']);
        });

        Schema::create('sales_bonuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_target_id')->unique()->constrained('sales_targets')->cascadeOnDelete();
            $table->foreignId('sales_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('sales_amount', 18, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->decimal('target_amount', 18, 2);
            $table->decimal('bonus_percentage', 7, 4)->default(0);
            $table->decimal('bonus_amount', 18, 2)->default(0);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sales_user_id', 'month', 'year']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_bonuses');
        Schema::dropIfExists('sales_targets');

        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->dropForeign(['sales_user_id']);
            $table->dropIndex(['sales_user_id', 'status', 'completed_at']);
            $table->dropColumn('sales_user_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['sales_user_id']);
            $table->dropIndex(['sales_user_id', 'is_active']);
            $table->dropColumn('sales_user_id');
        });
    }
};
