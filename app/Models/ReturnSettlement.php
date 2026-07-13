<?php

namespace App\Models;

use App\Enums\ReturnResolution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnSettlement extends Model
{
    protected $fillable = ['return_id', 'settled_by', 'resolution', 'document_no', 'amount', 'notes', 'metadata', 'settled_at'];

    protected function casts(): array
    {
        return ['resolution' => ReturnResolution::class, 'amount' => 'decimal:2', 'metadata' => 'array', 'settled_at' => 'datetime'];
    }

    /** @return BelongsTo<ReturnDocument, $this> */
    public function returnDocument(): BelongsTo
    {
        return $this->belongsTo(ReturnDocument::class, 'return_id');
    }
}
