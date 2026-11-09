<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder inisialisasi awal production (hosting/server).
 *
 * Meneruskan panggilan ke seeder utama `DatabaseSeeder`.
 */
class InitialAdministrationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
    }
}
