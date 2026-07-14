<?php

namespace App\Http\Controllers;

use App\Enums\B2bOrderStatus;
use App\Enums\ShipmentStatus;
use App\Exceptions\ServiceException;
use App\Http\Requests\StoreShipmentRequest;
use App\Models\B2bOrder;
use App\Models\Shipment;
use App\Services\B2B\B2bFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Shipment::class);

        return view('shipments.index', [
            'statuses' => ShipmentStatus::options(),
            'shipments' => Shipment::query()
                ->with(['order', 'customer'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->when($request->filled('q'), fn ($query) => $query->where('number', 'like', '%'.$request->string('q').'%'))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Shipment::class);
        $order = $request->filled('order_id')
            ? B2bOrder::query()->with(['items', 'customer'])->find($request->integer('order_id'))
            : null;

        return view('shipments.create', [
            'order' => $order,
            'orders' => B2bOrder::query()
                ->with('customer')
                ->whereIn('status', [B2bOrderStatus::APPROVED_CREDIT->value, B2bOrderStatus::PACKING->value])
                ->latest('id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(StoreShipmentRequest $request, B2bFulfillmentService $service): RedirectResponse
    {
        $order = B2bOrder::query()->findOrFail($request->integer('b2b_order_id'));

        try {
            $shipment = $service->createShipment($order, $request->user(), $request->validated());
        } catch (ServiceException $exception) {
            return back()->withErrors(['shipment' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('shipments.show', $shipment)->with('notification', ['type' => 'success', 'message' => 'Shipment berhasil dibuat.']);
    }

    public function show(Shipment $shipment): View
    {
        $this->authorize('view', $shipment);

        return view('shipments.show', [
            'shipment' => $shipment->load(['order.items', 'customer', 'destinationAddress', 'items.orderItem', 'proofs']),
        ]);
    }

    public function post(Shipment $shipment, Request $request, B2bFulfillmentService $service): RedirectResponse
    {
        $this->authorize('update', Shipment::class);

        try {
            $service->postShipment($shipment, $request->user());
        } catch (ServiceException $exception) {
            return back()->withErrors(['shipment' => $exception->getMessage()]);
        }

        return redirect()->route('shipments.show', $shipment)->with('notification', ['type' => 'success', 'message' => 'Shipment diposting dan stok resmi keluar.']);
    }
}
