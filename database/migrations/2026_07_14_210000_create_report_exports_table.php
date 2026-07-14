<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->string('report_type', 80)->index();
            $table->string('format', 20)->index();
            $table->string('status', 40)->default('queued')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('filters')->nullable();
            $table->unsignedInteger('progress')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->string('disk', 40)->default('local');
            $table->string('file_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->string('correlation_id', 80)->nullable()->index();
            $table->timestamps();

            $table->index(['requested_by', 'status', 'created_at']);
            $table->index(['report_type', 'format', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
