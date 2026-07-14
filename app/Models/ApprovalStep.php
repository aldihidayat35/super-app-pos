<?php

namespace App\Models;

use App\Enums\ApprovalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    protected $fillable = ['approval_request_id', 'step_order', 'required_role', 'required_permission', 'status', 'approver_user_id', 'decided_at', 'comments', 'metadata'];

    protected function casts(): array
    {
        return [
            'status' => ApprovalRequestStatus::class,
            'step_order' => 'integer',
            'decided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
