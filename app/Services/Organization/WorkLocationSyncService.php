<?php

namespace App\Services\Organization;

use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use DomainException;

class WorkLocationSyncService
{
    public function syncWarehouse(Warehouse $warehouse): WorkLocation
    {
        $location = $this->resolveLocation($warehouse->workLocation, 'warehouse', $warehouse->code);
        $location->fill([
            'type' => 'warehouse',
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'is_active' => $warehouse->is_active,
        ])->save();

        if (! $warehouse->work_location_id) {
            $warehouse->forceFill(['work_location_id' => $location->id])->save();
        }

        return $location;
    }

    public function syncBranch(Branch $branch): WorkLocation
    {
        $location = $this->resolveLocation($branch->workLocation, 'branch', $branch->code);
        $location->fill([
            'type' => 'branch',
            'code' => $branch->code,
            'name' => $branch->name,
            'is_active' => $branch->is_active,
        ])->save();

        if (! $branch->work_location_id) {
            $branch->forceFill(['work_location_id' => $location->id])->save();
        }

        return $location;
    }

    private function resolveLocation(?WorkLocation $currentLocation, string $type, string $code): WorkLocation
    {
        if ($currentLocation) {
            return $currentLocation;
        }

        $location = WorkLocation::query()->where('code', $code)->first();

        if ($location && $location->type !== $type) {
            throw new DomainException("Work location code [{$code}] already belongs to another location type.");
        }

        return $location ?: new WorkLocation(['type' => $type]);
    }
}
