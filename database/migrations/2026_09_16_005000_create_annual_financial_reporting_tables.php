<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('status', 30)->default('draft')->index();
            $table->boolean('is_historical')->default(false);
            $table->string('legal_name');
            $table->string('tax_number', 40)->nullable();
            $table->text('company_address')->nullable();
            $table->string('report_city', 120)->nullable();
            $table->string('commissioner_name')->nullable();
            $table->string('director_name')->nullable();
            $table->string('tax_scheme', 40)->default('article_31e');
            $table->decimal('final_rate', 8, 4)->default(0.5);
            $table->decimal('facility_rate', 8, 4)->default(11);
            $table->decimal('general_rate', 8, 4)->default(22);
            $table->decimal('small_turnover_limit', 18, 2)->default(4800000000);
            $table->decimal('facility_turnover_limit', 18, 2)->default(50000000000);
            $table->decimal('fiscal_positive_adjustment', 18, 2)->default(0);
            $table->decimal('fiscal_negative_adjustment', 18, 2)->default(0);
            $table->decimal('tax_credit_amount', 18, 2)->default(0);
            $table->decimal('installment_amount', 18, 2)->default(0);
            $table->decimal('prior_payment_amount', 18, 2)->default(0);
            $table->decimal('manual_tax_amount', 18, 2)->nullable();
            $table->boolean('tax_scheme_confirmed')->default(false);
            $table->decimal('commercial_profit_amount', 18, 2)->default(0);
            $table->decimal('taxable_income_amount', 18, 2)->default(0);
            $table->decimal('income_tax_amount', 18, 2)->default(0);
            $table->decimal('tax_payable_amount', 18, 2)->default(0);
            $table->decimal('balance_difference_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_monthly_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_year_id')->constrained('financial_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('auto_pos_sales_amount', 18, 2)->default(0);
            $table->decimal('auto_b2b_sales_amount', 18, 2)->default(0);
            $table->decimal('auto_returns_amount', 18, 2)->default(0);
            $table->decimal('auto_pos_cogs_amount', 18, 2)->default(0);
            $table->decimal('auto_b2b_cogs_amount', 18, 2)->default(0);
            $table->decimal('auto_shift_expense_amount', 18, 2)->default(0);
            $table->decimal('auto_receivable_balance', 18, 2)->default(0);
            $table->decimal('auto_inventory_balance', 18, 2)->default(0);
            $table->unsignedInteger('missing_hpp_count')->default(0);
            $table->json('source_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();

            $table->unique(['financial_year_id', 'month']);
        });

        Schema::create('financial_monthly_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_monthly_closing_id')->constrained('financial_monthly_closings')->cascadeOnDelete();
            $table->string('account_code', 80)->index();
            $table->string('label');
            $table->string('entry_type', 30)->default('manual');
            $table->decimal('amount', 18, 2);
            $table->text('reason');
            $table->string('proof_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('tax_profiles')->update([
            'is_pkp' => false,
            'calculation_enabled' => false,
            'default_output_tax_rule_id' => null,
            'default_input_tax_rule_id' => null,
            'updated_at' => now(),
        ]);

        $permissions = [
            'finance_annual.view' => ['Lihat Keuangan Tahunan', 'view'],
            'finance_annual.manage' => ['Kelola Keuangan Tahunan', 'manage'],
            'finance_annual.approve' => ['Setujui Keuangan Tahunan', 'approve'],
            'finance_annual.export' => ['Ekspor Keuangan Tahunan', 'export'],
        ];
        foreach ($permissions as $name => [$label, $action]) {
            Permission::findOrCreate($name)->forceFill(['label' => $label, 'module' => 'finance_annual', 'action' => $action, 'description' => $label.'.', 'is_system' => true])->save();
        }
        $staff = Role::findOrCreate('staf_keuangan');
        $staff->forceFill(['label' => 'Staf Keuangan', 'description' => 'Menyusun rekap keuangan bulanan dan laporan tahunan.', 'is_system' => true])->save();
        $staff->syncPermissions(['finance_annual.view', 'finance_annual.manage', 'finance_annual.export']);
        $head = Role::findOrCreate('kepala_keuangan');
        $head->forceFill(['label' => 'Kepala Keuangan', 'description' => 'Memeriksa, menyetujui, dan mengunci laporan keuangan.', 'is_system' => true])->save();
        $head->syncPermissions(['finance_annual.view', 'finance_annual.manage', 'finance_annual.approve', 'finance_annual.export']);
        foreach (['owner_viewer', 'owner_approver'] as $roleName) {
            Role::findByName($roleName)->givePermissionTo(['finance_annual.view', 'finance_annual.export']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_monthly_entries');
        Schema::dropIfExists('financial_monthly_closings');
        Schema::dropIfExists('financial_years');
        Role::query()->whereIn('name', ['staf_keuangan', 'kepala_keuangan'])->delete();
        Permission::query()->where('module', 'finance_annual')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
