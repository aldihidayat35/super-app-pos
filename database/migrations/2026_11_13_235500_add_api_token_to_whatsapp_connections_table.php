<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table): void {
            $table->string('api_token', 120)->nullable()->after('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table): void {
            $table->dropColumn('api_token');
        });
    }
};
