<?php

namespace App\Http\Controllers\B2B;

use App\Enums\B2bOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\B2bOrder;
use App\Services\B2B\B2bPortalService;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, B2bPortalService $portal): View
    {
        $customer = $portal->activeCustomerFor($request->user());
        $activeOrders = B2bOrder::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                B2bOrderStatus::PENDING_CONFIRMATION->value,
                B2bOrderStatus::WAREHOUSE_VALIDATION->value,
                B2bOrderStatus::RESERVED->value,
                B2bOrderStatus::INVOICE_READY->value,
                B2bOrderStatus::AWAITING_PAYMENT->value,
                B2bOrderStatus::APPROVED_CREDIT->value,
                B2bOrderStatus::PACKING->value,
                B2bOrderStatus::SHIPPED->value,
            ])
            ->with('items')
            ->latest('submitted_at')
            ->limit(5)
            ->get();

        $creditAvailable = Decimal::sub((string) $customer->credit_limit, (string) $customer->receivable_balance, 2);

        return view('b2b.dashboard.index', [
            'customer' => $customer,
            'activeOrders' => $activeOrders,
            'latestOrders' => B2bOrder::query()->where('customer_id', $customer->id)->latest('id')->limit(5)->get(),
            'creditAvailable' => $creditAvailable,
            'cart' => $portal->currentCart($customer, $request->user()),
        ]);
    }
}
