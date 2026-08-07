<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_documents', function (Blueprint $table): void {
            $table->string('document_number', 120)->nullable()->after('name');
            $table->date('issued_at')->nullable()->after('document_number');
            $table->text('notes')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_documents', function (Blueprint $table): void {
            $table->dropColumn(['document_number', 'issued_at', 'notes']);
        });
    }
};
