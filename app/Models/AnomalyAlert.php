<?php

namespace App\Models;

use App\Enums\AnomalyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AnomalyAlert extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'work_location_id', 'rule_key', 'title', 'description', 'severity', 'risk_value', 'evidence', 'status', 'detected_at', 'assigned_to', 'reviewed_by', 'reviewed_at', 'resolved_by', 'resolved_at', 'resolution_note', 'correlation_id'];

    protected function casts(): array
    {
        return [
            'risk_value' => 'decimal:2',
            'evidence' => 'array',
            'status' => AnomalyStatus::class,
            'detected_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
