<?php

namespace App\Http\Controllers\B2B;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\B2B\B2bFulfillmentService;
use App\Services\B2B\B2bPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentTrackingController extends Controller
{
    public function show(Request $request, Shipment $shipment, B2bPortalService $portal): View
    {
        $customer = $portal->activeCustomerFor($request->user());
        abort_unless((int) $shipment->customer_id === (int) $customer->id, 403);

        return view('b2b.shipments.show', [
            'shipment' => $shipment->load(['order', 'items.orderItem', 'proofs']),
        ]);
    }

    public function confirm(Request $request, Shipment $shipment, B2bPortalService $portal, B2bFulfillmentService $service): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());
        abort_unless((int) $shipment->customer_id === (int) $customer->id, 403);

        try {
            $service->confirmCustomerReceived($shipment, $request->user());
        } catch (ServiceException $exception) {
            return back()->withErrors(['shipment' => $exception->getMessage()]);
        }

        return redirect()->route('langganan.shipments.show', $shipment)->with('notification', ['type' => 'success', 'message' => 'Terima kasih, pengiriman dikonfirmasi selesai.']);
    }
}
