<?php

namespace App\Http\Controllers\B2B;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\B2B\SubmitB2bOrderRequest;
use App\Services\B2B\B2bPortalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function show(Request $request, B2bPortalService $portal): View
    {
        $customer = $portal->activeCustomerFor($request->user())->load('addresses');
        $cart = $portal->refreshCart($portal->currentCart($customer, $request->user()), $customer, $request->user());

        return view('b2b.checkout.show', [
            'customer' => $customer,
            'cart' => $cart,
            'totals' => $portal->cartTotals($cart->items),
        ]);
    }

    public function store(SubmitB2bOrderRequest $request, B2bPortalService $portal): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());

        try {
            $order = $portal->submitOrder($customer, $request->user(), $request->validated());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['checkout' => $exception->getMessage()]);
        }

        return redirect()->route('langganan.orders.show', $order)
            ->with('notification', ['type' => 'success', 'message' => 'Order berhasil dikirim dan menunggu validasi gudang.']);
    }
}
