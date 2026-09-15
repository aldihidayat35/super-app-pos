<?php

namespace App\Support;

use App\Models\User;
use App\Models\WorkLocation;

final class ApprovalAuthority
{
    public const STORE_HEAD = 'kepala_toko';

    public const WAREHOUSE_HEAD = 'kepala_gudang';

    public const SYSTEM_HEAD = 'admin_config';

    public static function roleForLocation(?WorkLocation $location, string $fallback = self::WAREHOUSE_HEAD): string
    {
        return match ($location?->type) {
            'branch' => self::STORE_HEAD,
            'warehouse' => self::WAREHOUSE_HEAD,
            default => $fallback,
        };
    }

    public static function canApproveAt(User $user, ?int $workLocationId, string $requiredRole): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->hasRole($requiredRole)) {
            return false;
        }

        return $workLocationId === null || $user->canAccessWorkLocation($workLocationId);
    }
}
