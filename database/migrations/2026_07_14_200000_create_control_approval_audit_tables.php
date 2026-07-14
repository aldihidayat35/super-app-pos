<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->string('approval_type', 80)->index();
            $table->string('module', 80)->index();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('current_status', 40)->default('pending')->index();
            $table->decimal('risk_value', 18, 2)->default(0);
            $table->string('risk_level', 40)->default('normal')->index();
            $table->string('required_permission', 120)->nullable()->index();
            $table->string('required_role', 120)->nullable()->index();
            $table->unsignedSmallInteger('required_level')->default(1);
            $table->text('reason');
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->string('handler_key', 120)->nullable();
            $table->boolean('separation_of_duties')->default(true);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->string('correlation_id', 80)->nullable()->index();
            $table->timestamps();

            $table->index(['module', 'current_status', 'expires_at']);
        });

        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order')->default(1);
            $table->string('required_role', 120)->nullable();
            $table->string('required_permission', 120)->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('comments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 120)->index();
            $table->string('module', 80)->index();
            $table->nullableMorphs('subject');
            $table->string('route_name', 160)->nullable();
            $table->string('http_method', 12)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent_hash', 80)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('severity', 40)->default('info')->index();
            $table->string('correlation_id', 80)->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['module', 'event', 'occurred_at']);
        });

        Schema::create('anomaly_alerts', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('subject');
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_key', 120)->index();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('severity', 40)->default('medium')->index();
            $table->decimal('risk_value', 18, 2)->default(0);
            $table->json('evidence')->nullable();
            $table->string('status', 40)->default('open')->index();
            $table->timestamp('detected_at')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->string('correlation_id', 80)->nullable()->index();
            $table->timestamps();

            $table->index(['rule_key', 'status', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_alerts');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
    }
};
