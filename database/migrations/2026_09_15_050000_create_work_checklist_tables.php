<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_checklist_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_key', 100);
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->string('frequency', 20)->index();
            $table->string('scope', 20)->default('global');
            $table->string('location_type', 30)->nullable();
            $table->date('effective_from')->index();
            $table->date('effective_until')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['template_key', 'version']);
            $table->index(['frequency', 'scope', 'is_active']);
        });

        Schema::create('work_checklist_template_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_checklist_template_id')->constrained()->cascadeOnDelete();
            $table->string('role_name', 100)->index();
            $table->unique(['work_checklist_template_id', 'role_name'], 'checklist_template_role_unique');
        });

        Schema::create('work_checklist_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_checklist_template_id')->constrained()->cascadeOnDelete();
            $table->string('item_key', 150);
            $table->string('label');
            $table->text('guidance')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['work_checklist_template_id', 'item_key'], 'checklist_template_item_unique');
        });

        Schema::create('work_checklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope_key', 80);
            $table->string('frequency', 20)->index();
            $table->date('period_start')->index();
            $table->date('period_end');
            $table->timestamp('due_at')->index();
            $table->string('status', 20)->default('open')->index();
            $table->json('role_snapshot');
            $table->json('template_snapshot');
            $table->timestamp('first_completed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'scope_key', 'frequency', 'period_start'], 'work_checklist_period_unique');
            $table->index(['work_location_id', 'period_start', 'status'], 'work_checklist_location_period_idx');
        });

        Schema::create('work_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('item_key', 150);
            $table->string('label');
            $table->text('guidance')->nullable();
            $table->json('role_snapshot');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->string('status', 30)->default('pending')->index();
            $table->text('note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['work_checklist_id', 'item_key'], 'work_checklist_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_checklist_items');
        Schema::dropIfExists('work_checklists');
        Schema::dropIfExists('work_checklist_template_items');
        Schema::dropIfExists('work_checklist_template_roles');
        Schema::dropIfExists('work_checklist_templates');
    }
};
