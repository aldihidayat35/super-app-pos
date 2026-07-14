<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StoreSpecialPriceRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPriceOverride;
use App\Models\Product;
use App\Services\Pricing\PriceManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpecialPriceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('prices.view'), 403);

        return view('pricing.special-prices.index', [
            'overrides' => CustomerPriceOverride::query()->with(['customer', 'product', 'branch'])->latest('id')->paginate(15),
            'customers' => Customer::query()->where('is_active', true)->orderBy('business_name')->limit(500)->get(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSpecialPriceRequest $request, PriceManagementService $service): RedirectResponse
    {
        $override = $service->saveCustomerOverride($request->validated(), $request->user());
        $message = $override->status === 'pending'
            ? 'Harga khusus dibuat dan menunggu approval.'
            : 'Harga khusus berhasil disimpan.';

        return back()->with('notification', ['type' => 'success', 'message' => $message]);
    }
}
