<?php

namespace App\Http\Controllers\Pricing;

use App\Enums\PricingChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StorePriceRuleRequest;
use App\Models\Branch;
use App\Models\PriceRule;
use App\Services\Pricing\PriceManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PriceRuleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PriceRule::class);

        $query = PriceRule::query()->with('branch');

        if ($request->filled('q')) {
            $search = '%'.trim((string) $request->input('q')).'%';
            $query->where(function ($sub) use ($search): void {
                $sub->where('name', 'like', $search)
                    ->orWhere('notes', 'like', $search)
                    ->orWhere('customer_category', 'like', $search);
            });
        }

        if ($request->filled('channel') && $request->input('channel') !== 'all_channels') {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->input('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $rules = $query->orderBy('priority')->orderBy('id')->paginate(15)->withQueryString();

        return view('pricing.rules.index', [
            'rules' => $rules,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'channels' => PricingChannel::options(),
            'filters' => [
                'q' => $request->input('q', ''),
                'channel' => $request->input('channel', 'all_channels'),
                'branch_id' => $request->input('branch_id', ''),
                'status' => $request->input('status', ''),
            ],
        ]);
    }

    public function store(StorePriceRuleRequest $request, PriceManagementService $service): JsonResponse|RedirectResponse
    {
        $rule = $service->saveRule($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Aturan harga berhasil disimpan.',
                'rule' => $rule,
            ]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Aturan harga berhasil disimpan.']);
    }

    public function toggle(PriceRule $priceRule): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $priceRule);

        $priceRule->is_active = ! $priceRule->is_active;
        $priceRule->save();

        $statusText = $priceRule->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $message = "Aturan harga '{$priceRule->name}' berhasil {$statusText}.";

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $priceRule->is_active,
                'message' => $message,
            ]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => $message]);
    }

    public function destroy(PriceRule $priceRule): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $priceRule);

        $name = $priceRule->name;
        $priceRule->delete();

        $message = "Aturan harga '{$name}' berhasil dihapus.";

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => $message]);
    }
}
