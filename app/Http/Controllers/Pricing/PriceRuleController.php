<?php

namespace App\Http\Controllers\Pricing;

use App\Enums\PricingChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StorePriceRuleRequest;
use App\Models\Branch;
use App\Models\PriceRule;
use App\Services\Pricing\PriceManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PriceRuleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PriceRule::class);

        return view('pricing.rules.index', [
            'rules' => PriceRule::query()->with('branch')->orderBy('priority')->paginate(15),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'channels' => PricingChannel::options(),
        ]);
    }

    public function store(StorePriceRuleRequest $request, PriceManagementService $service): RedirectResponse
    {
        $service->saveRule($request->validated());

        return back()->with('notification', ['type' => 'success', 'message' => 'Aturan harga berhasil disimpan.']);
    }
}
