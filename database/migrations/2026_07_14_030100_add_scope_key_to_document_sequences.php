<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_sequences', function (Blueprint $table): void {
            $table->string('scope_key')->default('global')->after('location_id');
        });

        DB::table('document_sequences')
            ->select(['id', 'location_type', 'location_id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $sequence): void {
                DB::table('document_sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'scope_key' => $sequence->location_type && $sequence->location_id
                            ? "{$sequence->location_type}:{$sequence->location_id}"
                            : 'global',
                    ]);
            });

        Schema::table('document_sequences', function (Blueprint $table): void {
            $table->unique(['document_type', 'scope_key', 'year'], 'document_sequences_unique_scope_key');
        });
    }

    public function down(): void
    {
        Schema::table('document_sequences', function (Blueprint $table): void {
            $table->dropUnique('document_sequences_unique_scope_key');
            $table->dropColumn('scope_key');
        });
    }
};
