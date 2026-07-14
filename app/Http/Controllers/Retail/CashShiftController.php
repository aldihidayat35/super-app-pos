<?php

namespace App\Http\Controllers\Retail;

use App\Enums\CashShiftStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\ApproveCashShiftRequest;
use App\Http\Requests\Retail\RejectCashShiftRequest;
use App\Http\Requests\Retail\StoreCashShiftExpenseRequest;
use App\Http\Requests\Retail\StoreCashShiftRequest;
use App\Http\Requests\Retail\SubmitCashShiftClosingRequest;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\ShiftExpense;
use App\Services\Retail\CashShiftService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashShiftController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('cash_shifts.view'), 403);

        return view('retail.shifts.index', [
            'shifts' => CashShift::query()
                ->with(['branch', 'cashier'])
                ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
                ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
                ->when($request->filled('date_from'), fn ($query) => $query->whereDate('opened_at', '>=', $request->query('date_from')))
                ->when($request->filled('date_to'), fn ($query) => $query->whereDate('opened_at', '<=', $request->query('date_to')))
                ->latest('opened_at')
                ->paginate(15)
                ->withQueryString(),
            'branches' => Branch::query()->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'statuses' => CashShiftStatus::cases(),
            'filters' => $request->only(['status', 'branch_id', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('cash_shifts.view'), 403);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nomor', 'Cabang', 'Kasir', 'Status', 'Modal', 'Tunai', 'Non Tunai', 'Expense', 'Expected', 'Actual', 'Selisih', 'Opened', 'Closed']);
            CashShift::query()
                ->with(['branch', 'cashier'])
                ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
                ->latest('opened_at')
                ->each(function (CashShift $shift) use ($handle): void {
                    fputcsv($handle, [$shift->number, $shift->branch?->name, $shift->cashier?->name, $shift->status->label(), $shift->opening_cash_amount, $shift->cash_sales_amount, $shift->non_cash_sales_amount, $shift->expense_amount, $shift->expected_cash_amount, $shift->actual_cash_amount, $shift->difference_amount, $shift->opened_at, $shift->closed_at]);
                });
            fclose($handle);
        }, 'cash-shifts.csv');
    }

    public function open(Request $request): View
    {
        abort_unless($request->user()->can('cash_shifts.create'), 403);

        return view('retail.shifts.open', [
            'branches' => Branch::query()->where('is_active', true)->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCashShiftRequest $request, CashShiftService $service): RedirectResponse
    {
        try {
            $shift = $service->open($request->validated(), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['shift' => $exception->getMessage()]);
        }

        return redirect()->route('retail.shifts.current')
            ->with('notification', ['type' => 'success', 'message' => "Shift {$shift->number} berhasil dibuka."]);
    }

    public function current(Request $request, CashShiftService $service): View
    {
        abort_unless($request->user()->can('cash_shifts.view'), 403);
        $shift = $service->current($request->user());

        return view('retail.shifts.current', [
            'shift' => $shift?->load(['branch', 'cashier', 'expenses']),
            'summary' => $shift instanceof CashShift ? $service->summary($shift) : null,
        ]);
    }

    public function expenses(CashShift $shift, CashShiftService $service): View
    {
        $this->authorize('expense', $shift);

        return view('retail.shifts.expenses', [
            'shift' => $shift->load(['branch', 'cashier']),
            'expenses' => ShiftExpense::query()->where('cash_shift_id', $shift->id)->with('creator')->latest('spent_at')->paginate(15),
            'summary' => $service->summary($shift),
        ]);
    }

    public function storeExpense(StoreCashShiftExpenseRequest $request, CashShift $shift, CashShiftService $service): RedirectResponse
    {
        $this->authorize('expense', $shift);

        try {
            $service->addExpense($shift, $request->validated(), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['expense' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Pengeluaran kecil berhasil dicatat.']);
    }

    public function close(CashShift $shift, CashShiftService $service): View
    {
        $this->authorize('close', $shift);

        return view('retail.shifts.close', [
            'shift' => $shift->load(['branch', 'cashier']),
            'summary' => $service->summary($shift),
            'denominations' => [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100],
        ]);
    }

    public function submitClose(SubmitCashShiftClosingRequest $request, CashShift $shift, CashShiftService $service): RedirectResponse
    {
        $this->authorize('close', $shift);

        try {
            $service->submitClosing($shift, $request->validated(), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['closing' => $exception->getMessage()]);
        }

        return redirect()->route('retail.shifts.current')
            ->with('notification', ['type' => 'success', 'message' => 'Closing shift berhasil diajukan untuk verifikasi.']);
    }

    public function approval(CashShift $shift, CashShiftService $service): View
    {
        $this->authorize('approve', $shift);

        return view('retail.shifts.approval', [
            'shift' => $shift->load(['branch', 'cashier', 'expenses', 'cashCounts', 'sales.payments', 'approvals.actor']),
            'summary' => $service->summary($shift),
        ]);
    }

    public function approve(ApproveCashShiftRequest $request, CashShift $shift, CashShiftService $service): RedirectResponse
    {
        $this->authorize('approve', $shift);

        try {
            $service->approve($shift, $request->user(), $request->validated()['notes'] ?? null);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return redirect()->route('retail.shifts.index')->with('notification', ['type' => 'success', 'message' => 'Closing shift disetujui dan dikunci.']);
    }

    public function reject(RejectCashShiftRequest $request, CashShift $shift, CashShiftService $service): RedirectResponse
    {
        $this->authorize('approve', $shift);

        try {
            $service->reject($shift, $request->user(), $request->validated()['notes']);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Closing shift ditolak untuk diperbaiki kasir.']);
    }

    public function report(CashShift $shift, CashShiftService $service): View
    {
        $this->authorize('view', $shift);

        return view('retail.shifts.report', [
            'shift' => $shift->load(['branch', 'cashier', 'expenses', 'cashCounts', 'sales.payments']),
            'summary' => $service->summary($shift),
        ]);
    }
}
