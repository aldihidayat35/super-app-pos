<?php

namespace App\Services\Sales;

use App\Enums\B2bOrderStatus;
use App\Models\B2bOrder;
use App\Models\SalesBonus;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type PerformanceMetrics array{sales_amount: string, target_amount: string, achievement_percentage: string, remaining_target: string, order_count: int, customer_count: int, bonus_amount: string, bonus_percentage: string, target: ?SalesTarget, bonus: ?SalesBonus}
 */
class SalesPerformanceService
{
    /** @return PerformanceMetrics */
    public function forUser(User $sales, int $month, int $year): array
    {
        [$start, $end] = $this->periodBounds($month, $year);

        $orders = B2bOrder::query()
            ->where('sales_user_id', $sales->id)
            ->where('status', B2bOrderStatus::COMPLETED->value)
            ->whereBetween('completed_at', [$start, $end]);

        $salesAmount = $this->money((string) (clone $orders)->sum('grand_total_amount'));
        $orderCount = (clone $orders)->count();
        $target = SalesTarget::query()
            ->where('sales_user_id', $sales->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $targetAmount = $this->money((string) ($target->target_amount ?? '0'));
        $bonusPercentage = $this->percentage((string) ($target->bonus_percentage ?? '0'));
        $achievement = bccomp($targetAmount, '0.00', 2) > 0
            ? bcmul(bcdiv($salesAmount, $targetAmount, 6), '100', 2)
            : '0.00';
        $remaining = bccomp($targetAmount, $salesAmount, 2) > 0
            ? bcsub($targetAmount, $salesAmount, 2)
            : '0.00';
        $bonusAmount = bccomp($salesAmount, $targetAmount, 2) >= 0 && bccomp($targetAmount, '0.00', 2) > 0
            ? bcmul($salesAmount, bcdiv($bonusPercentage, '100', 8), 2)
            : '0.00';

        $bonus = $target instanceof SalesTarget
            ? $this->snapshot($target, $salesAmount, $orderCount, $bonusAmount, $start)
            : null;

        if ($bonus?->finalized_at !== null) {
            $salesAmount = (string) $bonus->sales_amount;
            $orderCount = $bonus->order_count;
            $targetAmount = (string) $bonus->target_amount;
            $bonusPercentage = (string) $bonus->bonus_percentage;
            $bonusAmount = (string) $bonus->bonus_amount;
            $achievement = bccomp($targetAmount, '0.00', 2) > 0
                ? bcmul(bcdiv($salesAmount, $targetAmount, 6), '100', 2)
                : '0.00';
            $remaining = bccomp($targetAmount, $salesAmount, 2) > 0
                ? bcsub($targetAmount, $salesAmount, 2)
                : '0.00';
        }

        return [
            'sales_amount' => $salesAmount,
            'target_amount' => $targetAmount,
            'achievement_percentage' => $achievement,
            'remaining_target' => $remaining,
            'order_count' => $orderCount,
            'customer_count' => $sales->assignedSalesCustomers()->where('is_active', true)->count(),
            'bonus_amount' => $bonusAmount,
            'bonus_percentage' => $bonusPercentage,
            'target' => $target,
            'bonus' => $bonus,
        ];
    }

    /**
     * @return list<array{sales: User, metrics: PerformanceMetrics}>
     */
    public function all(int $month, int $year): array
    {
        return User::query()
            ->role('sales')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (User $sales): array => ['sales' => $sales, 'metrics' => $this->forUser($sales, $month, $year)])
            ->values()
            ->all();
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function periodBounds(int $month, int $year): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return [$start, $start->endOfMonth()];
    }

    private function snapshot(SalesTarget $target, string $salesAmount, int $orderCount, string $bonusAmount, CarbonImmutable $periodStart): SalesBonus
    {
        $bonus = SalesBonus::query()->firstOrNew(['sales_target_id' => $target->id]);
        $isPast = $periodStart->lt(CarbonImmutable::now()->startOfMonth());

        if ($bonus->exists && $bonus->finalized_at !== null) {
            return $bonus;
        }

        $bonus->fill([
            'sales_user_id' => $target->sales_user_id,
            'month' => $target->month,
            'year' => $target->year,
            'sales_amount' => $salesAmount,
            'order_count' => $orderCount,
            'target_amount' => $target->target_amount,
            'bonus_percentage' => $target->bonus_percentage,
            'bonus_amount' => $bonusAmount,
            'finalized_at' => $isPast ? now() : null,
            'created_by' => $target->created_by,
        ])->save();

        return $bonus->fresh();
    }

    private function money(string $value): string
    {
        return bcadd($value, '0', 2);
    }

    private function percentage(string $value): string
    {
        return bcadd($value, '0', 4);
    }
}
