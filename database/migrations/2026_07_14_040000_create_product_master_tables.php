<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('product_brands', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('symbol', 20);
            $table->unsignedTinyInteger('precision')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('has_transactions')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 80)->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
            $table->string('model')->nullable();
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->string('material')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->decimal('minimum_order', 18, 4)->default(0);
            $table->decimal('minimum_stock', 18, 4)->default(0);
            $table->decimal('safety_stock', 18, 4)->default(0);
            $table->decimal('weight', 18, 4)->nullable();
            $table->decimal('volume', 18, 4)->nullable();
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('total_stock', 18, 4)->default(0);
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->decimal('minimum_price', 18, 2)->default(0);
            $table->string('main_image_path')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('has_transactions')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'brand_id', 'status']);
            $table->index(['minimum_stock', 'total_stock']);
        });

        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name')->nullable();
            $table->decimal('conversion_factor', 18, 6);
            $table->boolean('is_base')->default(false)->index();
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_locked')->default(false)->index();
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });

        Schema::create('product_barcodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained('product_units')->cascadeOnDelete();
            $table->string('code', 120)->unique();
            $table->string('type', 30)->default('barcode');
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('product_brands');
        Schema::dropIfExists('product_categories');
    }
};
