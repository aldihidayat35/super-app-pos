<?php

namespace App\Services\Warehouse;

use App\Exceptions\ServiceException;
use App\Models\StockAdjustmentAccess;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Control\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentAccessService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function assertActive(WorkLocation|int $location): StockAdjustmentAccess
    {
        $locationId = $location instanceof WorkLocation ? $location->id : $location;
        $access = StockAdjustmentAccess::query()->where('work_location_id', $locationId)->first();

        if (! $access?->isCurrentlyActive()) {
            throw ServiceException::validation('Koreksi stok langsung belum diaktifkan atau masa aktivasinya sudah berakhir.');
        }

        return $access;
    }

    /** @param array{active_from:string, active_until:string, reason:string} $data */
    public function activate(WorkLocation $location, array $data, User $actor, ?Request $request = null): StockAdjustmentAccess
    {
        return DB::transaction(function () use ($location, $data, $actor, $request): StockAdjustmentAccess {
            if (! in_array($location->type, ['warehouse', 'branch'], true)) {
                throw ServiceException::validation('Akses koreksi hanya dapat diaktifkan untuk toko atau gudang.');
            }

            $access = StockAdjustmentAccess::query()->lockForUpdate()->firstOrNew(['work_location_id' => $location->id]);
            $before = $access->exists ? $access->toArray() : [];
            $action = $access->exists ? 'extended' : 'activated';
            $access->fill([
                'is_active' => true,
                'active_from' => $data['active_from'],
                'active_until' => $data['active_until'],
                'reason' => $data['reason'],
                'activated_by' => $actor->id,
                'activated_at' => now(),
                'deactivated_by' => null,
                'deactivated_at' => null,
            ])->save();
            $this->history($access, $action, $data['reason'], $actor);
            $this->audit->record('stock_adjustment_access.'.$action, 'stock_adjustments', $actor, $access, $before, $access->toArray(), $data['reason'], $request, $location, 'warning');

            return $access->fresh('workLocation');
        });
    }

    public function deactivate(StockAdjustmentAccess $access, string $reason, User $actor, ?Request $request = null): StockAdjustmentAccess
    {
        return DB::transaction(function () use ($access, $reason, $actor, $request): StockAdjustmentAccess {
            $access = StockAdjustmentAccess::query()->with('workLocation')->lockForUpdate()->findOrFail($access->id);
            $before = $access->toArray();
            $access->forceFill(['is_active' => false, 'reason' => $reason, 'deactivated_by' => $actor->id, 'deactivated_at' => now()])->save();
            $this->history($access, 'deactivated', $reason, $actor);
            $this->audit->record('stock_adjustment_access.deactivated', 'stock_adjustments', $actor, $access, $before, $access->toArray(), $reason, $request, $access->workLocation, 'warning');

            return $access;
        });
    }

    private function history(StockAdjustmentAccess $access, string $action, string $reason, User $actor): void
    {
        $access->histories()->create([
            'work_location_id' => $access->work_location_id,
            'action' => $action,
            'is_active' => $access->is_active,
            'active_from' => $access->active_from,
            'active_until' => $access->active_until,
            'reason' => $reason,
            'actor_user_id' => $actor->id,
            'occurred_at' => now(),
        ]);
    }
}
