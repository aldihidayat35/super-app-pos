<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('whatsapp_number', 50)->nullable();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('tax_number', 80)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->unsignedSmallInteger('payment_term_days')->default(0);
            $table->decimal('last_price', 18, 2)->default(0);
            $table->decimal('performance_score', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('whatsapp_number', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('supplier_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('last_price', 18, 2)->default(0);
            $table->timestamp('last_supplied_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id']);
        });

        Schema::create('supplier_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('name');
            $table->string('path');
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 40)->default('general')->index();
            $table->string('code', 60)->unique();
            $table->string('business_name');
            $table->string('owner_name')->nullable();
            $table->string('pic_name')->nullable();
            $table->string('whatsapp_number', 50)->nullable();
            $table->string('email')->nullable()->index();
            $table->text('business_address')->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('price_category', 60)->default('retail')->index();
            $table->decimal('minimum_order', 18, 2)->default(0);
            $table->unsignedSmallInteger('payment_term_days')->default(0);
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->decimal('receivable_balance', 18, 2)->default(0);
            $table->string('verification_status', 40)->default('pending_verification')->index();
            $table->string('account_status', 40)->default('pending_verification')->index();
            $table->text('status_reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('recipient_name')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->text('address');
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('directions')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->string('primary_scope', 20)->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'primary_scope']);
        });

        Schema::create('customer_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40)->default('langganan_staff');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('blocked_at')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'user_id']);
        });

        Schema::create('customer_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('name');
            $table->string('path');
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_price_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 18, 2);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'starts_at', 'ends_at']);
        });

        Schema::create('credit_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->unsignedSmallInteger('payment_term_days')->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->date('effective_from')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_limits');
        Schema::dropIfExists('customer_price_overrides');
        Schema::dropIfExists('customer_documents');
        Schema::dropIfExists('customer_users');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('supplier_documents');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
