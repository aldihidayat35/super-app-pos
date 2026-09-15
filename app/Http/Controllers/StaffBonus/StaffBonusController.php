<?php

namespace App\Http\Controllers\StaffBonus;

use App\Enums\StaffBonusPeriodStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffBonus\DecideStaffBonusRequest;
use App\Http\Requests\StaffBonus\PayStaffBonusRequest;
use App\Http\Requests\StaffBonus\StoreStaffBonusAdjustmentRequest;
use App\Http\Requests\StaffBonus\StoreStaffBonusProgramRequest;
use App\Models\SalesBonus;
use App\Models\StaffBonusAdjustment;
use App\Models\StaffBonusPeriod;
use App\Models\StaffBonusProgram;
use App\Models\StaffBonusResult;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Control\ApprovalWorkflowService;
use App\Services\Control\AuditLogService;
use App\Services\StaffBonus\StaffBonusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffBonusController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('staff_bonuses.view_own') || $request->user()->can('staff_bonuses.view_team') || $request->user()->can('staff_bonuses.view_all'), 403);
        $user = $request->user();
        $tab = (string) $request->query('tab', 'mine');
        $base = StaffBonusResult::query()->with(['user', 'workLocation', 'period.program', 'metrics', 'payment']);
        $mine = (clone $base)->where('user_id', $user->id)->latest('id')->paginate(12, ['*'], 'mine_page');
        $team = null;
        if ($user->can('staff_bonuses.view_team') || $user->can('staff_bonuses.view_all')) {
            $teamQuery = clone $base;
            if (! $user->can('staff_bonuses.view_all')) {
                /** @var array<string, list<string>> $teamRoles */
                $teamRoles = config('staff-bonuses.team_roles');
                $roles = collect($teamRoles)->only($user->roles->pluck('name')->all())->flatten()->unique();
                $teamQuery->whereIn('role_name', $roles)->whereIn('work_location_id', $user->permittedWorkLocationIds());
            }
            $team = $this->applyFilters($teamQuery, $request)->latest('id')->paginate(20, ['*'], 'team_page');
        }
        $programs = $user->can('staff_bonuses.manage') ? StaffBonusProgram::query()->with(['workLocation', 'metrics', 'assignments.user', 'period'])->latest('id')->paginate(15, ['*'], 'program_page') : null;
        $pending = $user->can('staff_bonuses.approve') || $user->can('staff_bonuses.pay')
            ? StaffBonusPeriod::query()->with(['program', 'workLocation', 'results.user', 'results.payment'])->whereIn('status', [StaffBonusPeriodStatus::PENDING_APPROVAL->value, StaffBonusPeriodStatus::APPROVED->value])->latest('id')->get() : collect();
        $locations = WorkLocation::query()->where('is_active', true)->orderBy('name')->get();
        $eligibleUsers = User::query()->with(['roles', 'employee.workLocation'])->where('is_active', true)->whereHas('employee', fn ($q) => $q->where('is_active', true))->role(config('staff-bonuses.eligible_roles'))->orderBy('name')->get();
        $legacy = SalesBonus::query()->with('sales')->when(! $user->can('staff_bonuses.view_all'), fn ($q) => $q->where('sales_user_id', $user->id))->latest('year')->latest('month')->limit(24)->get();
        $recapSummary = ['recipients' => 0, 'approved_amount' => '0.00', 'paid_amount' => '0.00'];
        if ($user->can('staff_bonuses.view_all')) {
            $recapQuery = $this->applyFilters(StaffBonusResult::query(), $request);
            $recapSummary = [
                'recipients' => (clone $recapQuery)->count(),
                'approved_amount' => (string) (clone $recapQuery)->whereHas('period', fn ($q) => $q->whereIn('status', [StaffBonusPeriodStatus::APPROVED->value, StaffBonusPeriodStatus::CLOSED->value]))->sum('bonus_amount'),
                'paid_amount' => (string) (clone $recapQuery)->where('payment_status', 'paid')->sum('bonus_amount'),
            ];
        }
        $selected = $request->filled('result_id') ? StaffBonusResult::query()->with(['user', 'period.program', 'metrics', 'payment'])->findOrFail($request->integer('result_id')) : null;
        if ($selected) {
            $this->authorize('view', $selected);
        }
        $adjustmentTargets = $selected && $selected->payment_status === 'paid' && $user->can('staff_bonuses.manage')
            ? StaffBonusResult::query()->with('period.program')->where('user_id', $selected->user_id)->where('payment_status', 'unpaid')->whereHas('period', function ($query) use ($selected): void {
                $sourcePeriod = $selected->period;
                $query->whereIn('status', [StaffBonusPeriodStatus::ACTIVE->value, StaffBonusPeriodStatus::REJECTED->value])
                    ->whereRaw('(year * 100 + month) > ?', [($sourcePeriod->year * 100) + $sourcePeriod->month]);
            })->get() : collect();

        return view('staff-bonuses.index', compact('tab', 'mine', 'team', 'programs', 'pending', 'locations', 'eligibleUsers', 'legacy', 'selected', 'adjustmentTargets', 'recapSummary'));
    }

    public function store(StoreStaffBonusProgramRequest $request, StaffBonusService $service): RedirectResponse
    {
        $program = $service->createProgram($request->validated(), $request->user(), $request);

        return redirect()->route('staff-bonuses.index', ['tab' => 'programs'])->with('notification', ['type' => 'success', 'message' => "Program {$program->name} berhasil dibuat sebagai draft."]);
    }

    public function activate(Request $request, StaffBonusProgram $program, StaffBonusService $service): RedirectResponse
    {
        abort_unless($request->user()->can('staff_bonuses.manage'), 403);
        $service->activate($program, $request->user(), $request);

        return back()->with('notification', ['type' => 'success', 'message' => 'Program bonus berhasil diaktifkan dan akun penerima telah dibekukan.']);
    }

    public function calculate(Request $request, StaffBonusPeriod $period, StaffBonusService $service): RedirectResponse
    {
        abort_unless($request->user()->can('staff_bonuses.manage'), 403);
        $service->calculate($period);

        return back()->with('notification', ['type' => 'success', 'message' => 'Progres bonus berhasil dihitung ulang.']);
    }

    public function submit(Request $request, StaffBonusPeriod $period, StaffBonusService $service): RedirectResponse
    {
        abort_unless($request->user()->can('staff_bonuses.manage'), 403);
        $service->submit($period, $request->user(), $request);

        return back()->with('notification', ['type' => 'success', 'message' => 'Hasil bonus berhasil diajukan kepada Owner.']);
    }

    public function approve(DecideStaffBonusRequest $request, StaffBonusPeriod $period, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_if($period->approval_request_id === null, 422, 'Pengajuan approval tidak ditemukan.');
        $approval = $period->approvalRequest()->firstOrFail();
        $workflow->approve($approval, $request->user(), $request->validated('note'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Bonus staf berhasil disetujui.']);
    }

    public function reject(DecideStaffBonusRequest $request, StaffBonusPeriod $period, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);
        abort_if($period->approval_request_id === null, 422, 'Pengajuan approval tidak ditemukan.');
        $approval = $period->approvalRequest()->firstOrFail();
        $workflow->reject($approval, $request->user(), $request->string('note')->toString());

        return back()->with('notification', ['type' => 'success', 'message' => 'Hasil bonus ditolak dan dapat dihitung ulang.']);
    }

    public function pay(PayStaffBonusRequest $request, StaffBonusResult $result, AuditLogService $audit): RedirectResponse
    {
        $period = $result->period;
        abort_unless(in_array($period->status, [StaffBonusPeriodStatus::APPROVED, StaffBonusPeriodStatus::CLOSED], true), 422, 'Bonus belum disetujui.');
        abort_if($result->payment !== null || $result->payment_status === 'paid', 422, 'Bonus sudah dibayar.');
        DB::transaction(function () use ($request, $result, $audit): void {
            $path = $request->file('proof')?->store('staff-bonus-payments', 'public');
            $result->payment()->create(['amount' => $result->bonus_amount, 'paid_on' => $request->validated('paid_on'), 'payment_method' => $request->validated('payment_method'), 'reference_no' => $request->validated('reference_no'), 'proof_path' => $path, 'notes' => $request->validated('notes'), 'paid_by' => $request->user()->id, 'recorded_at' => now()]);
            $result->forceFill(['payment_status' => 'paid'])->save();
            if (! $result->period->results()->where('payment_status', '!=', 'paid')->exists()) {
                $result->period->forceFill(['status' => StaffBonusPeriodStatus::CLOSED, 'closed_at' => now()])->save();
            }
            $audit->record('staff_bonus.paid', 'staff_bonuses', $request->user(), $result, [], ['amount' => $result->bonus_amount, 'reference_no' => $request->validated('reference_no')], request: $request, location: $result->workLocation);
        });

        return back()->with('notification', ['type' => 'success', 'message' => 'Pembayaran bonus berhasil dicatat.']);
    }

    public function adjust(StoreStaffBonusAdjustmentRequest $request, StaffBonusResult $result, StaffBonusService $service, AuditLogService $audit): RedirectResponse
    {
        abort_unless($result->payment_status === 'paid', 422, 'Penyesuaian hanya dibuat dari bonus yang sudah dibayar.');
        $target = StaffBonusResult::query()->with('period')->findOrFail($request->integer('target_result_id'));
        abort_unless((int) $target->user_id === (int) $result->user_id && $target->payment_status === 'unpaid', 422, 'Periode tujuan penyesuaian tidak valid.');
        $sourcePeriod = $result->period;
        $targetPeriod = $target->period;
        abort_unless((($targetPeriod->year * 100) + $targetPeriod->month) > (($sourcePeriod->year * 100) + $sourcePeriod->month), 422, 'Penyesuaian wajib diterapkan ke periode berikutnya.');
        abort_unless(in_array($targetPeriod->status, [StaffBonusPeriodStatus::ACTIVE, StaffBonusPeriodStatus::REJECTED], true), 422, 'Periode tujuan sudah dikunci.');
        DB::transaction(function () use ($request, $result, $target, $service, $audit): void {
            $adjustment = StaffBonusAdjustment::query()->create(['source_result_id' => $result->id, 'target_result_id' => $target->id, 'amount' => $request->validated('amount'), 'reason' => $request->validated('reason'), 'created_by' => $request->user()->id, 'applied_at' => now()]);
            $service->calculate($target->period);
            $audit->record('staff_bonus.adjusted', 'staff_bonuses', $request->user(), $adjustment, [], $adjustment->toArray(), $request->validated('reason'), $request, $target->workLocation, 'warning');
        });

        return back()->with('notification', ['type' => 'success', 'message' => 'Penyesuaian berhasil diterapkan pada periode berikutnya.']);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('staff_bonuses.export'), 403);
        $query = StaffBonusResult::query()->with(['user', 'workLocation', 'period.program', 'payment']);
        $this->applyFilters($query, $request);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Periode', 'Program', 'Akun', 'Role', 'Lokasi', 'Nilai', 'Bonus', 'Status Pembayaran', 'Referensi']);
            $query->orderBy('id')->chunk(200, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, [sprintf('%02d/%04d', $row->period->month, $row->period->year), $row->period->program->name, $row->user->name, $row->role_name, optional($row->workLocation)->name ?: 'Global', $row->final_score, $row->bonus_amount, $row->payment_status, $row->payment?->reference_no]);
                }
            });
            fclose($out);
        }, 'rekap-target-bonus.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param Builder<StaffBonusResult> $query
     * @return Builder<StaffBonusResult>
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        return $query->when($request->filled('year'), fn ($q) => $q->whereHas('period', fn ($p) => $p->where('year', $request->integer('year'))))
            ->when($request->filled('month'), fn ($q) => $q->whereHas('period', fn ($p) => $p->where('month', $request->integer('month'))))
            ->when($request->filled('role'), fn ($q) => $q->where('role_name', $request->string('role')->toString()))
            ->when($request->filled('work_location_id'), fn ($q) => $q->where('work_location_id', $request->integer('work_location_id')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')->toString()));
    }
}
