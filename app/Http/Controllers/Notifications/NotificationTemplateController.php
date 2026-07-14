<?php

namespace App\Http\Controllers\Notifications;

use App\Enums\NotificationChannelType;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreNotificationTemplateRequest;
use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationTemplateRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        return view('notifications.templates.index', [
            'templates' => NotificationTemplate::query()->latest('id')->paginate(20)->withQueryString(),
            'types' => NotificationChannelType::cases(),
            'variableGroups' => config('notifications.template_variables', []),
        ]);
    }

    public function store(StoreNotificationTemplateRequest $request): RedirectResponse
    {
        NotificationTemplate::query()->create($this->payload($request) + ['created_by' => $request->user()->id]);

        return back()->with('notification', ['type' => 'success', 'message' => 'Template pesan berhasil disimpan.']);
    }

    public function update(StoreNotificationTemplateRequest $request, NotificationTemplate $template): RedirectResponse
    {
        $history = $template->history ?? [];
        $history[] = [
            'version' => $template->version,
            'body' => $template->body,
            'updated_at' => now('Asia/Jakarta')->toDateTimeString(),
            'updated_by' => $request->user()->id,
        ];
        $template->update($this->payload($request) + [
            'version' => $template->version + 1,
            'history' => $history,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('notification', ['type' => 'success', 'message' => 'Template pesan berhasil diperbarui dengan versi baru.']);
    }

    public function preview(Request $request, NotificationTemplate $template, NotificationTemplateRenderer $renderer): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        $variables = $this->variables($template->allowed_variables ?: config('notifications.template_variables.'.$template->key, []));
        $context = [];
        foreach ($variables as $variable) {
            $context[$variable] = '['.$variable.']';
        }

        try {
            $preview = $renderer->render($template, $context);
        } catch (ServiceException $exception) {
            return back()->with('notification', ['type' => 'danger', 'message' => $exception->getMessage()]);
        }

        return back()->with('notification_preview', $preview);
    }

    /** @return array<string, mixed> */
    private function payload(StoreNotificationTemplateRequest $request): array
    {
        $validated = $request->validated();

        return [
            'key' => $validated['key'],
            'name' => $validated['name'],
            'channel_type' => $validated['channel_type'],
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'fallback_body' => $validated['fallback_body'] ?? null,
            'allowed_variables' => collect(explode(',', (string) ($validated['allowed_variables'] ?? '')))
                ->map(fn (string $value): string => trim($value))
                ->filter()
                ->values()
                ->all(),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    /**
     * @return list<string>
     */
    private function variables(mixed $variables): array
    {
        if (! is_array($variables)) {
            return [];
        }

        $normalized = [];
        foreach ($variables as $variable) {
            if (is_string($variable) && $variable !== '') {
                $normalized[] = $variable;
            }
        }

        return $normalized;
    }
}
