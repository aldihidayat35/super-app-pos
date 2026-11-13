<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\WorkLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkLocationSwitchController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_location_id' => ['required', 'integer', 'exists:work_locations,id'],
        ]);

        $user = $request->user();
        $locationId = (int) $validated['work_location_id'];

        if (! $user || ! $user->canAccessWorkLocation($locationId)) {
            abort(403, 'Anda tidak memiliki hak akses ke lokasi operasional ini.');
        }

        $location = WorkLocation::query()->findOrFail($locationId);

        $request->session()->put('work_location.id', $location->id);
        $request->session()->put('work_location.type', $location->type);

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Lokasi operasional aktif dialihkan ke '.$location->name.'.',
        ]);
    }
}
