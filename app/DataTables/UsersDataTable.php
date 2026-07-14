<?php

namespace App\DataTables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;

class UsersDataTable
{
    public function __construct(protected Request $request) {}

    public function query(User $model): EloquentBuilder
    {
        $query = $model->newQuery()
            ->with(['roles', 'workLocations']);

        // Filters
        if ($q = trim((string) $this->request->input('q'))) {
            $query->where(function (EloquentBuilder $qBuilder) use ($q) {
                $qBuilder->where('name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone_number', 'like', "%{$q}%");
            });
        }

        if ($roleId = $this->request->input('role')) {
            $query->whereHas('roles', fn ($q) => $q->whereKey((int) $roleId));
        }

        if ($locId = $this->request->input('location')) {
            $query->whereHas('workLocations', fn ($q) => $q->whereKey((int) $locId));
        }

        $status = $this->request->input('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    public function paginate(): array
    {
        $draw   = (int) $this->request->input('draw', 0);
        $start  = (int) $this->request->input('start', 0);
        $length = (int) $this->request->input('length', 15);

        if ($length < 1) {
            $length = 15;
        }

        $query = $this->query(new User());

        $recordsTotal = User::count();

        // Apply ordering
        $orderColIdx = $this->request->input('order.0.column');
        $orderDir    = $this->request->input('order.0.dir', 'desc');
        $columns     = $this->request->input('columns', []);

        if ($orderColIdx !== null && isset($columns[$orderColIdx]['name']) && $columns[$orderColIdx]['name'] !== '') {
            $colName = $columns[$orderColIdx]['name'];
            $map = [
                'name'         => 'users.name',
                'email'        => 'users.email',
                'status'       => 'users.is_active',
                'login_at'     => 'users.last_login_at',
                'roles'        => null,
                'location'     => null,
            ];
            if (isset($map[$colName]) && $map[$colName] !== null) {
                $query->orderBy($map[$colName], $orderDir === 'asc' ? 'asc' : 'desc');
            } else {
                $query->latest('id');
            }
        } else {
            $query->latest('id');
        }

        $recordsFiltered = (clone $query)->count();
        $rows            = $query->offset($start)->limit($length)->get();

        return [
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows->map(fn (User $u) => $this->row($u))->all(),
        ];
    }

    private function row(User $user): array
    {
        $primary = $user->workLocations->firstWhere('pivot.is_default', true);

        $locationHtml = $primary
            ? '<div class="fw-semibold">'.e($primary->name).'</div><div class="text-muted fs-7">'.e($primary->typeLabel()).'</div>'
            : '<span class="text-muted">Belum ada</span>';

        $roleBadges = $user->roles->map(fn ($r) => '<span class="badge badge-light-primary me-1">'.e(str_replace('_', ' ', $r->name)).'</span>')->implode('')
            ?: '<span class="text-muted">Belum ada</span>';

        $statusBadge = $user->is_active
            ? '<span class="badge badge-light-success">Aktif</span>'
            : '<span class="badge badge-light-secondary">Nonaktif</span>';

        $loginAt = $user->last_login_at
            ? $user->last_login_at->timezone(config('app.timezone'))->format('d/m/Y H:i')
            : '-';

        $detailUrl = route('admin.users.show', $user);
        $editUrl   = route('admin.users.edit', $user);

        $actions = '<a href="'.$detailUrl.'" class="btn btn-sm btn-light me-1">Detail</a>'
            .'<a href="'.$editUrl.'" class="btn btn-sm btn-light-primary me-1">Edit</a>';

        return [
            'id'            => $user->id,
            'name'          => '<a href="'.$detailUrl.'" class="fw-bold text-gray-900 text-hover-primary">'.e($user->name).'</a>'
                .'<div class="text-muted fs-7">'.e($user->username).'</div>',
            'email'         => e($user->email),
            'phone_number'  => e($user->phone_number ?: '-'),
            'roles'         => $roleBadges,
            'location'      => $locationHtml,
            'status'        => $statusBadge,
            'login_at'      => e($loginAt),
            'action'        => $actions,
            'route_detail'  => $detailUrl,
        ];
    }
}
