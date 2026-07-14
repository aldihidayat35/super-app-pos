<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\B2bOrderStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\RejectB2bOrderRequest;
use App\Http\Requests\Warehouse\ReviewB2bOrderRequest;
use App\Http\Requests\Warehouse\ShipB2bOrderRequest;
use App\Models\B2bOrder;
use App\Services\B2B\B2bOrderWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class B2bOrderController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('b2b_orders.view'), 403);
        $filters = ['status' => $request->query('status'), 'q' => trim((string) $request->query('q'))];

        return view('warehouse.b2b-orders.index', [
            'filters' => $filters,
            'statuses' => B2bOrderStatus::options(),
            'orders' => B2bOrder::query()
                ->with(['customer', 'requester'])
                ->withCount('items')
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['q'], function ($query, string $term): void {
                    $like = '%'.$term.'%';
                    $query->where(fn ($search) => $search->where('number', 'like', $like)->orWhereHas('customer', fn ($customer) => $customer->where('business_name', 'like', $like)));
                })
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function review(B2bOrder $order): View
    {
        return view('warehouse.b2b-orders.review', [
            'order' => $order->load(['customer', 'address', 'items.product', 'reservations.product', 'statusHistories.actor', 'messages.user', 'invoices', 'shipments']),
        ]);
    }

    public function reserve(ReviewB2bOrderRequest $request, B2bOrder $order, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        try {
            $workflow->reserve($order, $request->user(), $request->validated());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['review' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.b2b-orders.review', $order)
            ->with('notification', ['type' => 'success', 'message' => 'Order berhasil divalidasi dan stok sudah di-reserve.']);
    }

    public function reject(RejectB2bOrderRequest $request, B2bOrder $order, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        try {
            $workflow->reject($order, $request->user(), $request->validated('reason'));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['reject' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.b2b-orders.review', $order)
            ->with('notification', ['type' => 'success', 'message' => 'Order B2B ditolak dan reservation aktif dilepas.']);
    }

    public function pack(Request $request, B2bOrder $order, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('b2b_orders.approve'), 403);

        try {
            $workflow->pack($order, $request->user(), $request->input('internal_note'));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['pack' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Order masuk proses packing.']);
    }

    public function ship(ShipB2bOrderRequest $request, B2bOrder $order, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        try {
            if (filled($request->validated('courier_name'))) {
                $order->forceFill(['courier_name' => $request->validated('courier_name')])->save();
            }
            $workflow->ship($order, $request->user(), $request->validated('internal_note'));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['ship' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Order dikirim dan reserved stock sudah dikonversi menjadi issue stock.']);
    }
}
