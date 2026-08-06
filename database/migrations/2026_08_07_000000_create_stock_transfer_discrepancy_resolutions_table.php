<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_discrepancy_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('resolution_type', 40)->index();
            $table->text('notes');
            $table->string('proof_path')->nullable();
            $table->foreignId('resolved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->index();
            $table->foreignId('inventory_loss_id')->nullable()->constrained('inventory_losses')->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['stock_transfer_id', 'stock_transfer_item_id'], 'transfer_discrepancy_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_discrepancy_resolutions');
    }
};
