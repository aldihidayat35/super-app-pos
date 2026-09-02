<?php

namespace App\Models;

use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRule extends Model
{
    protected $fillable = [
        'code', 'name', 'tax_type', 'direction', 'rate', 'dpp_factor', 'luxury_tax_rate', 'is_creditable',
        'effective_from', 'effective_until', 'is_active', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tax_type' => TaxType::class,
            'rate' => 'decimal:4',
            'dpp_factor' => 'decimal:8',
            'luxury_tax_rate' => 'decimal:4',
            'is_creditable' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
