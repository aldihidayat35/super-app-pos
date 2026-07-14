<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Exceptions\ServiceException;
use App\Models\B2bOrder;
use App\Models\Invoice;
use App\Services\B2B\B2bFulfillmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);
        $user = $request->user();
        $customerIds = $user?->hasOnlyB2bPortalRoles() ? $user->customers()->pluck('customers.id')->all() : null;

        return view('invoices.index', [
            'statuses' => InvoiceStatus::options(),
            'invoices' => Invoice::query()
                ->with(['customer', 'order'])
                ->when($customerIds !== null, fn ($query) => $query->whereIn('customer_id', $customerIds))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->when($request->filled('q'), fn ($query) => $query->where(function ($search) use ($request): void {
                    $search->where('number', 'like', '%'.$request->string('q').'%')
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('business_name', 'like', '%'.$request->string('q').'%'));
                }))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load(['items', 'customer', 'order.statusHistories', 'allocations.payment']),
        ]);
    }

    public function issueFromOrder(Request $request, B2bOrder $order, B2bFulfillmentService $service): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        try {
            $invoice = $service->issueInvoice($order, $request->user(), $request->filled('due_date') ? now()->parse($request->string('due_date')->toString()) : null);
        } catch (ServiceException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('invoices.show', $invoice)->with('notification', ['type' => 'success', 'message' => 'Invoice berhasil diterbitkan.']);
    }

    public function pdf(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Pdf::loadView('invoices.pdf', ['invoice' => $invoice->load(['items', 'customer', 'order'])])
            ->download($invoice->number.'.pdf');
    }
}
