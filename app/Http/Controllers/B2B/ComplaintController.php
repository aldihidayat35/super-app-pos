<?php

namespace App\Http\Controllers\B2B;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreB2bComplaintRequest;
use App\Models\B2bComplaint;
use App\Models\B2bOrder;
use App\Models\Shipment;
use App\Services\B2B\B2bFulfillmentService;
use App\Services\B2B\B2bPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function index(Request $request, B2bPortalService $portal): View
    {
        $customer = $portal->activeCustomerFor($request->user());

        return view('b2b.complaints.index', [
            'complaints' => B2bComplaint::query()->where('customer_id', $customer->id)->latest('id')->paginate(15),
            'orders' => B2bOrder::query()->where('customer_id', $customer->id)->latest('id')->limit(30)->get(),
            'shipments' => Shipment::query()->where('customer_id', $customer->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function store(StoreB2bComplaintRequest $request, B2bPortalService $portal, B2bFulfillmentService $service): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());
        $data = $request->validated();
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')?->store('complaint-evidence');
        }

        $service->submitComplaint($customer, $request->user(), $data);

        return redirect()->route('langganan.complaints.index')->with('notification', ['type' => 'success', 'message' => 'Komplain berhasil diajukan. Tim kami akan meninjau.']);
    }
}
