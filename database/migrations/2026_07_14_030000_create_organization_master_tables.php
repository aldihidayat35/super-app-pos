<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_location_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('phone_number', 50)->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('capacity', 18, 4)->nullable();
            $table->string('service_area')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('has_transactions')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_location_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('primary_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('sales_target', 18, 2)->nullable();
            $table->string('price_configuration')->default('standard');
            $table->string('closing_configuration')->default('daily');
            $table->boolean('is_closing_required')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('has_transactions')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 50);
            $table->string('location_type', 30)->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('prefix', 30);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->boolean('reset_yearly')->default(true);
            $table->string('format')->default('{prefix}/{location}/{year}/{sequence}');
            $table->timestamps();

            $table->unique(['document_type', 'location_type', 'location_id', 'year'], 'document_sequences_unique_scope');
            $table->index(['document_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('warehouses');
    }
};
