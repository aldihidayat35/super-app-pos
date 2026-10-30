<?php

namespace Tests\Feature\System;

use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_units_are_seeded_idempotently(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(UnitSeeder::class);

        $this->assertDatabaseHas('units', ['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs']);
        $this->assertDatabaseHas('units', ['code' => 'LSN', 'name' => 'Lusin', 'symbol' => 'lsn']);
        $this->assertDatabaseHas('units', ['code' => 'GROS', 'name' => 'Gros', 'symbol' => 'gros']);
        $this->assertDatabaseCount('units', 3);
    }
}
