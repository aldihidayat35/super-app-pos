<?php

namespace App\Http\Controllers\WorkChecklist;

use App\Enums\WorkChecklistFrequency;
use App\Enums\WorkChecklistItemStatus;
use App\Enums\WorkChecklistStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkChecklist\ReopenWorkChecklistRequest;
use App\Http\Requests\WorkChecklist\StoreWorkChecklistTemplateRequest;
use App\Http\Requests\WorkChecklist\UpdateWorkChecklistItemRequest;
use App\Models\Attendance;
use App\Models\User;
use App\Models\WorkChecklist;
use App\Models\WorkChecklistItem;
use App\Models\WorkChecklistTemplate;
use App\Models\WorkLocation;
use App\Services\Attendance\AttendanceService;
use App\Services\WorkChecklist\WorkChecklistService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkChecklistController extends Controller
{
    public function __construct(private readonly WorkChecklistService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', WorkChecklist::class);
        $user = $request->user();
        $this->service->generateCurrentFor($user);
        $tab = in_array($request->query('tab'), ['mine', 'history', 'recap', 'templates'], true) ? $request->query('tab') : 'mine';
        if ($tab === 'recap' && ! $this->canViewRecap($user)) {
            $tab = 'mine';
        }
        if ($tab === 'templates' && ! $user->can('work_checklists.manage_templates')) {
            $tab = 'mine';
        }

        $filters = $this->filters($request);
        $history = WorkChecklist::query()
            ->with(['workLocation', 'items'])
            ->where('user_id', $user->id)
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('period_start', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('period_start', '<=', $to))
            ->when($filters['frequency'], fn (Builder $query, string $frequency) => $query->where('frequency', $frequency))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['work_location_id'], fn (Builder $query, int $locationId) => $query->where('work_location_id', $locationId))
            ->latest('period_start')->latest('id')->paginate(12, ['*'], 'history_page')->withQueryString();

        $recapQuery = $this->visibleRecapQuery($user);
        $this->applyRecapFilters($recapQuery, $filters);
        $recapMetrics = $this->metrics(clone $recapQuery);
        $recap = $this->canViewRecap($user)
            ? $recapQuery->with(['user.roles', 'workLocation', 'items'])->latest('period_start')->latest('id')->paginate(20, ['*'], 'recap_page')->withQueryString()
            : null;

        $selected = null;
        if ($request->filled('checklist_id')) {
            $selected = WorkChecklist::query()->with(['user.roles', 'workLocation', 'items'])->findOrFail($request->integer('checklist_id'));
            $this->authorize('view', $selected);
        }

        $templates = collect();
        $editingTemplate = null;
        if ($user->can('work_checklists.manage_templates')) {
            $this->service->ensureDefaultTemplates();
            $templates = WorkChecklistTemplate::query()->with(['roles', 'items'])
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', now(config('work-checklists.timezone'))->toDateString()))
                ->orderBy('template_key')->orderByDesc('version')->get()->unique('template_key')->sortBy('name')->values();
            if ($request->filled('edit_template')) {
                $editingTemplate = WorkChecklistTemplate::query()->with(['roles', 'items'])->findOrFail($request->integer('edit_template'));
            }
        }

        $locations = $user->can('work_checklists.view_all')
            ? WorkLocation::query()->where('is_active', true)->orderBy('name')->get()
            : WorkLocation::query()->whereIn('id', $user->permittedWorkLocationIds())->orderBy('name')->get();

        return view('work-checklists.index', [
            'tab' => $tab,
            'currentRuns' => WorkChecklist::query()
                ->with(['workLocation', 'items'])
                ->where('user_id', $user->id)
                ->where(function (Builder $query): void {
                    $query->where('status', WorkChecklistStatus::OPEN->value)
                        ->orWhereDate('period_end', '>=', now(config('work-checklists.timezone'))->toDateString());
                })
                ->orderBy('due_at')
                ->get(),
            'history' => $history,
            'recap' => $recap,
            'recapMetrics' => $recapMetrics,
            'selected' => $selected,
            'templates' => $templates,
            'editingTemplate' => $editingTemplate,
            'locations' => $locations,
            'users' => $this->visibleUsers($user),
            'roles' => $this->roleOptions(),
            'frequencies' => WorkChecklistFrequency::cases(),
            'itemStatuses' => WorkChecklistItemStatus::cases(),
            'filters' => $filters,
            'activeAttendance' => Attendance::query()->where('user_id', $user->id)->whereNotNull('check_in_at')->whereNull('check_out_at')->latest('check_in_at')->first(),
        ]);
    }

    public function updateItem(UpdateWorkChecklistItemRequest $request, WorkChecklistItem $item): RedirectResponse
    {
        $this->service->updateItem($item, $request->validated(), $request->user(), $request);

        return back()->with('notification', ['type' => 'success', 'message' => 'Status poin checklist berhasil disimpan.']);
    }

    public function complete(Request $request, WorkChecklist $checklist): RedirectResponse
    {
        $this->authorize('update', $checklist);
        $this->service->complete($checklist, $request->user(), $request);

        return back()->with('notification', ['type' => 'success', 'message' => 'Checklist berhasil diselesaikan.']);
    }

    public function completeAndCheckOut(Request $request, WorkChecklist $checklist, AttendanceService $attendanceService): RedirectResponse
    {
        $this->authorize('update', $checklist);
        if ($checklist->frequency !== WorkChecklistFrequency::DAILY) {
            throw ValidationException::withMessages(['checklist' => 'Absen pulang hanya dapat dilakukan dari checklist harian.']);
        }
        try {
            DB::transaction(function () use ($request, $checklist, $attendanceService): void {
                if ($checklist->status !== WorkChecklistStatus::COMPLETED) {
                    $this->service->complete($checklist, $request->user(), $request);
                }
                $attendanceService->checkOut($request->user(), []);
            });
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['attendance' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Checklist selesai dan jam pulang berhasil dicatat.']);
    }

    public function reopen(ReopenWorkChecklistRequest $request, WorkChecklist $checklist): RedirectResponse
    {
        $this->service->reopen($checklist, $request->validated('reason'), $request->user(), $request);

        return back()->with('notification', ['type' => 'success', 'message' => 'Checklist dibuka kembali untuk koreksi.']);
    }

    public function storeTemplate(StoreWorkChecklistTemplateRequest $request): RedirectResponse
    {
        $this->service->createTemplateVersion($request->validated(), $request->user(), $request);

        return redirect()->route('work-checklists.index', ['tab' => 'templates'])->with('notification', ['type' => 'success', 'message' => 'Template checklist berhasil dibuat.']);
    }

    public function versionTemplate(StoreWorkChecklistTemplateRequest $request, WorkChecklistTemplate $template): RedirectResponse
    {
        $this->service->createTemplateVersion($request->validated(), $request->user(), $request, $template);

        return redirect()->route('work-checklists.index', ['tab' => 'templates'])->with('notification', ['type' => 'success', 'message' => 'Versi baru template berhasil dijadwalkan.']);
    }

    public function deactivateTemplate(Request $request, WorkChecklistTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->can('work_checklists.manage_templates'), 403);
        $this->service->deactivateTemplate($template, $request->user(), $request);

        return back()->with('notification', ['type' => 'success', 'message' => 'Template akan berhenti setelah periode berjalan.']);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('work_checklists.export') && $this->canViewRecap($request->user()), 403);
        $filters = $this->filters($request);
        $query = $this->visibleRecapQuery($request->user());
        $this->applyRecapFilters($query, $filters);

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Periode', 'Frekuensi', 'Akun', 'Role', 'Lokasi', 'Status', 'Tepat Waktu', 'Selesai', 'Terkendala', 'Tidak Berlaku', 'Total']);
            $query->with(['user.roles', 'workLocation', 'items'])->orderBy('period_start')->chunkById(200, function (EloquentCollection $rows) use ($handle): void {
                foreach ($rows as $row) {
                    $locationName = $row->work_location_id === null ? 'Global' : $row->workLocation->name;
                    fputcsv($handle, [
                        $row->period_start->format('d/m/Y').' - '.$row->period_end->format('d/m/Y'), $row->frequency->label(), $row->user->name,
                        $row->user->roles->pluck('name')->join(', '), $locationName, $row->status->label(),
                        $row->first_completed_at ? ($row->first_completed_at->lessThanOrEqualTo($row->due_at) ? 'Ya' : 'Tidak') : '-',
                        $row->items->where('status', WorkChecklistItemStatus::DONE)->count(), $row->items->where('status', WorkChecklistItemStatus::BLOCKED)->count(),
                        $row->items->where('status', WorkChecklistItemStatus::NOT_APPLICABLE)->count(), $row->items->count(),
                    ]);
                }
            });
            fclose($handle);
        }, 'rekap-checklist-kerja-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{from: string|null, to: string|null, frequency: string|null, status: string|null, work_location_id: int|null, user_id: int|null, role: string|null} */
    private function filters(Request $request): array
    {
        $validated = validator($request->query(), [
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'frequency' => ['nullable', 'in:daily,weekly'], 'status' => ['nullable', 'in:open,completed'],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'], 'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', 'string', 'max:100'],
        ])->validate();

        return [
            'from' => $validated['from'] ?? null, 'to' => $validated['to'] ?? null,
            'frequency' => $validated['frequency'] ?? null, 'status' => $validated['status'] ?? null,
            'work_location_id' => isset($validated['work_location_id']) ? (int) $validated['work_location_id'] : null,
            'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            'role' => $validated['role'] ?? null,
        ];
    }

    /** @return Builder<WorkChecklist> */
    private function visibleRecapQuery(User $user): Builder
    {
        $query = WorkChecklist::query();
        if ($user->can('work_checklists.view_all')) {
            return $query;
        }
        if (! $user->can('work_checklists.view_team')) {
            return $query->where('user_id', $user->id);
        }

        $subordinateRoles = collect((array) config('work-checklists.team_roles'))
            ->filter(fn (array $roles, string $managerRole): bool => $user->hasRole($managerRole))
            ->flatten()->unique()->values();

        return $query->whereIn('work_location_id', $user->permittedWorkLocationIds())
            ->where(function (Builder $builder) use ($subordinateRoles): void {
                foreach ($subordinateRoles as $role) {
                    $builder->orWhereJsonContains('role_snapshot', $role);
                }
            });
    }

    /**
     * @param  Builder<WorkChecklist>  $query
     * @param  array{from: string|null, to: string|null, frequency: string|null, status: string|null, work_location_id: int|null, user_id: int|null, role: string|null}  $filters
     */
    private function applyRecapFilters(Builder $query, array $filters): void
    {
        $query->when($filters['from'], fn (Builder $builder, string $from) => $builder->whereDate('period_start', '>=', $from))
            ->when($filters['to'], fn (Builder $builder, string $to) => $builder->whereDate('period_start', '<=', $to))
            ->when($filters['frequency'], fn (Builder $builder, string $frequency) => $builder->where('frequency', $frequency))
            ->when($filters['status'], fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['work_location_id'], fn (Builder $builder, int $locationId) => $builder->where('work_location_id', $locationId))
            ->when($filters['user_id'], fn (Builder $builder, int $userId) => $builder->where('user_id', $userId))
            ->when($filters['role'], fn (Builder $builder, string $role) => $builder->whereJsonContains('role_snapshot', $role));
    }

    /**
     * @param  Builder<WorkChecklist>  $query
     * @return array{expected: int, completed: int, on_time: int, late: int, open: int, blocked: int, not_applicable: int, response_rate: float|int, completion_rate: float|int}
     */
    private function metrics(Builder $query): array
    {
        $rows = $query->with('items')->get();
        $items = $rows->flatMap(fn (WorkChecklist $row) => $row->items);
        $applicable = $items->where('status', '!=', WorkChecklistItemStatus::NOT_APPLICABLE);

        return [
            'expected' => $rows->count(),
            'completed' => $rows->where('status', WorkChecklistStatus::COMPLETED)->count(),
            'on_time' => $rows->filter(fn (WorkChecklist $row): bool => $row->first_completed_at?->lessThanOrEqualTo($row->due_at) ?? false)->count(),
            'late' => $rows->filter(fn (WorkChecklist $row): bool => $row->first_completed_at?->greaterThan($row->due_at) ?? false)->count(),
            'open' => $rows->where('status', WorkChecklistStatus::OPEN)->count(),
            'blocked' => $items->where('status', WorkChecklistItemStatus::BLOCKED)->count(),
            'not_applicable' => $items->where('status', WorkChecklistItemStatus::NOT_APPLICABLE)->count(),
            'response_rate' => $items->count() === 0 ? 0 : round(($items->where('status', '!=', WorkChecklistItemStatus::PENDING)->count() / $items->count()) * 100, 1),
            'completion_rate' => $applicable->count() === 0 ? 0 : round(($applicable->where('status', WorkChecklistItemStatus::DONE)->count() / $applicable->count()) * 100, 1),
        ];
    }

    /** @return Collection<int, User> */
    private function visibleUsers(User $user)
    {
        $ids = $this->visibleRecapQuery($user)->distinct()->pluck('user_id');

        return User::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    private function canViewRecap(User $user): bool
    {
        return $user->can('work_checklists.view_team') || $user->can('work_checklists.view_all');
    }

    /** @return Collection<string, array{label: string, description: string, permissions: list<string>}> */
    private function roleOptions(): Collection
    {
        $roles = [];
        foreach ((array) config('work-checklists.internal_roles') as $roleName) {
            if (! is_string($roleName)) {
                continue;
            }
            $metadata = config("rbac.roles.{$roleName}");
            if (is_array($metadata) && is_string($metadata['label'] ?? null) && is_string($metadata['description'] ?? null) && is_array($metadata['permissions'] ?? null)) {
                $permissions = array_values(array_filter($metadata['permissions'], is_string(...)));
                $roles[$roleName] = ['label' => $metadata['label'], 'description' => $metadata['description'], 'permissions' => $permissions];
            }
        }

        return collect($roles);
    }
}
