<?php

namespace App\Http\Controllers\B2B;

use App\Enums\B2bOrderStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\B2B\CancelB2bOrderRequest;
use App\Models\B2bOrder;
use App\Services\B2B\B2bOrderWorkflowService;
use App\Services\B2B\B2bPortalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request, B2bPortalService $portal): View
    {
        $customer = $portal->activeCustomerFor($request->user());
        $this->authorize('viewAny', B2bOrder::class);
        $filters = ['status' => $request->query('status'), 'from' => $request->query('from'), 'to' => $request->query('to')];

        return view('b2b.orders.index', [
            'customer' => $customer,
            'filters' => $filters,
            'statuses' => B2bOrderStatus::options(),
            'orders' => B2bOrder::query()
                ->where('customer_id', $customer->id)
                ->withCount('items')
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['from'], fn ($query, $date) => $query->whereDate('submitted_at', '>=', $date))
                ->when($filters['to'], fn ($query, $date) => $query->whereDate('submitted_at', '<=', $date))
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function show(Request $request, B2bOrder $order, B2bPortalService $portal): View
    {
        $customer = $portal->activeCustomerFor($request->user());
        $this->authorize('view', $order);
        abort_unless((int) $order->customer_id === (int) $customer->id, 403);

        return view('b2b.orders.show', ['customer' => $customer, 'order' => $order->load(['items.product', 'address', 'requester', 'reservations.product', 'statusHistories.actor', 'messages.user', 'invoices', 'shipments'])]);
    }

    public function cancel(CancelB2bOrderRequest $request, B2bOrder $order, B2bPortalService $portal, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());
        $this->authorize('view', $order);
        abort_unless((int) $order->customer_id === (int) $customer->id, 403);

        try {
            $workflow->cancel($order, $request->user(), $request->validated('reason'));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['cancel' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Order berhasil dibatalkan.']);
    }

    public function receive(Request $request, B2bOrder $order, B2bPortalService $portal, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());
        $this->authorize('view', $order);
        abort_unless((int) $order->customer_id === (int) $customer->id, 403);

        try {
            $workflow->markReceived($order, $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['receive' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Order dikonfirmasi diterima.']);
    }
}
