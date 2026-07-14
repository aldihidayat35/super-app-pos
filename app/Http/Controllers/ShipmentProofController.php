<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceException;
use App\Http\Requests\StoreShipmentProofRequest;
use App\Models\Shipment;
use App\Services\B2B\B2bFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShipmentProofController extends Controller
{
    public function show(Shipment $shipment): View
    {
        $this->authorize('view', $shipment);

        return view('shipments.proof', ['shipment' => $shipment->load(['order', 'items.orderItem', 'proofs'])]);
    }

    public function store(StoreShipmentProofRequest $request, Shipment $shipment, B2bFulfillmentService $service): RedirectResponse
    {
        $this->authorize('view', $shipment);
        $data = $request->validated();
        if ($request->hasFile('proof')) {
            $data['file_path'] = $request->file('proof')?->store('shipment-proofs');
        }

        try {
            $service->storeShipmentProof($shipment, $request->user(), $data);
        } catch (ServiceException $exception) {
            return back()->withErrors(['proof' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('shipments.show', $shipment)->with('notification', ['type' => 'success', 'message' => 'Bukti shipment berhasil disimpan.']);
    }
}
