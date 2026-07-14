<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('channel_type', 40)->index();
            $table->string('endpoint')->nullable();
            $table->string('auth_type', 40)->default('bearer');
            $table->text('credentials')->nullable();
            $table->string('sender')->nullable();
            $table->string('default_destination')->nullable();
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->unsignedTinyInteger('retry_attempts')->default(3);
            $table->boolean('is_active')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['channel_type', 'is_active']);
        });

        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100);
            $table->string('name', 160);
            $table->string('channel_type', 40)->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->text('fallback_body')->nullable();
            $table->json('allowed_variables')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->json('history')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'channel_type', 'version']);
            $table->index(['key', 'channel_type', 'is_active']);
        });

        Schema::create('notification_recipients', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('recipient_type', 40)->default('user')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_name', 120)->nullable()->index();
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->string('channel_type', 40)->index();
            $table->string('destination', 180);
            $table->string('report_type', 80)->default('daily_report')->index();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['report_type', 'channel_type', 'is_active']);
            $table->index(['work_location_id', 'role_name']);
        });

        Schema::create('notification_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('schedule_key', 100)->unique();
            $table->string('frequency', 40)->default('daily')->index();
            $table->time('run_time');
            $table->string('timezone', 80)->default('Asia/Jakarta');
            $table->string('report_type', 80)->default('daily_report')->index();
            $table->string('report_period', 40)->default('yesterday');
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->json('channel_types')->nullable();
            $table->json('recipient_scope')->nullable();
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();

            $table->index(['report_type', 'is_active', 'next_run_at']);
        });

        Schema::create('daily_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained('notification_schedules')->nullOnDelete();
            $table->date('report_date')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 40)->default('generated')->index();
            $table->json('filters')->nullable();
            $table->json('summary');
            $table->json('rows')->nullable();
            $table->json('definitions')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 160)->unique();
            $table->timestamps();

            $table->unique(['schedule_id', 'report_date']);
        });

        Schema::create('secure_report_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->string('token_hash', 128)->unique();
            $table->string('recipient_destination', 180)->nullable();
            $table->foreignId('recipient_id')->nullable()->constrained('notification_recipients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('scope')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->boolean('one_time')->default(false);
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamps();
        });

        Schema::create('alert_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_key', 100)->unique();
            $table->string('name', 160);
            $table->string('alert_type', 80)->index();
            $table->string('severity', 40)->default('medium')->index();
            $table->decimal('threshold_value', 18, 4)->nullable();
            $table->unsignedInteger('cooldown_minutes')->default(60);
            $table->json('recipient_scope')->nullable();
            $table->json('channel_types')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_triggered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_channel_id')->nullable()->constrained('notification_channels')->nullOnDelete();
            $table->foreignId('notification_template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('notification_recipient_id')->nullable()->constrained('notification_recipients')->nullOnDelete();
            $table->foreignId('daily_report_id')->nullable()->constrained('daily_reports')->nullOnDelete();
            $table->foreignId('secure_report_token_id')->nullable()->constrained('secure_report_tokens')->nullOnDelete();
            $table->string('channel_type', 40)->index();
            $table->string('template_key', 100)->nullable()->index();
            $table->string('recipient_name', 160)->nullable();
            $table->string('destination', 180);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 40)->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->json('sanitized_response')->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['channel_type', 'status', 'created_at']);
            $table->index(['destination', 'template_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('secure_report_tokens');
        Schema::dropIfExists('daily_reports');
        Schema::dropIfExists('notification_schedules');
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_channels');
    }
};
