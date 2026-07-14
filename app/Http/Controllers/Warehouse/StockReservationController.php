<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\StockReservationStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Models\StockReservation;
use App\Services\B2B\B2bOrderWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StockReservationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('stock.view') || $request->user()->can('b2b_orders.view'), 403);
        $filters = ['status' => $request->query('status'), 'q' => trim((string) $request->query('q'))];

        return view('warehouse.reservations.index', [
            'filters' => $filters,
            'statuses' => StockReservationStatus::cases(),
            'reservations' => StockReservation::query()
                ->with(['order.customer', 'item', 'product', 'workLocation', 'warehouseLocation'])
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['q'], function ($query, string $term): void {
                    $like = '%'.$term.'%';
                    $query->whereHas('order', fn ($order) => $order->where('number', 'like', $like))
                        ->orWhereHas('product', fn ($product) => $product->where('name', 'like', $like)->orWhere('sku', 'like', $like));
                })
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function release(Request $request, StockReservation $reservation, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('b2b_orders.approve'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $workflow->releaseReservation($reservation, $request->user(), $data['reason']);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['reservation' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Reservation dilepas.']);
    }

    public function expire(Request $request, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('b2b_orders.approve'), 403);
        $count = $workflow->expireReservations($request->user());

        return back()->with('notification', ['type' => 'success', 'message' => "{$count} reservation kedaluwarsa diproses."]);
    }
}
