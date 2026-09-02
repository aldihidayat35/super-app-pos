<?php

namespace App\Http\Controllers\Sales;

use App\Enums\B2bOrderStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Models\B2bOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Services\B2B\B2bPortalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('sales.orders.view_own'), 403);
        $filters = $request->only(['q', 'status', 'from', 'to']);

        return view('sales.orders.index', [
            'filters' => $filters,
            'statuses' => B2bOrderStatus::options(),
            'orders' => B2bOrder::query()
                ->where('sales_user_id', $request->user()->id)
                ->with(['customer', 'latestShipment'])
                ->when($filters['q'] ?? null, function ($query, string $term): void {
                    $query->where(fn ($inner) => $inner->where('number', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('business_name', 'like', "%{$term}%")));
                })
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['from'] ?? null, fn ($query, string $date) => $query->whereDate('submitted_at', '>=', $date))
                ->when($filters['to'] ?? null, fn ($query, string $date) => $query->whereDate('submitted_at', '<=', $date))
                ->latest('submitted_at')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('sales.orders.create'), 403);
        $customers = Customer::query()
            ->where('sales_user_id', $request->user()->id)
            ->where('is_active', true)
            ->with(['addresses' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('label')])
            ->orderBy('business_name')
            ->get();

        return view('sales.orders.create', [
            'customers' => $customers,
            'products' => Product::query()
                ->with('baseUnit')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'selectedCustomerId' => $request->integer('customer_id') ?: null,
        ]);
    }

    public function store(StoreSalesOrderRequest $request, B2bPortalService $portal): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($request->integer('customer_id'));
        $this->authorize('view', $customer);

        try {
            $order = $portal->submitForSales(
                $customer,
                $request->user(),
                $request->validated('items'),
                [...$request->safe()->except(['customer_id', 'items']), 'idempotency_key' => 'sales-'.Str::uuid()],
            );
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['order' => $exception->getMessage()]);
        }

        return redirect()->route('sales.orders.show', $order)
            ->with('notification', ['type' => 'success', 'message' => 'Order berhasil dibuat dan masuk ke alur validasi B2B.']);
    }

    public function show(Request $request, B2bOrder $order): View
    {
        abort_unless($request->user()->can('sales.orders.view_own'), 403);
        abort_unless((int) $order->sales_user_id === (int) $request->user()->id, 403);

        return view('sales.orders.show', [
            'order' => $order->load(['customer', 'address', 'items.product', 'latestShipment', 'statusHistories.actor']),
        ]);
    }
}
