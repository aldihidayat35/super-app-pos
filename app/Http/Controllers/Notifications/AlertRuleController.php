<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreAlertRuleRequest;
use App\Models\AlertRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlertRuleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->can('audit.view'), 403);

        return view('notifications.alerts.index', [
            'rules' => AlertRule::query()->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function store(StoreAlertRuleRequest $request): RedirectResponse
    {
        AlertRule::query()->create($this->payload($request));

        return back()->with('notification', ['type' => 'success', 'message' => 'Aturan alert berhasil disimpan.']);
    }

    public function update(StoreAlertRuleRequest $request, AlertRule $alert): RedirectResponse
    {
        $alert->update($this->payload($request));

        return back()->with('notification', ['type' => 'success', 'message' => 'Aturan alert berhasil diperbarui.']);
    }

    public function preview(Request $request, AlertRule $alert): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->can('audit.view'), 403);

        return back()->with('notification_preview', [
            'subject' => 'Preview '.$alert->name,
            'body' => "Jika nilai {$alert->alert_type} melewati {$alert->threshold_value}, alert {$alert->severity} akan dikirim setelah cooldown {$alert->cooldown_minutes} menit.",
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(StoreAlertRuleRequest $request): array
    {
        $validated = $request->validated();

        return [
            'rule_key' => $validated['rule_key'],
            'name' => $validated['name'],
            'alert_type' => $validated['alert_type'],
            'severity' => $validated['severity'],
            'threshold_value' => $validated['threshold_value'] ?? null,
            'cooldown_minutes' => (int) $validated['cooldown_minutes'],
            'channel_types' => $validated['channel_types'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
