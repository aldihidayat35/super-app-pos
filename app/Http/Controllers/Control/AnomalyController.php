<?php

namespace App\Http\Controllers\Control;

use App\Enums\AnomalyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\ResolveAnomalyRequest;
use App\Models\AnomalyAlert;
use App\Services\Control\AnomalyDetectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnomalyController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('audit.view'), 403);

        return view('audit.anomalies.index', [
            'alerts' => AnomalyAlert::query()
                ->with(['subject', 'workLocation', 'assignee'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
                ->when($request->filled('severity'), fn ($query) => $query->where('severity', $request->query('severity')))
                ->when($request->filled('rule_key'), fn ($query) => $query->where('rule_key', $request->query('rule_key')))
                ->latest('detected_at')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => AnomalyStatus::cases(),
        ]);
    }

    public function resolve(ResolveAnomalyRequest $request, AnomalyAlert $anomaly, AnomalyDetectionService $service): RedirectResponse
    {
        $service->resolve($anomaly, $request->user(), $request->validated()['status'], $request->validated()['resolution_note'] ?? null);

        return back()->with('notification', ['type' => 'success', 'message' => 'Status anomali berhasil diperbarui.']);
    }
}
