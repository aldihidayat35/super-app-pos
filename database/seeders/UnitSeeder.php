<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs'],
            ['code' => 'LSN', 'name' => 'Lusin', 'symbol' => 'lsn'],
            ['code' => 'GROS', 'name' => 'Gros', 'symbol' => 'gros'],
        ] as $unit) {
            Unit::query()->updateOrCreate(
                ['code' => $unit['code']],
                [...$unit, 'precision' => 0, 'is_active' => true],
            );
        }
    }
}
