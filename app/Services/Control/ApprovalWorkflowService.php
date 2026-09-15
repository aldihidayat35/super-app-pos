<?php

namespace App\Services\Control;

use App\Enums\ApprovalRequestStatus;
use App\Enums\PriceApprovalStatus;
use App\Enums\ProductPriceStatus;
use App\Enums\StaffBonusPeriodStatus;
use App\Exceptions\ServiceException;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\CustomerPriceOverride;
use App\Models\EmergencyPurchase;
use App\Models\PriceApprovalRequest;
use App\Models\ProductPrice;
use App\Models\StaffBonusPeriod;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Notifications\BusinessNotificationService;
use App\Support\ApprovalAuthority;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApprovalWorkflowService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly BusinessNotificationService $notifications,
    ) {}

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
        $requiredRole ??= ApprovalAuthority::roleForLocation(
            $location,
            $module === 'tax' ? ApprovalAuthority::SYSTEM_HEAD : ApprovalAuthority::WAREHOUSE_HEAD,
        );

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
            $this->notifications->send(
                'approval_pending',
                'Approval Baru Menunggu Keputusan',
                "Modul: {$module}\nJenis: {$type}\nPemohon: {$requester->name}\nAlasan: {$reason}",
                $location?->id,
                route('approvals.show', $approval),
                $approval->id,
                roles: [$requiredRole],
                locationScoped: in_array($requiredRole, ['kepala_toko', 'kepala_gudang', 'supervisor_shift'], true),
            );

            return $approval->fresh(['subject', 'requester', 'steps']);
        });
    }

    /**
     * @param  null|callable(ApprovalRequest): void  $afterApproval
     */
    public function approve(ApprovalRequest $approval, User $approver, ?string $comments = null, ?callable $afterApproval = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approval, $approver, $comments, $afterApproval): ApprovalRequest {
            $approval = ApprovalRequest::query()->with(['subject', 'workLocation'])->lockForUpdate()->findOrFail($approval->id);
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
            $requiredRole = $approval->required_role ?: ApprovalAuthority::roleForLocation($approval->workLocation);
            if (! ApprovalAuthority::canApproveAt($approver, $approval->work_location_id, $requiredRole)) {
                throw ServiceException::validation('Approval ini hanya dapat diputuskan kepala bagian pada lokasi yang ditugaskan.');
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
            $this->notifications->send(
                'approval_decided',
                'Approval Disetujui',
                "Permintaan {$approval->approval_type} disetujui oleh {$approver->name}.".($comments ? "\nCatatan: {$comments}" : ''),
                $approval->work_location_id,
                route('approvals.show', $approval),
                $approval->id,
                userIds: [(int) $approval->requester_user_id],
            );

            return $approval->fresh(['subject', 'requester', 'steps']);
        });
    }

    public function reject(ApprovalRequest $approval, User $approver, ?string $comments = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approval, $approver, $comments): ApprovalRequest {
            $approval = ApprovalRequest::query()->with(['subject', 'workLocation'])->lockForUpdate()->findOrFail($approval->id);
            if ($this->status($approval) !== ApprovalRequestStatus::PENDING) {
                throw ServiceException::validation('Approval tidak dapat ditolak pada status saat ini.');
            }
            if ($approval->separation_of_duties && (int) $approval->requester_user_id === (int) $approver->id) {
                throw ServiceException::validation('Requester tidak boleh menolak permintaannya sendiri.');
            }
            if ($approval->required_permission !== null && ! $approver->can($approval->required_permission)) {
                throw ServiceException::validation('Anda tidak memiliki permission untuk approval ini.');
            }
            $requiredRole = $approval->required_role ?: ApprovalAuthority::roleForLocation($approval->workLocation);
            if (! ApprovalAuthority::canApproveAt($approver, $approval->work_location_id, $requiredRole)) {
                throw ServiceException::validation('Approval ini hanya dapat diputuskan kepala bagian pada lokasi yang ditugaskan.');
            }

            $approval->steps()->where('status', ApprovalRequestStatus::PENDING->value)->orderBy('step_order')->first()?->forceFill([
                'status' => ApprovalRequestStatus::REJECTED,
                'approver_user_id' => $approver->id,
                'decided_at' => now(),
                'comments' => $comments,
            ])->save();
            $approval->forceFill(['current_status' => ApprovalRequestStatus::REJECTED, 'rejected_by' => $approver->id, 'rejected_at' => now(), 'decision_notes' => $comments])->save();
            if ($approval->handler_key === 'retail.emergency_purchase' && $approval->subject instanceof EmergencyPurchase) {
                $purchase = $approval->subject;
                $purchase->forceFill(['status' => 'rejected'])->save();
                $purchase->histories()->create(['actor_id' => $approver->id, 'action' => 'rejected',
                    'from_status' => 'pending_approval', 'to_status' => 'rejected', 'notes' => $comments]);
            }
            if ($approval->handler_key === 'staff_bonus.period' && $approval->subject instanceof StaffBonusPeriod) {
                $approval->subject->forceFill([
                    'status' => StaffBonusPeriodStatus::REJECTED,
                    'rejected_by' => $approver->id,
                    'rejected_at' => now(),
                    'decision_note' => $comments,
                ])->save();
            }

            $subject = $approval->subject instanceof Model ? $approval->subject : null;
            $this->audit->record('approval.rejected', $approval->module, $approver, $subject, [], ['approval_id' => $approval->id, 'comments' => $comments], $comments, correlationId: $approval->correlation_id);
            $this->notifications->send(
                'approval_decided',
                'Approval Ditolak',
                "Permintaan {$approval->approval_type} ditolak oleh {$approver->name}.".($comments ? "\nAlasan: {$comments}" : ''),
                $approval->work_location_id,
                route('approvals.show', $approval),
                $approval->id,
                userIds: [(int) $approval->requester_user_id],
            );

            return $approval->fresh(['subject', 'requester', 'steps']);
        });
    }

    private function executeHandler(ApprovalRequest $approval, User $approver): void
    {
        if ($approval->handler_key === 'staff_bonus.period') {
            $period = $approval->subject;
            if (! $period instanceof StaffBonusPeriod || $period->status !== StaffBonusPeriodStatus::PENDING_APPROVAL) {
                throw ServiceException::validation('Periode bonus tidak lagi menunggu persetujuan.');
            }
            $period->forceFill([
                'status' => StaffBonusPeriodStatus::APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'decision_note' => $approval->decision_notes,
            ])->save();

            return;
        }
        if ($approval->handler_key === 'retail.emergency_purchase') {
            $purchase = $approval->subject;
            if (! $purchase instanceof EmergencyPurchase || $purchase->status !== 'pending_approval') {
                throw ServiceException::validation('Permintaan pembelian darurat tidak lagi menunggu approval.');
            }
            $purchase->forceFill(['status' => 'approved'])->save();
            $purchase->histories()->create(['actor_id' => $approver->id, 'action' => 'approved',
                'from_status' => 'pending_approval', 'to_status' => 'approved', 'notes' => $approval->decision_notes]);

            return;
        }
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
