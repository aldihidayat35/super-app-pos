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
        $this->authorize('viewAny', ApprovalRequest::class);
        $user = $request->user();
        $query = ApprovalRequest::query()->with(['requester', 'workLocation']);

        if (! $user->hasAnyRole(['super_admin', 'owner_viewer', 'owner_approver'])) {
            $roleNames = $user->roles()->pluck('name');
            $locationIds = $user->permittedWorkLocationIds();
            $query->where(function ($scope) use ($user, $roleNames, $locationIds): void {
                $scope->where('requester_user_id', $user->id)
                    ->orWhere(function ($assigned) use ($roleNames, $locationIds): void {
                        $assigned->whereIn('required_role', $roleNames)
                            ->where(function ($location) use ($locationIds): void {
                                $location->whereNull('work_location_id')
                                    ->orWhereIn('work_location_id', $locationIds);
                            });
                    });
            });
        }

        return view('approvals.index', [
            'approvals' => $query
                ->when($request->filled('status'), fn ($query) => $query->where('current_status', $request->query('status')))
                ->when($request->filled('module'), fn ($query) => $query->where('module', $request->query('module')))
                ->when($request->filled('risk_level'), fn ($query) => $query->where('risk_level', $request->query('risk_level')))
                ->when($request->integer('requester_user_id') > 0, fn ($query) => $query->where('requester_user_id', $request->integer('requester_user_id')))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'statuses' => ApprovalRequestStatus::cases(),
        ]);
    }

    public function show(ApprovalRequest $approval): View
    {
        $this->authorize('view', $approval);

        return view('approvals.show', ['approval' => $approval->load(['subject', 'requester', 'approver', 'workLocation', 'steps.approver'])]);
    }

    public function approve(DecideApprovalRequest $request, ApprovalRequest $approval, ApprovalWorkflowService $service): RedirectResponse
    {
        $this->authorize('approve', $approval);

        try {
            $service->approve($approval, $request->user(), $request->validated()['comments'] ?? null);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return redirect()->route('approvals.show', $approval)->with('notification', ['type' => 'success', 'message' => 'Approval berhasil disetujui.']);
    }

    public function reject(DecideApprovalRequest $request, ApprovalRequest $approval, ApprovalWorkflowService $service): RedirectResponse
    {
        $this->authorize('reject', $approval);

        try {
            $service->reject($approval, $request->user(), $request->validated()['comments'] ?? null);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return redirect()->route('approvals.show', $approval)->with('notification', ['type' => 'success', 'message' => 'Approval berhasil ditolak.']);
    }
}
