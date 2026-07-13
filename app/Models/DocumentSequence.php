<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'document_type',
        'location_type',
        'location_id',
        'scope_key',
        'year',
        'prefix',
        'next_number',
        'padding',
        'reset_yearly',
        'format',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'next_number' => 'integer',
            'padding' => 'integer',
            'reset_yearly' => 'boolean',
        ];
    }
}
