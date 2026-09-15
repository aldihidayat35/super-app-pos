<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannelType;
use App\Jobs\SendNotificationJob;
use App\Models\Branch;
use App\Models\BusinessNotificationSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BusinessNotificationService
{
    public function __construct(private readonly NotificationDispatchService $dispatcher) {}

    /**
     * @param  list<string>|null  $roles
     * @param  list<int>  $userIds
     */
    public function send(string $eventKey, string $title, string $message, ?int $workLocationId = null, ?string $url = null, string|int|null $subjectId = null, ?array $roles = null, array $userIds = [], ?bool $locationScoped = null): int
    {
        $setting = BusinessNotificationSetting::query()->where('event_key', $eventKey)->first();
        if (! $setting?->is_active) {
            return 0;
        }

        $configuredRoles = $setting->getAttribute('recipient_roles');
        $roleNames = $roles ?? (is_array($configuredRoles)
            ? array_values(array_filter($configuredRoles, is_string(...)))
            : []);
        $query = User::query()->where('is_active', true)->where(function (Builder $query): void {
            $query->whereNotNull('phone_number')->orWhereHas('employee', fn (Builder $employee): Builder => $employee->whereNotNull('whatsapp_number'));
        });
        if ($userIds !== []) {
            $query->whereIn('id', $userIds);
        } elseif ($roleNames !== []) {
            $query->role($roleNames);
        } else {
            return 0;
        }
        if (($locationScoped ?? $setting->location_scoped) && $workLocationId !== null) {
            $globalRecipientRoles = array_values(array_intersect($roleNames, ['owner_viewer', 'owner_approver', 'super_admin', 'admin_config', 'purchasing', 'sales']));
            $query->where(function (Builder $recipients) use ($workLocationId, $globalRecipientRoles): void {
                $recipients->whereHas('workLocations', fn (Builder $location): Builder => $location->where('work_locations.id', $workLocationId)->where('user_work_locations.is_active', true));
                if ($globalRecipientRoles !== []) {
                    $recipients->orWhereHas('roles', fn (Builder $role): Builder => $role->whereIn('name', $globalRecipientRoles));
                }
            });
        }

        $body = "*{$title}*\n{$message}".($url ? "\n\nBuka aplikasi: {$url}" : '');
        $now = now('Asia/Jakarta');
        $bucket = match (true) {
            $setting->cooldown_minutes > 0 => (string) intdiv($now->timestamp, $setting->cooldown_minutes * 60),
            $subjectId !== null => 'once-per-subject',
            default => $now->format('YmdHis'),
        };
        $queued = 0;

        foreach ($query->with('employee')->get() as $user) {
            $destination = $user->employee?->whatsapp_number ?: $user->phone_number;
            if (! filled($destination)) {
                continue;
            }
            $key = hash('sha256', implode('|', ['business-wa', $eventKey, $subjectId ?? 'general', $workLocationId ?? 'global', $user->id, $bucket]));
            $log = $this->dispatcher->queueLog(NotificationChannelType::WHATSAPP, (string) $destination, $body, actor: null, payload: [
                'source' => 'business_event', 'event_key' => $eventKey, 'work_location_id' => $workLocationId, 'subject_id' => $subjectId,
            ], idempotencyKey: $key, subject: $title, recipientUser: $user);
            if ($log->wasRecentlyCreated) {
                DB::afterCommit(fn () => SendNotificationJob::dispatch($log->id));
                $queued++;
            }
        }

        return $queued;
    }

    public function ownerReport(string $trigger, ?WorkLocation $location = null, string|int|null $subjectId = null): int
    {
        $eventKey = $trigger === 'nightly' ? 'owner_report_nightly' : 'owner_report_head_checklist';
        $locationLabel = $location ? $location->typeLabel().' '.$location->name : 'seluruh lokasi';
        $dashboard = $this->dashboardUrl($location);
        $dailyReport = route('reports.daily.index', array_filter([
            'work_location_id' => $location?->id,
            'report_scope' => $location?->type === 'branch' ? 'retail' : ($location?->type === 'warehouse' ? 'warehouse' : 'all'),
        ]));
        $storeLinks = '';
        if ($location === null) {
            $branches = Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
            if ($branches->isNotEmpty()) {
                $storeLinks = "\n\nDashboard toko:\n".$branches
                    ->map(fn (Branch $branch): string => "- {$branch->name}: ".route('retail.dashboard', ['branch_id' => $branch->id]))
                    ->implode("\n");
            }
        }

        return $this->send(
            $eventKey,
            $trigger === 'nightly' ? 'Laporan Operasional Harian' : 'Checklist Kepala Lokasi Selesai',
            "Ringkasan {$locationLabel} siap diperiksa.\nDashboard utama: {$dashboard}\nLaporan harian: {$dailyReport}{$storeLinks}",
            $location?->id,
            $dashboard,
            $subjectId ?? now('Asia/Jakarta')->toDateString(),
        );
    }

    private function dashboardUrl(?WorkLocation $location): string
    {
        if ($location?->type === 'branch') {
            $branchId = Branch::query()->where('work_location_id', $location->id)->value('id');

            return route('retail.dashboard', array_filter(['branch_id' => $branchId]));
        }
        if ($location?->type === 'warehouse') {
            $warehouseId = Warehouse::query()->where('work_location_id', $location->id)->value('id');

            return route('warehouse.dashboard', array_filter(['warehouse_id' => $warehouseId]));
        }

        return route('owner.dashboard');
    }
}
