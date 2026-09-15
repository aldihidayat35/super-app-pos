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
use App\Support\Decimal;
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

        $channel = in_array($request->string('channel')->toString(), ['warehouse', 'retail'], true)
            ? $request->string('channel')->toString()
            : null;
        $rangeDays = in_array($request->integer('range'), [7, 30, 90], true) ? $request->integer('range') : 30;
        $today = now()->startOfDay();
        $periodStart = $today->copy()->subDays($rangeDays - 1);

        $base = Receivable::query()
            ->where('outstanding_amount', '>', 0)
            ->when($channel, fn ($query, $value) => $query->where('channel', $value));

        $total = (string) (clone $base)->sum('outstanding_amount');
        $notDue = (string) (clone $base)->where('aging_bucket', 'not_due')->sum('outstanding_amount');
        $overdue = (string) (clone $base)->whereNot('aging_bucket', 'not_due')->sum('outstanding_amount');

        $agingAggregates = (clone $base)
            ->select('aging_bucket', DB::raw('COUNT(*) as document_count'), DB::raw('SUM(outstanding_amount) as total'))
            ->groupBy('aging_bucket')
            ->get()
            ->keyBy('aging_bucket');
        $agingRows = collect([
            'not_due' => ['label' => 'Belum jatuh tempo', 'tone' => 'success'],
            '1_7' => ['label' => 'Terlambat 1–7 hari', 'tone' => 'warning'],
            '8_30' => ['label' => 'Terlambat 8–30 hari', 'tone' => 'orange'],
            '31_60' => ['label' => 'Terlambat 31–60 hari', 'tone' => 'danger'],
            'over_60' => ['label' => 'Terlambat > 60 hari', 'tone' => 'dark'],
        ])->map(function (array $meta, string $bucket) use ($agingAggregates, $total): array {
            $aggregate = $agingAggregates->get($bucket);
            $amount = (string) data_get($aggregate, 'total', '0');

            return [
                'bucket' => $bucket,
                'label' => $meta['label'],
                'tone' => $meta['tone'],
                'amount' => $amount,
                'document_count' => (int) data_get($aggregate, 'document_count', 0),
                'percentage' => $this->percentage($amount, $total),
            ];
        })->values();

        $channelAggregates = (clone $base)
            ->select('channel', DB::raw('COUNT(*) as document_count'), DB::raw('SUM(outstanding_amount) as total'))
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');
        $channelRows = collect([
            'warehouse' => ['label' => 'Gudang / B2B', 'tone' => 'primary'],
            'retail' => ['label' => 'Toko Internal', 'tone' => 'info'],
        ])->map(function (array $meta, string $key) use ($channelAggregates, $total): array {
            $aggregate = $channelAggregates->get($key);
            $amount = (string) data_get($aggregate, 'total', '0');

            return [
                'key' => $key,
                'label' => $meta['label'],
                'tone' => $meta['tone'],
                'amount' => $amount,
                'document_count' => (int) data_get($aggregate, 'document_count', 0),
                'percentage' => $this->percentage($amount, $total),
            ];
        })->values();

        $flowQuery = DB::table('receivable_entries')
            ->join('receivables', 'receivables.id', '=', 'receivable_entries.receivable_id')
            ->whereIn('receivable_entries.entry_type', ['invoice', 'payment'])
            ->whereBetween('receivable_entries.occurred_at', [$periodStart, $today->copy()->endOfDay()])
            ->when($channel, fn ($query, $value) => $query->where('receivables.channel', $value));
        $flowRows = (clone $flowQuery)
            ->selectRaw('DATE(receivable_entries.occurred_at) as flow_date')
            ->selectRaw("SUM(CASE WHEN receivable_entries.entry_type = 'invoice' THEN ABS(receivable_entries.amount) ELSE 0 END) as invoiced")
            ->selectRaw("SUM(CASE WHEN receivable_entries.entry_type = 'payment' THEN ABS(receivable_entries.amount) ELSE 0 END) as paid")
            ->groupByRaw('DATE(receivable_entries.occurred_at)')
            ->get()
            ->keyBy('flow_date');
        $flowTrend = collect(range(0, $rangeDays - 1))->map(function (int $offset) use ($periodStart, $flowRows): array {
            $date = $periodStart->copy()->addDays($offset);
            $row = $flowRows->get($date->toDateString());

            return [
                'date' => $date->translatedFormat('d M'),
                'invoiced' => (string) data_get($row, 'invoiced', '0'),
                'paid' => (string) data_get($row, 'paid', '0'),
            ];
        });
        $periodInvoiced = (string) (clone $flowQuery)->where('receivable_entries.entry_type', 'invoice')->sum(DB::raw('ABS(receivable_entries.amount)'));
        $periodPaid = (string) (clone $flowQuery)->where('receivable_entries.entry_type', 'payment')->sum(DB::raw('ABS(receivable_entries.amount)'));

        $priorityReceivables = (clone $base)
            ->with('customer')
            ->whereDate('due_date', '<=', $today->toDateString())
            ->orderBy('due_date')
            ->orderByDesc('outstanding_amount')
            ->limit(7)
            ->get();

        $riskCustomers = Receivable::query()
            ->with('customer')
            ->where('outstanding_amount', '>', 0)
            ->whereDate('due_date', '<', $today->toDateString())
            ->when($channel, fn ($query, $value) => $query->where('channel', $value))
            ->select('customer_id', DB::raw('COUNT(*) as document_count'), DB::raw('SUM(outstanding_amount) as overdue_total'), DB::raw('MIN(due_date) as oldest_due_date'))
            ->groupBy('customer_id')
            ->orderByDesc('overdue_total')
            ->limit(6)
            ->get();

        $followUpQuery = CollectionNote::query()
            ->whereNotNull('next_follow_up_date')
            ->whereDate('next_follow_up_date', '<=', $today->toDateString())
            ->when($channel, fn ($query, $value) => $query->whereHas('receivable', fn ($receivable) => $receivable->where('channel', $value)));
        $followUpCount = (clone $followUpQuery)->count();
        $followUps = $followUpQuery
            ->with(['customer', 'receivable'])
            ->orderBy('next_follow_up_date')
            ->limit(6)
            ->get();

        $overLimitCustomers = Customer::query()
            ->whereColumn('receivable_balance', '>', 'credit_limit')
            ->when($channel, fn ($query, $value) => $query->whereHas('receivables', fn ($receivable) => $receivable->where('channel', $value)->where('outstanding_amount', '>', 0)))
            ->count();

        return view('receivables.dashboard', [
            'filters' => ['channel' => $channel, 'range' => $rangeDays],
            'summary' => [
                'total' => $total,
                'not_due' => $notDue,
                'overdue' => $overdue,
                'overdue_percentage' => $this->percentage($overdue, $total),
                'due_today' => (string) (clone $base)->whereDate('due_date', $today->toDateString())->sum('outstanding_amount'),
                'due_next_7_days' => (string) (clone $base)->whereBetween('due_date', [$today->copy()->addDay()->toDateString(), $today->copy()->addDays(7)->toDateString()])->sum('outstanding_amount'),
                'paid_today' => (string) DB::table('receivable_entries')->join('receivables', 'receivables.id', '=', 'receivable_entries.receivable_id')->where('receivable_entries.entry_type', 'payment')->whereDate('receivable_entries.occurred_at', $today->toDateString())->when($channel, fn ($query, $value) => $query->where('receivables.channel', $value))->sum(DB::raw('ABS(receivable_entries.amount)')),
                'period_paid' => $periodPaid,
                'period_invoiced' => $periodInvoiced,
                'collection_ratio' => $this->percentage($periodPaid, $periodInvoiced),
                'open_documents' => (clone $base)->count(),
                'open_customers' => (clone $base)->distinct()->count('customer_id'),
                'over_limit_customers' => $overLimitCustomers,
                'follow_ups_due' => $followUpCount,
            ],
            'agingRows' => $agingRows,
            'channelRows' => $channelRows,
            'flowTrend' => $flowTrend,
            'priorityReceivables' => $priorityReceivables,
            'riskCustomers' => $riskCustomers,
            'followUps' => $followUps,
            'refreshedAt' => now(),
        ]);
    }

    private function percentage(string $part, string $whole): string
    {
        if (! Decimal::isPositive($whole, 2)) {
            return '0.0';
        }

        $ratio = Decimal::div($part, $whole, 2, 2, 4);

        return Decimal::mul($ratio, '100', 4, 0, 1);
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
        $this->authorize('approveAdjustment', $creditNote->receivable);
        $service->approveCreditNote($creditNote, $request->user(), $request->input('approval_note'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Credit note disetujui dan saldo piutang dikoreksi.']);
    }
}
