<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('channel', 40)->default('all')->index();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_category', 60)->nullable()->index();
            $table->string('margin_method', 20)->default('percent');
            $table->decimal('minimum_margin_percent', 8, 2)->default(20);
            $table->decimal('minimum_margin_amount', 18, 2)->default(0);
            $table->decimal('overpricing_tolerance_percent', 8, 2)->default(100);
            $table->decimal('max_discount_percent', 8, 2)->default(20);
            $table->decimal('approval_threshold_amount', 18, 2)->default(1000000);
            $table->unsignedInteger('priority')->default(100)->index();
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['channel', 'branch_id', 'customer_category', 'is_active'], 'price_rules_scope_index');
        });

        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 40)->default('retail')->index();
            $table->string('price_ring', 60)->default('retail')->index();
            $table->string('customer_category', 60)->nullable()->index();
            $table->decimal('min_price', 18, 2)->default(0);
            $table->decimal('recommended_price', 18, 2);
            $table->decimal('max_price', 18, 2)->default(0);
            $table->decimal('minimum_qty', 18, 4)->default(1);
            $table->unsignedInteger('priority')->default(100)->index();
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->string('status', 40)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'channel', 'price_ring', 'status'], 'product_prices_lookup_index');
        });

        Schema::table('customer_price_overrides', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('channel', 40)->default('b2b')->after('branch_id')->index();
            $table->decimal('minimum_qty', 18, 4)->default(1)->after('price');
            $table->decimal('discount_percent', 8, 2)->default(0)->after('minimum_qty');
            $table->unsignedInteger('priority')->default(10)->after('discount_percent')->index();
            $table->string('status', 40)->default('approved')->after('priority')->index();
            $table->text('reason')->nullable()->after('notes');
            $table->foreignId('requested_by')->nullable()->after('reason')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->index(['customer_id', 'product_id', 'channel', 'status'], 'customer_price_overrides_lookup_index');
        });

        Schema::create('price_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('priceable_type', 80);
            $table->unsignedBigInteger('priceable_id');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 40)->nullable()->index();
            $table->string('price_ring', 60)->nullable();
            $table->decimal('old_price', 18, 2)->nullable();
            $table->decimal('new_price', 18, 2);
            $table->decimal('hpp_snapshot', 18, 2)->default(0);
            $table->decimal('minimum_price_snapshot', 18, 2)->default(0);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 80)->default('manual')->index();
            $table->string('bulk_batch_id')->nullable()->index();
            $table->text('reason')->nullable();
            $table->date('effective_at')->nullable()->index();
            $table->timestamps();

            $table->index(['priceable_type', 'priceable_id', 'created_at'], 'price_histories_priceable_index');
        });

        Schema::create('price_approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('approval_type', 80)->index();
            $table->string('document_type', 80)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('pending')->index();
            $table->decimal('requested_price', 18, 2);
            $table->decimal('minimum_price_snapshot', 18, 2)->default(0);
            $table->decimal('maximum_price_snapshot', 18, 2)->default(0);
            $table->decimal('hpp_snapshot', 18, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->text('reason')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_approval_requests');
        Schema::dropIfExists('price_histories');
        Schema::table('customer_price_overrides', function (Blueprint $table): void {
            $table->dropIndex('customer_price_overrides_lookup_index');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['channel', 'minimum_qty', 'discount_percent', 'priority', 'status', 'reason', 'approved_at']);
        });
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('price_rules');
    }
};
