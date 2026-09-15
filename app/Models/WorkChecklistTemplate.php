<?php

namespace App\Models;

use App\Enums\WorkChecklistFrequency;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $template_key
 * @property int $version
 * @property WorkChecklistFrequency $frequency
 * @property string $scope
 * @property string|null $location_type
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property bool $is_active
 * @property-read Collection<int, WorkChecklistTemplateRole> $roles
 * @property-read Collection<int, WorkChecklistTemplateItem> $items
 */
class WorkChecklistTemplate extends Model
{
    protected $fillable = ['template_key', 'version', 'name', 'frequency', 'scope', 'location_type', 'effective_from', 'effective_until', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return [
            'frequency' => WorkChecklistFrequency::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<WorkChecklistTemplateRole, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(WorkChecklistTemplateRole::class);
    }

    /** @return HasMany<WorkChecklistTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(WorkChecklistTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
