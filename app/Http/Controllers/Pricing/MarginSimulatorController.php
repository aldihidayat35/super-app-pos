<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\SimulatePriceRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Pricing\PriceResolverService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MarginSimulatorController extends Controller
{
    public function index(Request $request, PriceResolverService $resolver): View
    {
        abort_unless($request->user()?->can('prices.view'), 403);
        $result = null;

        if ($request->filled('product_id')) {
            $validated = Validator::make($request->all(), (new SimulatePriceRequest)->rules())->validate();
            $product = Product::query()->findOrFail($validated['product_id']);
            $branch = isset($validated['branch_id']) ? Branch::query()->find($validated['branch_id']) : null;
            $customer = isset($validated['customer_id']) ? Customer::query()->find($validated['customer_id']) : null;
            $result = $resolver->resolve($product, $validated['quantity'], $validated['unit_id'] ?? null, $branch, $customer, $validated['channel'], user: $request->user(), requestedPrice: $validated['requested_price'] ?? null, discountPercent: $validated['discount_percent'] ?? 0);
        }

        return view('pricing.simulator.index', [
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('business_name')->limit(500)->get(),
            'result' => $result,
            'filters' => $request->all(),
        ]);
    }
}
