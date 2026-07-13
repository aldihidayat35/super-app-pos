<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $view = match (true) {
            $user->hasAnyRole(['owner', 'owner_viewer', 'owner_approver']) => 'dashboards.owner',
            $user->hasAnyRole(['kepala_gudang', 'staff_gudang', 'picker_packer', 'purchasing']) => 'dashboards.warehouse',
            $user->hasAnyRole(['kepala_toko', 'kasir', 'supervisor_shift']) => 'dashboards.retail',
            default => 'dashboards.super-admin',
        };

        return view($view);
    }
}
