<?php

namespace App\Models;

use App\Enums\WorkChecklistFrequency;
use App\Enums\WorkChecklistStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int|null $work_location_id
 * @property WorkChecklistFrequency $frequency
 * @property WorkChecklistStatus $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $due_at
 * @property array<int, string> $role_snapshot
 * @property array<int, array<string, mixed>> $template_snapshot
 * @property Carbon|null $first_completed_at
 * @property Carbon|null $completed_at
 * @property-read User $user
 * @property-read WorkLocation|null $workLocation
 * @property-read Collection<int, WorkChecklistItem> $items
 */
class WorkChecklist extends Model
{
    protected $fillable = ['user_id', 'work_location_id', 'scope_key', 'frequency', 'period_start', 'period_end', 'due_at', 'status', 'role_snapshot', 'template_snapshot', 'first_completed_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'frequency' => WorkChecklistFrequency::class,
            'status' => WorkChecklistStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'due_at' => 'datetime',
            'role_snapshot' => 'array',
            'template_snapshot' => 'array',
            'first_completed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return HasMany<WorkChecklistItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(WorkChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isLate(): bool
    {
        $finishedAt = $this->first_completed_at ?? now();

        return $finishedAt->greaterThan($this->due_at);
    }
}
