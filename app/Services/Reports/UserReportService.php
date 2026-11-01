<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserReportService
{
    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function report(User $actor, array $filters): array
    {
        $users = DB::table('users')
            ->select(['id', 'name', 'username', 'email', 'is_active', 'last_login_at', 'created_at'])
            ->orderByDesc('last_login_at')
            ->limit(100)
            ->get()
            ->map(fn ($user): array => (array) $user)
            ->all();

        $roleDistribution = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->selectRaw('roles.name as role, COUNT(*) as total')
            ->groupBy('roles.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => ['label' => $row->role, 'value' => (int) $row->total])
            ->all();

        $locationAssignments = DB::table('user_work_locations')
            ->join('users', 'users.id', '=', 'user_work_locations.user_id')
            ->join('work_locations', 'work_locations.id', '=', 'user_work_locations.work_location_id')
            ->select(['users.name as user', 'work_locations.name as location', 'work_locations.type', 'user_work_locations.is_default', 'user_work_locations.is_active', 'user_work_locations.effective_from', 'user_work_locations.effective_until'])
            ->orderBy('work_locations.name')
            ->limit(100)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        $audit = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_user_id')
            ->whereBetween('audit_logs.occurred_at', [$filters['start'], $filters['end']])
            ->where(function ($query): void {
                $query->where('audit_logs.module', 'like', 'admin%')
                    ->orWhere('audit_logs.subject_type', User::class);
            })
            ->select(['audit_logs.event', 'audit_logs.severity', 'audit_logs.route_name', 'audit_logs.occurred_at', 'users.name as actor'])
            ->latest('audit_logs.occurred_at')
            ->limit(100)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        return [
            'type' => 'users',
            'filters' => $filters,
            'definitions' => [
                'Data akun tidak menyertakan password, token, session, atau credential.',
                'Aktivitas dibatasi pada periode filter dan audit modul administrasi pengguna.',
            ],
            'summary' => [
                'total_users' => DB::table('users')->count(),
                'active_users' => DB::table('users')->where('is_active', true)->count(),
                'inactive_users' => DB::table('users')->where('is_active', false)->count(),
                'never_logged_in' => DB::table('users')->whereNull('last_login_at')->count(),
                'without_role' => DB::table('users')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('model_has_roles')->whereColumn('model_has_roles.model_id', 'users.id')->where('model_has_roles.model_type', User::class))->count(),
                'without_location' => DB::table('users')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('user_work_locations')->whereColumn('user_work_locations.user_id', 'users.id')->where('user_work_locations.is_active', true))->count(),
            ],
            'rows' => $users,
            'charts' => ['roles' => $roleDistribution],
            'sections' => ['assignments' => $locationAssignments, 'audit' => $audit],
            'last_updated_at' => $filters['last_updated_at'],
        ];
    }
}
