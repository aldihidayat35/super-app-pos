<?php

namespace App\Http\Controllers\B2B;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\B2B\SubmitB2bOrderRequest;
use App\Http\Requests\B2B\UpdateCartRequest;
use App\Models\B2bCartItem;
use App\Services\B2B\B2bPortalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request, B2bPortalService $portal): View
    {
        $customer = $portal->activeCustomerFor($request->user());
        $cart = $portal->refreshCart($portal->currentCart($customer, $request->user()), $customer, $request->user());

        return view('b2b.cart.index', [
            'customer' => $customer->load('addresses'),
            'cart' => $cart,
            'totals' => $portal->cartTotals($cart->items),
        ]);
    }

    public function update(UpdateCartRequest $request, B2bPortalService $portal): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());

        try {
            $portal->updateCart($customer, $request->user(), $request->validated('items', []));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Keranjang berhasil diperbarui dengan harga terbaru.']);
    }

    public function destroy(Request $request, B2bCartItem $item, B2bPortalService $portal): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());
        abort_unless((int) $item->cart?->customer_id === (int) $customer->id && (int) $item->cart?->user_id === (int) $request->user()->id, 403);
        $portal->removeItem($customer, $request->user(), $item->id);

        return back()->with('notification', ['type' => 'success', 'message' => 'Item dihapus dari keranjang.']);
    }

    public function submit(SubmitB2bOrderRequest $request, B2bPortalService $portal): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());

        try {
            $order = $portal->submitOrder($customer, $request->user(), $request->validated());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['checkout' => $exception->getMessage()]);
        }

        return redirect()->route('langganan.orders.show', $order)
            ->with('notification', ['type' => 'success', 'message' => 'Order langganan berhasil diajukan. Tim kami akan memprosesnya.']);
    }
}
