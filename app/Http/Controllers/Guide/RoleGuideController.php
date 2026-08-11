<?php

namespace App\Http\Controllers\Guide;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Guide\RoleGuideService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class RoleGuideController extends Controller
{
    public function index(Request $request, RoleGuideService $guides): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('guides.index', [
            'guides' => $guides->availableFor($user),
            'userRoles' => $guides->userRoles($user),
        ]);
    }

    public function show(Request $request, string $guide, RoleGuideService $guides): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $selectedGuide = $guides->findFor($user, $guide);

        return view('guides.show', [
            'guide' => $selectedGuide,
            'rendered' => $guides->render($selectedGuide),
            'availableGuides' => $guides->availableFor($user),
            'userRoles' => $guides->userRoles($user),
        ]);
    }
}
