<?php

namespace App\Services\Control;

use App\Enums\ApprovalRequestStatus;
use App\Enums\PriceApprovalStatus;
use App\Enums\ProductPriceStatus;
use App\Exceptions\ServiceException;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\CustomerPriceOverride;
use App\Models\PriceApprovalRequest;
use App\Models\ProductPrice;
use App\Models\User;
use App\Models\WorkLocation;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApprovalWorkflowService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function create(
        Model $subject,
        string $type,
        string $module,
        User $requester,
        string|int $riskValue,
        string $reason,
        array $before = [],
        array $after = [],
        array $metadata = [],
        ?WorkLocation $location = null,
        ?string $requiredPermission = 'approvals.approve',
        ?string $requiredRole = null,
        ?string $handlerKey = null,
        ?string $correlationId = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($subject, $type, $module, $requester, $riskValue, $reason, $before, $after, $metadata, $location, $requiredPermission, $requiredRole, $handlerKey, $correlationId): ApprovalRequest {
            $existing = ApprovalRequest::query()
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->where('approval_type', $type)
                ->where('current_status', ApprovalRequestStatus::PENDING->value)
                ->first();
            if ($existing instanceof ApprovalRequest) {
                return $existing->fresh(['subject', 'requester', 'steps']);
            }

            $approval = ApprovalRequest::query()->create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'approval_type' => $type,
                'module' => $module,
                'requester_user_id' => $requester->id,
                'work_location_id' => $location?->id,
                'risk_value' => $riskValue,
                'risk_level' => $this->riskLevel((string) $riskValue),
                'required_permission' => $requiredPermission,
                'required_role' => $requiredRole,
                'required_level' => 1,
                'reason' => $reason,
                'before_payload' => $this->audit->redact($before),
                'after_payload' => $this->audit->redact($after),
                'metadata' => $this->audit->redact($metadata),
                'handler_key' => $handlerKey,
                'expires_at' => now()->addDays(7),
                'correlation_id' => $correlationId ?? (string) Str::uuid(),
            ]);
            ApprovalStep::query()->create([
                'approval_request_id' => $approval->id,
                'step_order' => 1,
                'required_role' => $requiredRole,
                'required_permission' => $requiredPermission,
            ]);

            $this->audit->record('approval.requested', $module, $requester, $subject, [], $approval->only(['approval_type', 'risk_value', 'risk_level', 'reason']), $reason, correlationId: $approval->correlation_id);

            return $approval->fresh(['subject', 'requester', 'steps']);
        });
    }

    /**
     * @param  null|callable(ApprovalRequest): void  $afterApproval
     */
    public function approve(ApprovalRequest $approval, User $approver, ?string $comments = null, ?callable $afterApproval = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approval, $approver, $comments, $afterApproval): ApprovalRequest {
            $approval = ApprovalRequest::query()->with('subject')->lockForUpdate()->findOrFail($approval->id);
            $status = $this->status($approval);
            if ($status === ApprovalRequestStatus::APPROVED) {
                return $approval->fresh(['subject', 'requester', 'steps']);
            }
            if ($status !== ApprovalRequestStatus::PENDING) {
                throw ServiceException::validation('Approval tidak dapat diproses pada status saat ini.');
            }
            if ($this->isExpired($approval)) {
                $approval->forceFill(['current_status' => ApprovalRequestStatus::EXPIRED])->save();
                throw ServiceException::validation('Approval sudah kedaluwarsa.');
            }
            if ($approval->separation_of_duties && (int) $approval->requester_user_id === (int) $approver->id) {
                throw ServiceException::validation('Requester tidak boleh menyetujui permintaannya sendiri.');
            }
            if ($approval->required_permission !== null && ! $approver->can($approval->required_permission)) {
                throw ServiceException::validation('Anda tidak memiliki permission untuk approval ini.');
            }
            if ($approval->required_role !== null && ! $approver->hasRole($approval->required_role)) {
                throw ServiceException::validation('Role Anda tidak sesuai untuk approval ini.');
            }

            $approval->steps()->where('status', ApprovalRequestStatus::PENDING->value)->orderBy('step_order')->first()?->forceFill([
                'status' => ApprovalRequestStatus::APPROVED,
                'approver_user_id' => $approver->id,
                'decided_at' => now(),
                'comments' => $comments,
            ])->save();

            $approval->forceFill(['decision_notes' => $comments])->save();
            $this->executeHandler($approval, $approver);
            if ($afterApproval !== null) {
                $afterApproval($approval);
            }

            $approval->forceFill([
                'current_status' => ApprovalRequestStatus::APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'executed_at' => now(),
            ])->save();

            $subject = $approval->subject instanceof Model ? $approval->subject : null;
            $this->audit->record('approval.approved', $approval->module, $approver, $subject, [], ['approval_id' => $approval->id, 'comments' => $comments], $comments, correlationId: $approval->correlation_id);

            return $approval->fresh(['subject', 'requester', 'steps']);
        });
    }

    public function reject(ApprovalRequest $approval, User $approver, ?string $comments = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approval, $approver, $comments): ApprovalRequest {
            $approval = ApprovalRequest::query()->with('subject')->lockForUpdate()->findOrFail($approval->id);
            if ($this->status($approval) !== ApprovalRequestStatus::PENDING) {
                throw ServiceException::validation('Approval tidak dapat ditolak pada status saat ini.');
            }
            if ($approval->separation_of_duties && (int) $approval->requester_user_id === (int) $approver->id) {
                throw ServiceException::validation('Requester tidak boleh menolak permintaannya sendiri.');
            }
            if ($approval->required_permission !== null && ! $approver->can($approval->required_permission)) {
                throw ServiceException::validation('Anda tidak memiliki permission untuk approval ini.');
            }

            $approval->steps()->where('status', ApprovalRequestStatus::PENDING->value)->orderBy('step_order')->first()?->forceFill([
                'status' => ApprovalRequestStatus::REJECTED,
                'approver_user_id' => $approver->id,
                'decided_at' => now(),
                'comments' => $comments,
            ])->save();
            $approval->forceFill(['current_status' => ApprovalRequestStatus::REJECTED, 'rejected_by' => $approver->id, 'rejected_at' => now(), 'decision_notes' => $comments])->save();

            $subject = $approval->subject instanceof Model ? $approval->subject : null;
            $this->audit->record('approval.rejected', $approval->module, $approver, $subject, [], ['approval_id' => $approval->id, 'comments' => $comments], $comments, correlationId: $approval->correlation_id);

            return $approval->fresh(['subject', 'requester', 'steps']);
        });
    }

    private function executeHandler(ApprovalRequest $approval, User $approver): void
    {
        if ($approval->handler_key !== 'pricing.approval') {
            return;
        }

        $subject = $approval->subject;
        if (! $subject instanceof PriceApprovalRequest) {
            throw ServiceException::validation('Handler approval harga tidak menemukan subject yang valid.');
        }
        $subject->forceFill(['status' => PriceApprovalStatus::APPROVED, 'approved_by' => $approver->id, 'approved_at' => now(), 'decision_notes' => $approval->decision_notes])->save();
        if ($subject->document_type === 'customer_price_override') {
            CustomerPriceOverride::query()->whereKey($subject->document_id)->update(['status' => PriceApprovalStatus::APPROVED->value, 'approved_by' => $approver->id, 'approved_at' => now()]);
        }
        if ($subject->document_type === 'product_price') {
            ProductPrice::query()->whereKey($subject->document_id)->update(['status' => ProductPriceStatus::ACTIVE->value]);
        }
    }

    private function status(ApprovalRequest $approval): ApprovalRequestStatus
    {
        return ApprovalRequestStatus::from((string) $approval->getRawOriginal('current_status'));
    }

    private function isExpired(ApprovalRequest $approval): bool
    {
        if ($approval->expires_at === null) {
            return false;
        }

        return Carbon::parse($approval->expires_at)->isPast();
    }

    private function riskLevel(string $value): string
    {
        return match (true) {
            Decimal::compare($value, '5000000', 2) >= 0 => 'critical',
            Decimal::compare($value, '1000000', 2) >= 0 => 'high',
            Decimal::compare($value, '100000', 2) >= 0 => 'medium',
            default => 'normal',
        };
    }
}
