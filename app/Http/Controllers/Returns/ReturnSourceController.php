<?php

namespace App\Http\Controllers\Returns;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Models\ReturnDocument;
use App\Models\WarehouseLocation;
use App\Services\Returns\ReturnSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnSourceController extends Controller
{
    public function documents(Request $request, ReturnSourceService $sources): JsonResponse
    {
        $data = $request->validate([
            'source_type' => ['required', Rule::in(['pos', 'b2b', 'supplier', 'transfer'])],
            'work_location_id' => ['required', 'integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:50'],
        ]);
        $workLocationId = (int) $data['work_location_id'];
        abort_unless($request->user()?->canAccessWorkLocation($workLocationId), 403);

        return response()->json($sources->searchDocuments(
            (string) $data['source_type'],
            $workLocationId,
            trim((string) ($data['q'] ?? '')),
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 20),
        ));
    }

    public function items(Request $request, ReturnSourceService $sources): JsonResponse
    {
        $data = $request->validate([
            'source_type' => ['required', Rule::in(array_keys(ReturnSourceService::sourceOptions()))],
            'work_location_id' => ['required', 'integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'exclude_return_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $workLocationId = (int) $data['work_location_id'];
        abort_unless($request->user()?->canAccessWorkLocation($workLocationId), 403);
        $showCost = $request->user()->can('margins.view_sensitive');
        $sourceType = (string) $data['source_type'];
        $excludeReturnId = isset($data['exclude_return_id']) ? (int) $data['exclude_return_id'] : null;
        if ($excludeReturnId !== null) {
            $return = ReturnDocument::query()->findOrFail($excludeReturnId);
            $this->authorize('update', $return);
            abort_unless((int) $return->work_location_id === $workLocationId, 403);
        }

        try {
            $items = $sourceType === 'manual'
                ? $sources->manualItems(trim((string) ($data['q'] ?? '')), $showCost)
                : $sources->documentItems($sourceType, (int) ($data['reference_id'] ?? 0), $workLocationId, $showCost, $excludeReturnId);
        } catch (ServiceException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['source' => [$exception->getMessage()]],
            ], $exception->httpStatus);
        }

        return response()->json(['results' => $items, 'pagination' => ['more' => false]]);
    }

    public function locations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'work_location_id' => ['required', 'integer', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $workLocationId = (int) $data['work_location_id'];
        abort_unless($request->user()?->canAccessWorkLocation($workLocationId), 403);
        $search = trim((string) ($data['q'] ?? ''));
        $locations = WarehouseLocation::query()
            ->where('is_active', true)
            ->whereHas('warehouse', fn ($query) => $query->where('work_location_id', $workLocationId))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('full_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('full_code')->paginate(30, ['*'], 'page', (int) ($data['page'] ?? 1));

        return response()->json([
            'results' => $locations->getCollection()->map(fn (WarehouseLocation $location): array => [
                'id' => $location->id,
                'text' => $location->full_code.' - '.($location->name ?: $location->type),
            ])->values(),
            'pagination' => ['more' => $locations->hasMorePages()],
        ]);
    }
}
