<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\DecidePriceApprovalRequest;
use App\Models\PriceApprovalRequest;
use App\Services\Pricing\PriceManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PriceApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PriceApprovalRequest::class);

        return view('pricing.approvals.index', [
            'approvals' => PriceApprovalRequest::query()->with(['product', 'customer', 'requester'])->latest('id')->paginate(15),
        ]);
    }

    public function approve(DecidePriceApprovalRequest $request, PriceApprovalRequest $approval, PriceManagementService $service): RedirectResponse
    {
        $this->authorize('approve', $approval);
        $service->approve($approval, $request->user(), $request->validated()['notes'] ?? null);

        return back()->with('notification', ['type' => 'success', 'message' => 'Approval harga disetujui.']);
    }

    public function reject(DecidePriceApprovalRequest $request, PriceApprovalRequest $approval, PriceManagementService $service): RedirectResponse
    {
        $this->authorize('approve', $approval);
        $service->reject($approval, $request->user(), $request->validated()['notes'] ?? null);

        return back()->with('notification', ['type' => 'success', 'message' => 'Approval harga ditolak.']);
    }
}
