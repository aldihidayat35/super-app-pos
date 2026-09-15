<?php

namespace App\Services\WorkChecklist;

use App\Enums\WorkChecklistFrequency;
use App\Enums\WorkChecklistItemStatus;
use App\Enums\WorkChecklistStatus;
use App\Models\User;
use App\Models\WorkChecklist;
use App\Models\WorkChecklistItem;
use App\Models\WorkChecklistTemplate;
use App\Models\WorkLocation;
use App\Services\Control\AuditLogService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WorkChecklistService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function ensureDefaultTemplates(): void
    {
        if (! Schema::hasTable('work_checklist_templates') || WorkChecklistTemplate::query()->exists()) {
            return;
        }

        DB::transaction(function (): void {
            if (WorkChecklistTemplate::query()->lockForUpdate()->exists()) {
                return;
            }

            foreach ((array) config('work-checklists.templates', []) as $definition) {
                $template = WorkChecklistTemplate::query()->create([
                    'template_key' => $definition['key'],
                    'version' => 1,
                    'name' => $definition['name'],
                    'frequency' => $definition['frequency'],
                    'scope' => $definition['scope'],
                    'location_type' => $definition['location_type'],
                    'effective_from' => '2020-01-01',
                    'is_active' => true,
                ]);

                $roles = [];
                foreach ((array) ($definition['roles'] ?? []) as $role) {
                    if (is_string($role)) {
                        $roles[] = ['role_name' => $role];
                    }
                }
                $items = [];
                foreach ((array) ($definition['items'] ?? []) as $index => $configuredItem) {
                    if (! is_array($configuredItem) || ! is_string($configuredItem['key'] ?? null) || ! is_string($configuredItem['label'] ?? null)) {
                        continue;
                    }
                    $items[] = [
                        'item_key' => $configuredItem['key'],
                        'label' => $configuredItem['label'],
                        'guidance' => is_string($configuredItem['guidance'] ?? null) ? $configuredItem['guidance'] : null,
                        'sort_order' => ((int) $index + 1) * 10,
                        'is_required' => true,
                    ];
                }
                $template->roles()->createMany($roles);
                $template->items()->createMany($items);
            }
        });
    }

    /** @return Collection<int, WorkChecklist> */
    public function generateCurrentFor(User $user, ?CarbonInterface $moment = null): Collection
    {
        $this->ensureDefaultTemplates();
        $now = CarbonImmutable::instance($moment ?? now(config('work-checklists.timezone')))
            ->setTimezone((string) config('work-checklists.timezone'));
        $roleNames = $user->roles()->pluck('name')->intersect((array) config('work-checklists.internal_roles'))->values();

        if ($roleNames->isEmpty()) {
            return collect();
        }

        $generated = collect();
        foreach (WorkChecklistFrequency::cases() as $frequency) {
            [$start, $end] = $this->period($frequency, $now);
            $templates = WorkChecklistTemplate::query()
                ->with(['roles', 'items'])
                ->where('frequency', $frequency->value)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $start->toDateString())
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $start->toDateString()))
                ->whereHas('roles', fn ($query) => $query->whereIn('role_name', $roleNames->all()))
                ->orderBy('template_key')
                ->orderByDesc('version')
                ->get()
                ->unique('template_key')
                ->values();

            $global = $templates->where('scope', 'global')->values();
            if ($global->isNotEmpty()) {
                $generated->push($this->createRun($user, null, $frequency, $start, $end, $global, $roleNames));
            }

            $locationTemplates = $templates->where('scope', 'location')->values();
            if ($locationTemplates->isEmpty()) {
                continue;
            }

            $locations = $this->activeAssignedLocations($user, $now);
            foreach ($locations as $location) {
                $matching = $locationTemplates
                    ->filter(fn (WorkChecklistTemplate $template): bool => $template->location_type === null || $template->location_type === $location->type)
                    ->values();
                if ($matching->isNotEmpty()) {
                    $generated->push($this->createRun($user, $location, $frequency, $start, $end, $matching, $roleNames));
                }
            }
        }

        return $generated->filter()->values();
    }

    public function generateCurrentForAll(?CarbonInterface $moment = null): int
    {
        $count = 0;
        User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', (array) config('work-checklists.internal_roles')))
            ->orderBy('id')
            ->chunkById(100, function (EloquentCollection $users) use (&$count, $moment): void {
                foreach ($users as $user) {
                    $count += $this->generateCurrentFor($user, $moment)->count();
                }
            });

        return $count;
    }

    public function pendingCount(User $user): int
    {
        if (! Schema::hasTable('work_checklists')) {
            return 0;
        }

        return WorkChecklist::query()->where('user_id', $user->id)->where('status', WorkChecklistStatus::OPEN->value)->count();
    }

    /** @param array{status: string, note?: string|null} $data */
    public function updateItem(WorkChecklistItem $item, array $data, User $actor, Request $request): WorkChecklistItem
    {
        return DB::transaction(function () use ($item, $data, $actor, $request): WorkChecklistItem {
            $locked = WorkChecklistItem::query()->with('checklist.workLocation')->lockForUpdate()->findOrFail($item->id);
            if ($locked->checklist->status === WorkChecklistStatus::COMPLETED) {
                throw ValidationException::withMessages(['status' => 'Checklist sudah selesai. Gunakan aksi Koreksi sebelum mengubah jawaban.']);
            }

            $status = WorkChecklistItemStatus::from($data['status']);
            $note = filled($data['note'] ?? null) ? trim((string) $data['note']) : null;
            if (in_array($status, [WorkChecklistItemStatus::BLOCKED, WorkChecklistItemStatus::NOT_APPLICABLE], true) && $note === null) {
                throw ValidationException::withMessages(['note' => 'Catatan wajib diisi untuk status Terkendala atau Tidak Berlaku.']);
            }

            $before = $locked->only(['status', 'note', 'responded_at']);
            $locked->update([
                'status' => $status,
                'note' => $note,
                'responded_at' => $status === WorkChecklistItemStatus::PENDING ? null : now(),
            ]);
            $this->audit->record('work_checklist.item_updated', 'work_checklists', $actor, $locked->checklist, $before, $locked->only(['status', 'note', 'responded_at']), request: $request, location: $locked->checklist->workLocation);

            return $locked->fresh();
        });
    }

    public function complete(WorkChecklist $checklist, User $actor, Request $request): WorkChecklist
    {
        return DB::transaction(function () use ($checklist, $actor, $request): WorkChecklist {
            $locked = WorkChecklist::query()->with(['items', 'workLocation'])->lockForUpdate()->findOrFail($checklist->id);
            if ($locked->items->contains(fn (WorkChecklistItem $item): bool => $item->status === WorkChecklistItemStatus::PENDING)) {
                throw ValidationException::withMessages(['checklist' => 'Semua poin harus direspons sebelum checklist diselesaikan.']);
            }

            $completedAt = now();
            $before = $locked->only(['status', 'completed_at']);
            $locked->update([
                'status' => WorkChecklistStatus::COMPLETED,
                'first_completed_at' => $locked->first_completed_at ?? $completedAt,
                'completed_at' => $completedAt,
            ]);
            $this->audit->record('work_checklist.completed', 'work_checklists', $actor, $locked, $before, $locked->only(['status', 'first_completed_at', 'completed_at']), request: $request, location: $locked->workLocation);

            return $locked->fresh();
        });
    }

    public function reopen(WorkChecklist $checklist, string $reason, User $actor, Request $request): WorkChecklist
    {
        return DB::transaction(function () use ($checklist, $reason, $actor, $request): WorkChecklist {
            $locked = WorkChecklist::query()->with('workLocation')->lockForUpdate()->findOrFail($checklist->id);
            if ($locked->status !== WorkChecklistStatus::COMPLETED) {
                throw ValidationException::withMessages(['reason' => 'Checklist masih terbuka dan tidak perlu dikoreksi.']);
            }

            $before = $locked->only(['status', 'completed_at']);
            $locked->update(['status' => WorkChecklistStatus::OPEN, 'completed_at' => null]);
            $this->audit->record('work_checklist.reopened', 'work_checklists', $actor, $locked, $before, $locked->only(['status', 'completed_at']), $reason, $request, $locked->workLocation, 'warning');

            return $locked->fresh();
        });
    }

    /**
     * @param  array{template_key?: string|null, name: string, frequency: string, scope: string, location_type?: string|null, effective_from: string, roles: list<string>, items: list<array{item_key: string, label: string, guidance?: string|null, is_required?: bool|int}>}  $data
     */
    public function createTemplateVersion(array $data, User $actor, Request $request, ?WorkChecklistTemplate $source = null): WorkChecklistTemplate
    {
        return DB::transaction(function () use ($data, $actor, $request, $source): WorkChecklistTemplate {
            if ($source !== null) {
                $source = WorkChecklistTemplate::query()->lockForUpdate()->findOrFail($source->id);
            }

            $frequency = WorkChecklistFrequency::from($data['frequency']);
            $effective = CarbonImmutable::parse($data['effective_from'], (string) config('work-checklists.timezone'));
            if ($frequency === WorkChecklistFrequency::WEEKLY && $effective->dayOfWeek !== CarbonInterface::MONDAY) {
                $effective = $effective->next(CarbonInterface::MONDAY);
            }

            $key = $source === null ? (string) $data['template_key'] : $source->template_key;
            $latestEffective = WorkChecklistTemplate::query()->where('template_key', $key)->max('effective_from');
            if ($latestEffective !== null && $effective->lessThanOrEqualTo(CarbonImmutable::parse((string) $latestEffective))) {
                throw ValidationException::withMessages(['effective_from' => 'Tanggal mulai harus setelah versi template terakhir yang sudah dijadwalkan.']);
            }
            $latestVersion = (int) WorkChecklistTemplate::query()->where('template_key', $key)->max('version');
            $template = WorkChecklistTemplate::query()->create([
                'template_key' => $key,
                'version' => $latestVersion + 1,
                'name' => $data['name'],
                'frequency' => $frequency,
                'scope' => $data['scope'],
                'location_type' => $data['scope'] === 'location' ? $data['location_type'] : null,
                'effective_from' => $effective->toDateString(),
                'is_active' => true,
                'created_by' => $actor->id,
            ]);
            $template->roles()->createMany(collect($data['roles'])->unique()->map(fn (string $role): array => ['role_name' => $role])->values()->all());
            $template->items()->createMany(collect($data['items'])->values()->map(fn (array $item, int $index): array => [
                'item_key' => $item['item_key'],
                'label' => $item['label'],
                'guidance' => $item['guidance'] ?? null,
                'sort_order' => ($index + 1) * 10,
                'is_required' => (bool) ($item['is_required'] ?? true),
            ])->all());

            WorkChecklistTemplate::query()
                ->where('template_key', $key)
                ->whereKeyNot($template->id)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $effective->toDateString()))
                ->update(['effective_until' => $effective->subDay()->toDateString()]);

            $this->audit->record($source ? 'work_checklist.template_versioned' : 'work_checklist.template_created', 'work_checklists', $actor, $template, [], $template->only(['template_key', 'version', 'name', 'frequency', 'scope', 'location_type', 'effective_from']), request: $request);

            return $template->load(['roles', 'items']);
        });
    }

    public function deactivateTemplate(WorkChecklistTemplate $template, User $actor, Request $request): void
    {
        DB::transaction(function () use ($template, $actor, $request): void {
            $locked = WorkChecklistTemplate::query()->lockForUpdate()->findOrFail($template->id);
            if ($locked->effective_from->isFuture()) {
                $locked->update(['is_active' => false]);
                $this->audit->record('work_checklist.template_deactivated', 'work_checklists', $actor, $locked, [], ['is_active' => false], request: $request);

                return;
            }
            $end = $locked->frequency === WorkChecklistFrequency::DAILY
                ? now(config('work-checklists.timezone'))->endOfDay()->toDateString()
                : now(config('work-checklists.timezone'))->endOfWeek(CarbonInterface::SUNDAY)->toDateString();
            $locked->update(['effective_until' => $end]);
            $this->audit->record('work_checklist.template_deactivated', 'work_checklists', $actor, $locked, [], ['effective_until' => $end], request: $request);
        });
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(WorkChecklistFrequency $frequency, CarbonImmutable $now): array
    {
        if ($frequency === WorkChecklistFrequency::DAILY) {
            return [$now->startOfDay(), $now->endOfDay()];
        }

        return [$now->startOfWeek(CarbonInterface::MONDAY), $now->endOfWeek(CarbonInterface::SUNDAY)];
    }

    /** @return EloquentCollection<int, WorkLocation> */
    private function activeAssignedLocations(User $user, CarbonImmutable $now): EloquentCollection
    {
        return $user->workLocations()
            ->where('work_locations.is_active', true)
            ->wherePivot('is_active', true)
            ->where(fn ($query) => $query->whereNull('user_work_locations.effective_from')->orWhere('user_work_locations.effective_from', '<=', $now->toDateString()))
            ->where(fn ($query) => $query->whereNull('user_work_locations.effective_until')->orWhere('user_work_locations.effective_until', '>=', $now->toDateString()))
            ->get();
    }

    /**
     * @param  Collection<int, WorkChecklistTemplate>  $templates
     * @param  Collection<int, string>  $userRoles
     */
    private function createRun(User $user, ?WorkLocation $location, WorkChecklistFrequency $frequency, CarbonImmutable $start, CarbonImmutable $end, Collection $templates, Collection $userRoles): WorkChecklist
    {
        return DB::transaction(function () use ($user, $location, $frequency, $start, $end, $templates, $userRoles): WorkChecklist {
            $scopeKey = $location ? 'location:'.$location->id : 'global';
            $roles = $templates->flatMap(fn (WorkChecklistTemplate $template) => $template->roles->pluck('role_name'))->intersect($userRoles)->unique()->values();
            $checklist = WorkChecklist::query()->firstOrCreate(
                ['user_id' => $user->id, 'scope_key' => $scopeKey, 'frequency' => $frequency->value, 'period_start' => $start->startOfDay()],
                [
                    'work_location_id' => $location?->id,
                    'period_end' => $end->toDateString(),
                    'due_at' => $end,
                    'status' => WorkChecklistStatus::OPEN,
                    'role_snapshot' => $roles->all(),
                    'template_snapshot' => $templates->map(fn (WorkChecklistTemplate $template): array => ['id' => $template->id, 'key' => $template->template_key, 'version' => $template->version, 'name' => $template->name])->values()->all(),
                ],
            );

            if (! $checklist->wasRecentlyCreated) {
                return $checklist;
            }

            $merged = collect();
            foreach ($templates as $template) {
                $sourceRoles = $template->roles->pluck('role_name')->intersect($userRoles)->values()->all();
                foreach ($template->items as $templateItem) {
                    $existing = $merged->get($templateItem->item_key);
                    $merged->put($templateItem->item_key, [
                        'item_key' => $templateItem->item_key,
                        'label' => $templateItem->label,
                        'guidance' => $templateItem->guidance,
                        'role_snapshot' => array_values(array_unique(array_merge($existing['role_snapshot'] ?? [], $sourceRoles))),
                        'is_required' => (bool) $templateItem->is_required,
                    ]);
                }
            }

            if ($frequency === WorkChecklistFrequency::DAILY) {
                foreach ((array) config('work-checklists.common_items', []) as $common) {
                    $merged->put($common['key'], [
                        'item_key' => $common['key'],
                        'label' => $common['label'],
                        'guidance' => $common['guidance'] ?? null,
                        'role_snapshot' => $roles->all(),
                        'is_required' => true,
                    ]);
                }
            }

            $checklist->items()->createMany($merged->values()->map(fn (array $item, int $index): array => [...$item, 'sort_order' => ($index + 1) * 10])->all());

            return $checklist->load('items');
        });
    }
}
