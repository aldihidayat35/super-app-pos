<?php

namespace App\Services\Control;

use App\Enums\AnomalyStatus;
use App\Models\AnomalyAlert;
use App\Models\AuditLog;
use App\Models\CashShift;
use App\Models\PriceApprovalRequest;
use App\Models\Receivable;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AnomalyDetectionService
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function flag(Model $subject, string $ruleKey, string $title, string $description, string $severity = 'medium', string|int $riskValue = 0, array $evidence = []): AnomalyAlert
    {
        return AnomalyAlert::query()->firstOrCreate(
            [
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'rule_key' => $ruleKey,
                'status' => AnomalyStatus::OPEN->value,
            ],
            [
                'work_location_id' => $subject->getAttribute('work_location_id'),
                'title' => $title,
                'description' => $description,
                'severity' => $severity,
                'risk_value' => $riskValue,
                'evidence' => $evidence,
                'detected_at' => now(),
            ],
        );
    }

    public function detectPriceApproval(PriceApprovalRequest $approval): ?AnomalyAlert
    {
        $type = (string) $approval->approval_type;
        if (! str_contains($type, 'below_minimum') && ! str_contains($type, 'overpricing') && ! str_contains($type, 'discount')) {
            return null;
        }

        $riskValue = Decimal::normalize($approval->requested_price ?? '0', 2);

        return $this->flag(
            $approval,
            'pricing_sensitive',
            'Harga sensitif membutuhkan review',
            'Permintaan harga berada di luar batas aturan margin/diskon.',
            str_contains($type, 'below_minimum') ? 'high' : 'medium',
            $riskValue,
            $approval->only(['approval_type', 'requested_price', 'minimum_price_snapshot', 'maximum_price_snapshot', 'discount_percent']),
        );
    }

    public function detectClosingDifference(CashShift $shift): ?AnomalyAlert
    {
        $difference = Decimal::normalize($shift->difference_amount ?? '0', 2);
        $absoluteDifference = str_starts_with($difference, '-') ? substr($difference, 1) : $difference;
        $threshold = Decimal::normalize($shift->discrepancy_threshold_amount ?? '0', 2);

        if (Decimal::compare($absoluteDifference, $threshold, 2) < 0) {
            return null;
        }

        return $this->flag($shift, 'cash_difference', 'Selisih closing melewati threshold', 'Selisih kas perlu ditinjau supervisor.', 'high', $difference, $shift->only(['number', 'difference_amount', 'discrepancy_threshold_amount']));
    }

    public function detectOverdueReceivables(): int
    {
        $count = 0;
        Receivable::query()->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', now()->subDays(30)->toDateString())->each(function (Receivable $receivable) use (&$count): void {
            $this->flag($receivable, 'receivable_overdue', 'Piutang overdue lebih dari 30 hari', 'Piutang lama perlu follow-up collection.', 'medium', Decimal::normalize($receivable->outstanding_amount ?? '0', 2), $receivable->only(['number', 'due_date', 'outstanding_amount']));
            $count++;
        });

        return $count;
    }

    public function detectLoginFailures(User $user): ?AnomalyAlert
    {
        $failures = AuditLog::query()
            ->where('module', 'security')
            ->where('event', 'auth.login_failed')
            ->where('actor_user_id', $user->id)
            ->where('occurred_at', '>=', now()->subHour())
            ->count();
        if ($failures < 5) {
            return null;
        }

        return $this->flag($user, 'login_failed_threshold', 'Login gagal berulang', 'Ada login gagal berulang dalam satu jam terakhir.', 'medium', $failures, ['failures_last_hour' => $failures]);
    }

    public function resolve(AnomalyAlert $alert, User $actor, string $status, ?string $note = null): AnomalyAlert
    {
        return DB::transaction(function () use ($alert, $actor, $status, $note): AnomalyAlert {
            $alert = AnomalyAlert::query()->lockForUpdate()->findOrFail($alert->id);
            $alert->forceFill([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'resolved_by' => in_array($status, [AnomalyStatus::RESOLVED->value, AnomalyStatus::FALSE_POSITIVE->value], true) ? $actor->id : null,
                'resolved_at' => in_array($status, [AnomalyStatus::RESOLVED->value, AnomalyStatus::FALSE_POSITIVE->value], true) ? now() : null,
                'resolution_note' => $note,
            ])->save();

            return $alert->fresh(['subject', 'assignee']);
        });
    }
}
