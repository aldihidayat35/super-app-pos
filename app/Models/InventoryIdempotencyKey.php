<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed>|null $response
 */
class InventoryIdempotencyKey extends Model
{
    protected $fillable = ['key', 'operation', 'response'];

    protected function casts(): array
    {
        return ['response' => 'array'];
    }
}
