<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs', 'precision' => 0],
            ['code' => 'LSN', 'name' => 'Lusin', 'symbol' => 'lsn', 'precision' => 0],
            ['code' => 'GROS', 'name' => 'Gros', 'symbol' => 'gros', 'precision' => 0],
            ['code' => 'BOX', 'name' => 'Box / Kotak', 'symbol' => 'box', 'precision' => 0],
            ['code' => 'PACK', 'name' => 'Pack / Bungkus', 'symbol' => 'pack', 'precision' => 0],
            ['code' => 'SET', 'name' => 'Set / Paket', 'symbol' => 'set', 'precision' => 0],
            ['code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg', 'precision' => 4],
            ['code' => 'GR', 'name' => 'Gram', 'symbol' => 'gr', 'precision' => 2],
            ['code' => 'MTR', 'name' => 'Meter', 'symbol' => 'm', 'precision' => 2],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'symbol' => $unit['symbol'],
                    'precision' => $unit['precision'],
                    'is_active' => true,
                ],
            );
        }
    }
}
