<?php

namespace App\Http\Controllers\Control;

use App\Enums\ApprovalRequestStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\DecideApprovalRequest;
use App\Models\ApprovalRequest;
use App\Services\Control\ApprovalWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApprovalInboxController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('approvals.view'), 403);

        return view('approvals.index', [
            'approvals' => ApprovalRequest::query()
                ->with(['requester', 'workLocation'])
                ->when($request->filled('status'), fn ($query) => $query->where('current_status', $request->query('status')))
                ->when($request->filled('module'), fn ($query) => $query->where('module', $request->query('module')))
                ->when($request->filled('risk_level'), fn ($query) => $query->where('risk_level', $request->query('risk_level')))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'statuses' => ApprovalRequestStatus::cases(),
        ]);
    }

    public function show(ApprovalRequest $approval): View
    {
        abort_unless(request()->user()?->can('approvals.view'), 403);

        return view('approvals.show', ['approval' => $approval->load(['subject', 'requester', 'approver', 'workLocation', 'steps.approver'])]);
    }

    public function approve(DecideApprovalRequest $request, ApprovalRequest $approval, ApprovalWorkflowService $service): RedirectResponse
    {
        try {
            $service->approve($approval, $request->user(), $request->validated()['comments'] ?? null);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return redirect()->route('approvals.show', $approval)->with('notification', ['type' => 'success', 'message' => 'Approval berhasil disetujui.']);
    }

    public function reject(DecideApprovalRequest $request, ApprovalRequest $approval, ApprovalWorkflowService $service): RedirectResponse
    {
        try {
            $service->reject($approval, $request->user(), $request->validated()['comments'] ?? null);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return redirect()->route('approvals.show', $approval)->with('notification', ['type' => 'success', 'message' => 'Approval berhasil ditolak.']);
    }
}
