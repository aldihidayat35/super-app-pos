<?php

namespace App\Http\Middleware;

use App\Data\WorkLocationContext;
use App\Models\WorkLocation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkLocation
{
    /** @var list<string> */
    private const ALLOWED_TYPES = ['warehouse', 'branch'];

    public function handle(Request $request, Closure $next): Response
    {
        $type = $request->session()->get('work_location.type');
        $id = $request->session()->get('work_location.id');

        if (! is_string($type) || ! in_array($type, self::ALLOWED_TYPES, true) || ! is_numeric($id)) {
            $type = null;
            $id = null;
        }

        $locationId = $id === null ? null : (int) $id;
        $user = $request->user();

        if ($type !== null && $locationId !== null) {
            $isValidLocation = WorkLocation::query()
                ->whereKey($locationId)
                ->where('type', $type)
                ->where('is_active', true)
                ->exists();

            if (! $isValidLocation || ($user && ! $user->canAccessWorkLocation($locationId))) {
                $type = null;
                $locationId = null;
                $request->session()->forget(['work_location.type', 'work_location.id']);
            }
        }

        app()->instance(WorkLocationContext::class, new WorkLocationContext($type, $locationId));

        return $next($request);
    }
}
