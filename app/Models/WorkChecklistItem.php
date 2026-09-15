<?php

namespace App\Models;

use App\Enums\WorkChecklistItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property WorkChecklistItemStatus $status
 * @property array<int, string> $role_snapshot
 * @property Carbon|null $responded_at
 * @property-read WorkChecklist $checklist
 */
class WorkChecklistItem extends Model
{
    protected $fillable = ['work_checklist_id', 'item_key', 'label', 'guidance', 'role_snapshot', 'sort_order', 'is_required', 'status', 'note', 'responded_at'];

    protected function casts(): array
    {
        return [
            'role_snapshot' => 'array',
            'is_required' => 'boolean',
            'status' => WorkChecklistItemStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WorkChecklist, $this> */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(WorkChecklist::class, 'work_checklist_id');
    }
}
