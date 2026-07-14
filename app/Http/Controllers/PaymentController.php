<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\ServiceException;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\VerifyPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\B2B\B2bFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorize('create', Payment::class);
        $user = $request->user();
        $customerIds = $user?->hasOnlyB2bPortalRoles() ? $user->customers()->pluck('customers.id')->all() : null;

        return view('payments.create', [
            'methods' => PaymentMethod::options(),
            'invoices' => Invoice::query()
                ->with('customer')
                ->whereIn('status', ['issued', 'partial', 'overdue'])
                ->when($customerIds !== null, fn ($query) => $query->whereIn('customer_id', $customerIds))
                ->where('outstanding_amount', '>', 0)
                ->latest('id')
                ->get(),
            'selectedInvoiceId' => $request->integer('invoice_id') ?: null,
        ]);
    }

    public function store(StorePaymentRequest $request, B2bFulfillmentService $service): RedirectResponse
    {
        $invoice = Invoice::query()->findOrFail($request->integer('invoice_id'));
        $this->authorize('view', $invoice);

        $data = $request->validated();
        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')?->store('payment-proofs');
        }

        try {
            $payment = $service->recordPayment($invoice, $request->user(), $data);
        } catch (ServiceException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        }

        $target = $request->user()?->can('verify', Payment::class)
            ? route('payments.verify', $payment)
            : route('invoices.show', $invoice);

        return redirect($target)->with('notification', ['type' => 'success', 'message' => 'Pembayaran berhasil dicatat dan menunggu verifikasi.']);
    }

    public function verifyForm(Payment $payment): View
    {
        $this->authorize('verify', Payment::class);
        $this->authorize('view', $payment);

        return view('payments.verify', [
            'payment' => $payment->load(['allocations.invoice', 'customer']),
            'proofUrl' => $payment->proof_path ? URL::temporarySignedRoute('payments.proof', now()->addMinutes(30), ['payment' => $payment]) : null,
        ]);
    }

    public function verify(VerifyPaymentRequest $request, Payment $payment, B2bFulfillmentService $service): RedirectResponse
    {
        try {
            if ($request->validated('decision') === 'approve') {
                $service->verifyPayment($payment, $request->user());
                $message = 'Pembayaran berhasil diverifikasi.';
            } else {
                $service->rejectPayment($payment, $request->user(), (string) $request->validated('reject_reason'));
                $message = 'Pembayaran ditolak.';
            }
        } catch (ServiceException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('payments.verify', $payment)->with('notification', ['type' => 'success', 'message' => $message]);
    }

    public function proof(Payment $payment): Response
    {
        $this->authorize('view', $payment);
        abort_unless($payment->proof_path && Storage::exists($payment->proof_path), 404);

        return Storage::download($payment->proof_path);
    }
}
