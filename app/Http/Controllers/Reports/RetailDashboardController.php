<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Reports\ReportMetricService;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RetailDashboardController extends Controller
{
    public function index(ReportFilterRequest $request, ReportMetricService $reports): View
    {
        $this->ensureDirectDashboardAccess($request);

        return $this->renderForUser($request, $reports);
    }

    public function renderForUser(Request $request, ReportMetricService $reports): View
    {
        [$branches, $activeBranch, $canSelectBranch] = $this->resolveContext($request);
        $payload = $this->dashboardPayload($request, $reports, $activeBranch);

        return view('dashboards.retail', $payload + [
            'branches' => $branches,
            'activeBranch' => $activeBranch,
            'canSelectBranch' => $canSelectBranch,
        ]);
    }

    public function data(ReportFilterRequest $request, ReportMetricService $reports): JsonResponse
    {
        $this->ensureDirectDashboardAccess($request);

        [, $activeBranch] = $this->resolveContext($request);
        $payload = $this->dashboardPayload($request, $reports, $activeBranch);

        return response()->json([
            'branch_id' => $activeBranch?->id,
            'branch_name' => $activeBranch?->name,
            'work_location_id' => $activeBranch?->work_location_id,
            'html' => view('dashboards.partials.retail-content', $payload)->render(),
            'kpis' => $payload['dashboard']['kpis'],
            'charts' => $payload['dashboard']['charts'],
            'last_updated_at' => $payload['dashboard']['last_updated_at']->toIso8601String(),
        ]);
    }

    /**
     * @return array{0: Collection<int, Branch>, 1: Branch|null, 2: bool}
     */
    private function resolveContext(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $branches = Branch::query()
            ->with('workLocation')
            ->where('is_active', true)
            ->whereNotNull('work_location_id')
            ->whereIn('work_location_id', $user->permittedWorkLocationIds())
            ->orderBy('name')
            ->get();

        $requestedBranchId = $request->integer('branch_id');
        $activeBranch = $requestedBranchId > 0
            ? $branches->firstWhere('id', $requestedBranchId)
            : $branches->first();

        if ($requestedBranchId > 0 && ! $activeBranch) {
            abort(403, 'Cabang tidak termasuk dalam cakupan akses Anda.');
        }

        $canSelectBranch = $branches->isNotEmpty()
            && ($user->hasUnrestrictedLocationScope() || $branches->count() > 1);

        return [$branches, $activeBranch, $canSelectBranch];
    }

    /** @return array<string, mixed> */
    private function dashboardPayload(Request $request, ReportMetricService $reports, ?Branch $activeBranch): array
    {
        /** @var User $user */
        $user = $request->user();
        $input = $request->only(['start_date', 'end_date', 'range']);
        $input['work_location_id'] = $activeBranch === null ? -1 : $activeBranch->work_location_id;
        $filters = $reports->filters($user, $input);
        $dashboard = $reports->retailDashboard($user, $filters);
        $canViewSensitiveMargin = $user->can('margins.view_sensitive');

        if (! $canViewSensitiveMargin) {
            unset($dashboard['kpis']['margin'], $dashboard['kpis']['margin_percent'], $dashboard['kpis']['stock_value']);
            $dashboard['charts']['slow_products'] = $this->withoutSensitiveStockValues($dashboard['charts']['slow_products'] ?? []);
        }

        $salesTarget = Decimal::normalize((string) ($activeBranch === null ? 0 : $activeBranch->sales_target), 2);
        $targetAchievement = Decimal::compare($salesTarget, '0', 2) > 0
            ? Decimal::mul(Decimal::div($dashboard['kpis']['revenue'], $salesTarget, 4, 2, 4), '100', 4, 2, 2)
            : '0.00';

        $dashboard['kpis']['sales_target'] = $salesTarget;
        $dashboard['kpis']['target_achievement'] = $targetAchievement;

        return [
            'filters' => $filters,
            'dashboard' => $dashboard,
            'definitions' => $reports->definitions('retail'),
            'activeBranch' => $activeBranch,
            'canViewSensitiveMargin' => $canViewSensitiveMargin,
        ];
    }

    private function ensureDirectDashboardAccess(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless(
            $user?->can('cash_shifts.view')
                || $user?->can('reports.view')
                || ($user?->can('dashboard.view') && $user->hasUnrestrictedLocationScope()),
            403
        );
    }

    /** @return list<array<string, mixed>> */
    private function withoutSensitiveStockValues(mixed $products): array
    {
        if (! is_array($products)) {
            return [];
        }

        $sanitized = [];
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            unset($product['stock_value']);
            $sanitized[] = $product;
        }

        return $sanitized;
    }
}
