<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\BusinessNotificationSetting;
use App\Services\Control\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessNotificationSettingController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        return view('notifications.business-settings', ['settings' => BusinessNotificationSetting::query()->orderBy('id')->get()]);
    }

    public function update(Request $request, BusinessNotificationSetting $setting, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.update'), 403);
        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'cooldown_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ], [], ['is_active' => 'status notifikasi', 'cooldown_minutes' => 'jeda pengiriman']);
        $before = $setting->only(['is_active', 'cooldown_minutes']);
        $setting->update(['is_active' => (bool) ($data['is_active'] ?? false), 'cooldown_minutes' => $data['cooldown_minutes'], 'updated_by' => $request->user()->id]);
        $audit->record('business_notification.setting_updated', 'notifications', $request->user(), $setting, $before, $setting->only(['is_active', 'cooldown_minutes']), request: $request);

        return back()->with('notification', ['type' => 'success', 'message' => "Pengaturan {$setting->name} berhasil disimpan."]);
    }
}
