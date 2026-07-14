<?php

namespace App\Http\Controllers\Receivables;

use App\Enums\CreditLimitStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceivableStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Receivables\StoreCollectionNoteRequest;
use App\Http\Requests\Receivables\StoreCreditNoteRequest;
use App\Http\Requests\Receivables\StoreReceivablePaymentRequest;
use App\Http\Requests\Receivables\UpdateCreditLimitRequest;
use App\Models\CashShift;
use App\Models\CollectionNote;
use App\Models\CreditLimit;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Receivable;
use App\Services\Receivables\ReceivableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReceivableController extends Controller
{
    public function dashboard(Request $request, ReceivableService $service): View
    {
        $this->authorize('viewAny', Receivable::class);
        $service->refreshAging();

        $base = Receivable::query()->where('outstanding_amount', '>', 0);

        return view('receivables.dashboard', [
            'total' => (clone $base)->sum('outstanding_amount'),
            'notDue' => (clone $base)->where('aging_bucket', 'not_due')->sum('outstanding_amount'),
            'overdue' => (clone $base)->whereNot('aging_bucket', 'not_due')->sum('outstanding_amount'),
            'todayDue' => (clone $base)->whereDate('due_date', now()->toDateString())->sum('outstanding_amount'),
            'paidToday' => DB::table('receivable_entries')->where('entry_type', 'payment')->whereDate('occurred_at', now()->toDateString())->sum(DB::raw('ABS(amount)')),
            'warehouseTotal' => (clone $base)->where('channel', 'warehouse')->sum('outstanding_amount'),
            'retailTotal' => (clone $base)->where('channel', 'retail')->sum('outstanding_amount'),
            'aging' => (clone $base)->select('aging_bucket', DB::raw('SUM(outstanding_amount) as total'))->groupBy('aging_bucket')->pluck('total', 'aging_bucket'),
            'overLimitCustomers' => Customer::query()->whereColumn('receivable_balance', '>', 'credit_limit')->count(),
        ]);
    }

    public function index(Request $request, ReceivableService $service): View
    {
        $this->authorize('viewAny', Receivable::class);
        $service->refreshAging();

        return view('receivables.index', [
            'statuses' => ReceivableStatus::options(),
            'receivables' => Receivable::query()
                ->with(['customer', 'workLocation', 'invoice'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->when($request->filled('aging'), fn ($query) => $query->where('aging_bucket', $request->string('aging')))
                ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->string('channel')))
                ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
                ->when($request->filled('from'), fn ($query) => $query->whereDate('issue_date', '>=', $request->date('from')))
                ->when($request->filled('to'), fn ($query) => $query->whereDate('issue_date', '<=', $request->date('to')))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function customer(Customer $customer): View
    {
        $this->authorize('viewAny', Receivable::class);

        return view('receivables.customer', [
            'customer' => $customer->load(['creditLimit', 'receivables.entries', 'receivables.collectionNotes']),
            'receivables' => $customer->receivables()->with('entries')->latest('id')->paginate(15),
            'notes' => CollectionNote::query()->where('customer_id', $customer->id)->latest('id')->limit(20)->get(),
        ]);
    }

    public function paymentCreate(Request $request): View
    {
        $this->authorize('pay', Receivable::class);
        $customerId = $request->integer('customer_id') ?: null;

        return view('receivables.payments.create', [
            'methods' => PaymentMethod::options(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('business_name')->limit(100)->get(),
            'selectedCustomerId' => $customerId,
            'receivables' => Receivable::query()
                ->with('customer')
                ->where('outstanding_amount', '>', 0)
                ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
                ->orderBy('due_date')
                ->get(),
        ]);
    }

    public function paymentStore(StoreReceivablePaymentRequest $request, ReceivableService $service): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($request->integer('customer_id'));
        $data = $request->validated();
        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')?->store('receivable-payments');
        }
        $shift = CashShift::query()->where('cashier_user_id', $request->user()->id)->where('status', 'open')->latest('id')->first();

        try {
            $payment = $service->recordPayment($customer, $data['allocations'], $data, $request->user(), $shift);
        } catch (ServiceException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('receivables.customers.show', $customer)->with('notification', ['type' => 'success', 'message' => 'Pembayaran piutang '.$payment->number.' berhasil dicatat.']);
    }

    public function reminders(): View
    {
        $this->authorize('viewAny', Receivable::class);

        return view('receivables.reminders', [
            'receivables' => Receivable::query()->with(['customer', 'collectionNotes'])->where('outstanding_amount', '>', 0)->whereDate('due_date', '<=', now()->addDays(3)->toDateString())->orderBy('due_date')->paginate(15),
            'notes' => CollectionNote::query()->with('customer')->latest('id')->limit(20)->get(),
        ]);
    }

    public function storeReminder(StoreCollectionNoteRequest $request, ReceivableService $service): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($request->integer('customer_id'));
        $receivable = $request->filled('receivable_id') ? Receivable::query()->findOrFail($request->integer('receivable_id')) : null;
        $service->addCollectionNote($customer, $receivable, $request->validated(), $request->user());

        return back()->with('notification', ['type' => 'success', 'message' => 'Catatan penagihan berhasil disimpan.']);
    }

    public function creditLimits(): View
    {
        $this->authorize('manageLimit', Receivable::class);

        return view('receivables.credit-limits', [
            'statuses' => CreditLimitStatus::cases(),
            'limits' => CreditLimit::query()->with('customer')->latest('id')->paginate(15),
        ]);
    }

    public function updateCreditLimit(UpdateCreditLimitRequest $request, CreditLimit $creditLimit): RedirectResponse
    {
        $data = $request->validated();
        $data['blocked_at'] = $data['status'] === CreditLimitStatus::BLOCKED->value ? now() : null;
        $data['blocked_by'] = $data['status'] === CreditLimitStatus::BLOCKED->value ? $request->user()->id : null;
        $creditLimit->forceFill($data)->save();
        $creditLimit->customer?->forceFill(['credit_limit' => $data['credit_limit'], 'payment_term_days' => $data['payment_term_days']])->save();

        return back()->with('notification', ['type' => 'success', 'message' => 'Limit kredit berhasil diperbarui.']);
    }

    public function retail(Request $request): View
    {
        $this->authorize('viewAny', Receivable::class);

        return view('receivables.retail', [
            'receivables' => Receivable::query()->with(['customer', 'workLocation', 'posSale'])->where('channel', 'retail')->latest('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function adjustments(Receivable $receivable): View
    {
        $this->authorize('view', $receivable);

        return view('receivables.adjustments', ['receivable' => $receivable->load(['customer', 'entries']), 'creditNotes' => CreditNote::query()->where('receivable_id', $receivable->id)->latest('id')->get()]);
    }

    public function storeAdjustment(StoreCreditNoteRequest $request, Receivable $receivable, ReceivableService $service): RedirectResponse
    {
        try {
            $service->createCreditNote($receivable, (string) $request->validated('amount'), (string) $request->validated('reason'), $request->user());
        } catch (ServiceException $exception) {
            return back()->withErrors(['adjustment' => $exception->getMessage()])->withInput();
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Credit note dibuat dan menunggu approval.']);
    }

    public function approveAdjustment(Request $request, CreditNote $creditNote, ReceivableService $service): RedirectResponse
    {
        $this->authorize('adjust', Receivable::class);
        $service->approveCreditNote($creditNote, $request->user(), $request->input('approval_note'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Credit note disetujui dan saldo piutang dikoreksi.']);
    }
}
