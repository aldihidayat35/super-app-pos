<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder akun administrasi kompatibilitas.
 *
 * Meneruskan panggilan ke seeder utama `DatabaseSeeder` agar kompatibel
 * dengan perintah ataupun script lama.
 */
class DemoFullApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
    }
}
