<?php

namespace App\Models;

use Database\Factories\WorkLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkLocation extends Model
{
    /** @use HasFactory<WorkLocationFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'code',
        'name',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_work_locations')
            ->withPivot('is_default', 'effective_from', 'effective_until', 'is_active')
            ->withTimestamps();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'warehouse' => 'Gudang',
            'branch' => 'Cabang/Toko',
            default => ucfirst($this->type),
        };
    }
}
