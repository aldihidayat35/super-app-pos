<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('session_id', 80)->unique();
            $table->string('status', 30)->default('disconnected')->index();
            $table->string('phone_number', 40)->nullable();
            $table->string('account_name')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('last_action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connections');
    }
};
