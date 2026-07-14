<?php

namespace App\Http\Controllers\B2B;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Services\B2B\B2bPortalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderController extends Controller
{
    public function index(Request $request, B2bPortalService $portal): View
    {
        $customer = $portal->activeCustomerFor($request->user());
        $items = DB::table('b2b_order_items')
            ->join('b2b_orders', 'b2b_orders.id', '=', 'b2b_order_items.b2b_order_id')
            ->join('products', 'products.id', '=', 'b2b_order_items.product_id')
            ->where('b2b_orders.customer_id', $customer->id)
            ->selectRaw('products.id as product_id, products.sku, products.name, products.base_unit_id, count(*) as order_count, sum(b2b_order_items.quantity) as total_quantity, max(b2b_orders.submitted_at) as last_ordered_at')
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.base_unit_id')
            ->orderByDesc('order_count')
            ->limit(30)
            ->get();

        return view('b2b.reorder.index', ['customer' => $customer, 'items' => $items]);
    }

    public function store(Request $request, B2bPortalService $portal): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $customer = $portal->activeCustomerFor($request->user());

        try {
            foreach ($data['items'] as $item) {
                $portal->addToCart($customer, $request->user(), $item);
            }
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['reorder' => $exception->getMessage()]);
        }

        return redirect()->route('langganan.keranjang.index')->with('notification', ['type' => 'success', 'message' => 'Produk reorder ditambahkan ke keranjang dengan harga terbaru.']);
    }
}
