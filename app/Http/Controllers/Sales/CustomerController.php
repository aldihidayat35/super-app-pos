<?php

namespace App\Http\Controllers\Sales;

use App\Enums\B2bOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\B2bOrder;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);
        $salesId = $request->user()->id;
        $term = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->where('sales_user_id', $salesId)
            ->withCount(['b2bOrders as total_orders' => fn ($query) => $query
                ->where('sales_user_id', $salesId)
                ->whereNotIn('status', [B2bOrderStatus::CANCELLED->value, B2bOrderStatus::REJECTED->value])])
            ->withSum(['b2bOrders as total_sales' => fn ($query) => $query
                ->where('sales_user_id', $salesId)
                ->where('status', B2bOrderStatus::COMPLETED->value)], 'grand_total_amount')
            ->addSelect(['last_order_at' => B2bOrder::query()
                ->select('submitted_at')
                ->whereColumn('customer_id', 'customers.id')
                ->where('sales_user_id', $salesId)
                ->latest('submitted_at')
                ->limit(1)])
            ->when($term !== '', fn ($query) => $query->where(function ($inner) use ($term): void {
                $inner->where('business_name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('whatsapp_number', 'like', "%{$term}%");
            }))
            ->orderBy('business_name')
            ->paginate(15)
            ->withQueryString();

        return view('sales.customers.index', compact('customers', 'term'));
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);
        abort_unless((int) $customer->sales_user_id === (int) $request->user()->id, 403);

        $orders = $customer->b2bOrders()
            ->where('sales_user_id', $request->user()->id)
            ->with('latestShipment')
            ->latest('submitted_at')
            ->paginate(10);

        return view('sales.customers.show', compact('customer', 'orders'));
    }
}
