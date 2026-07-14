<?php

namespace App\Models;

use App\Enums\ApprovalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'approval_type', 'module', 'requester_user_id', 'work_location_id', 'current_status', 'risk_value', 'risk_level', 'required_permission', 'required_role', 'required_level', 'reason', 'before_payload', 'after_payload', 'metadata', 'handler_key', 'separation_of_duties', 'expires_at', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'cancelled_at', 'executed_at', 'decision_notes', 'correlation_id'];

    protected function casts(): array
    {
        return [
            'current_status' => ApprovalRequestStatus::class,
            'risk_value' => 'decimal:2',
            'required_level' => 'integer',
            'before_payload' => 'array',
            'after_payload' => 'array',
            'metadata' => 'array',
            'separation_of_duties' => 'boolean',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return HasMany<ApprovalStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class);
    }
}
