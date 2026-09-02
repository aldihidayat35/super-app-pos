<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('tax_type', 30)->index();
            $table->string('direction', 20)->default('both')->index();
            $table->decimal('rate', 7, 4)->default(0);
            $table->decimal('dpp_factor', 12, 8)->default(1);
            $table->decimal('luxury_tax_rate', 7, 4)->default(0);
            $table->boolean('is_creditable')->default(true);
            $table->date('effective_from')->index();
            $table->date('effective_until')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tax_type', 'direction', 'effective_from', 'effective_until'], 'tax_rules_effective_idx');
        });

        Schema::create('tax_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 80)->unique()->default('company');
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->string('legal_name');
            $table->string('tax_number', 32)->nullable();
            $table->string('nitku', 32)->nullable();
            $table->text('tax_address')->nullable();
            $table->boolean('is_pkp')->default(false)->index();
            $table->date('pkp_effective_date')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_tax_number', 32)->nullable();
            $table->boolean('calculation_enabled')->default(false)->index();
            $table->foreignId('default_output_tax_rule_id')->nullable()->constrained('tax_rules')->nullOnDelete();
            $table->foreignId('default_input_tax_rule_id')->nullable()->constrained('tax_rules')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('tax_rule_id')->nullable()->after('base_unit_id')->constrained('tax_rules')->nullOnDelete();
            $table->string('tax_category_code', 60)->nullable()->after('tax_rule_id')->index();
            $table->boolean('is_taxable')->default(false)->after('tax_category_code')->index();
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('tax_number', 32)->nullable()->after('business_name')->index();
            $table->string('tax_identity_type', 20)->nullable()->after('tax_number');
            $table->text('tax_address')->nullable()->after('business_address');
            $table->boolean('is_pkp')->default(false)->after('tax_address')->index();
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('tax_identity_type', 20)->nullable()->after('tax_number');
            $table->text('tax_address')->nullable()->after('address');
            $table->boolean('is_pkp')->default(false)->after('tax_address')->index();
        });

        Schema::create('tax_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('status', 30)->default('open')->index();
            $table->decimal('output_dpp_amount', 18, 2)->default(0);
            $table->decimal('output_tax_amount', 18, 2)->default(0);
            $table->decimal('input_dpp_amount', 18, 2)->default(0);
            $table->decimal('creditable_input_tax_amount', 18, 2)->default(0);
            $table->decimal('non_creditable_input_tax_amount', 18, 2)->default(0);
            $table->decimal('withholding_tax_amount', 18, 2)->default(0);
            $table->decimal('compensation_amount', 18, 2)->default(0);
            $table->decimal('payable_amount', 18, 2)->default(0);
            $table->string('filing_reference', 120)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['month', 'year']);
            $table->index(['year', 'month', 'status']);
        });

        Schema::create('tax_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_period_id')->constrained('tax_periods')->restrictOnDelete();
            $table->foreignId('tax_rule_id')->nullable()->constrained('tax_rules')->nullOnDelete();
            $table->foreignId('original_document_id')->nullable()->constrained('tax_documents')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->string('direction', 20)->index();
            $table->string('tax_type', 30)->index();
            $table->string('document_type', 40)->index();
            $table->string('document_number', 120)->nullable()->index();
            $table->string('source_key', 180)->unique();
            $table->string('source_type', 80)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('counterparty_type', 40)->nullable();
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_tax_number', 32)->nullable();
            $table->text('counterparty_address')->nullable();
            $table->date('issue_date')->index();
            $table->date('tax_date')->index();
            $table->decimal('dpp_amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('dpp_factor', 12, 8)->default(1);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('luxury_tax_amount', 18, 2)->default(0);
            $table->decimal('withholding_tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->boolean('is_creditable')->default(true)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('reconciliation_status', 30)->default('unmatched')->index();
            $table->string('coretax_reference', 160)->nullable()->index();
            $table->text('reconciliation_notes')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id', 'direction']);
            $table->index(['tax_period_id', 'direction', 'status'], 'tax_documents_period_direction_idx');
        });

        Schema::create('tax_document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_document_id')->constrained('tax_documents')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_code')->nullable();
            $table->string('description');
            $table->string('unit_name', 100)->nullable();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('dpp_amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('luxury_tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_document_items');
        Schema::dropIfExists('tax_documents');
        Schema::dropIfExists('tax_periods');

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['tax_identity_type', 'tax_address', 'is_pkp']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['tax_number', 'tax_identity_type', 'tax_address', 'is_pkp']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['tax_rule_id']);
            $table->dropColumn(['tax_rule_id', 'tax_category_code', 'is_taxable']);
        });

        Schema::dropIfExists('tax_profiles');
        Schema::dropIfExists('tax_rules');
    }
};
