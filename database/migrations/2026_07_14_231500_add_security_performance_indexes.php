<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['actor_user_id', 'occurred_at'], 'audit_logs_actor_time_idx');
            $table->index(['ip_address', 'occurred_at'], 'audit_logs_ip_time_idx');
            $table->index(['severity', 'occurred_at'], 'audit_logs_severity_time_idx');
        });

        Schema::table('stock_mutations', function (Blueprint $table): void {
            $table->index(['work_location_id', 'occurred_at', 'mutation_type'], 'stock_mutations_location_time_type_idx');
            $table->index(['mutation_type', 'occurred_at'], 'stock_mutations_type_time_idx');
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->index(['work_location_id', 'status', 'completed_at'], 'pos_sales_location_status_time_idx');
            $table->index(['cash_shift_id', 'status'], 'pos_sales_shift_status_idx');
        });

        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->index(['status', 'submitted_at'], 'b2b_orders_status_submitted_idx');
            $table->index(['reservation_expires_at', 'status'], 'b2b_orders_reservation_status_idx');
        });

        Schema::table('receivables', function (Blueprint $table): void {
            $table->index(['work_location_id', 'status', 'due_date'], 'receivables_location_status_due_idx');
            $table->index(['aging_bucket', 'status', 'due_date'], 'receivables_aging_status_due_idx');
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->index(['module', 'current_status', 'created_at'], 'approval_requests_module_status_created_idx');
            $table->index(['requester_user_id', 'current_status'], 'approval_requests_requester_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->dropIndex('approval_requests_requester_status_idx');
            $table->dropIndex('approval_requests_module_status_created_idx');
        });

        Schema::table('receivables', function (Blueprint $table): void {
            $table->dropIndex('receivables_aging_status_due_idx');
            $table->dropIndex('receivables_location_status_due_idx');
        });

        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->dropIndex('b2b_orders_reservation_status_idx');
            $table->dropIndex('b2b_orders_status_submitted_idx');
        });

        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropIndex('pos_sales_shift_status_idx');
            $table->dropIndex('pos_sales_location_status_time_idx');
        });

        Schema::table('stock_mutations', function (Blueprint $table): void {
            $table->dropIndex('stock_mutations_type_time_idx');
            $table->dropIndex('stock_mutations_location_time_type_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_severity_time_idx');
            $table->dropIndex('audit_logs_ip_time_idx');
            $table->dropIndex('audit_logs_actor_time_idx');
        });
    }
};
