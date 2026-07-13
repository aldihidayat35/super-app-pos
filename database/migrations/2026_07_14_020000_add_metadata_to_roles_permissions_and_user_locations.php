<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('roles', 'label')) {
                $table->string('label')->nullable()->after('name');
            }

            if (! Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('guard_name');
            }

            if (! Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->index()->after('description');
            }
        });

        Schema::table('permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('permissions', 'label')) {
                $table->string('label')->nullable()->after('name');
            }

            if (! Schema::hasColumn('permissions', 'module')) {
                $table->string('module')->nullable()->index()->after('label');
            }

            if (! Schema::hasColumn('permissions', 'action')) {
                $table->string('action')->nullable()->index()->after('module');
            }

            if (! Schema::hasColumn('permissions', 'description')) {
                $table->text('description')->nullable()->after('guard_name');
            }

            if (! Schema::hasColumn('permissions', 'is_system')) {
                $table->boolean('is_system')->default(false)->index()->after('description');
            }
        });

        Schema::table('user_work_locations', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_work_locations', 'effective_from')) {
                $table->date('effective_from')->nullable()->after('is_default');
            }

            if (! Schema::hasColumn('user_work_locations', 'effective_until')) {
                $table->date('effective_until')->nullable()->after('effective_from');
            }

            if (! Schema::hasColumn('user_work_locations', 'is_active')) {
                $table->boolean('is_active')->default(true)->index()->after('effective_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_work_locations', function (Blueprint $table): void {
            foreach (['is_active', 'effective_until', 'effective_from'] as $column) {
                if (Schema::hasColumn('user_work_locations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('permissions', function (Blueprint $table): void {
            foreach (['is_system', 'description', 'action', 'module', 'label'] as $column) {
                if (Schema::hasColumn('permissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('roles', function (Blueprint $table): void {
            foreach (['is_system', 'description', 'label'] as $column) {
                if (Schema::hasColumn('roles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
