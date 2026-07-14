<?php

namespace App\Http\Controllers\Retail;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\StorePosReturnRequest;
use App\Http\Requests\Retail\VoidPosSaleRequest;
use App\Models\PosSale;
use App\Services\Retail\PosService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosSaleController extends Controller
{
    public function show(PosSale $sale): View
    {
        $this->authorize('view', $sale);

        return view('retail.sales.show', [
            'sale' => $sale->load(['items.product', 'items.unit', 'payments', 'branch', 'cashier', 'customer', 'stockMutations']),
        ]);
    }

    public function print(Request $request, PosSale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->increment('receipt_print_count');
        $sale->forceFill(['last_printed_at' => now()])->save();

        return view('retail.sales.receipt', [
            'sale' => $sale->load(['items.product', 'payments', 'branch', 'cashier', 'customer']),
        ]);
    }

    public function voidForm(PosSale $sale): View
    {
        $this->authorize('void', $sale);

        return view('retail.sales.void', ['sale' => $sale->load(['items', 'payments', 'branch', 'cashier'])]);
    }

    public function void(VoidPosSaleRequest $request, PosSale $sale, PosService $service): RedirectResponse
    {
        $this->authorize('void', $sale);

        try {
            $service->voidSale($sale, $request->user(), $request->validated()['reason']);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['void' => $exception->getMessage()]);
        }

        return redirect()->route('retail.sales.show', $sale)
            ->with('notification', ['type' => 'success', 'message' => 'Transaksi berhasil di-void dan stok dikembalikan.']);
    }

    public function returnForm(PosSale $sale): View
    {
        $this->authorize('return', $sale);

        return view('retail.sales.return', ['sale' => $sale->load(['items.product', 'branch', 'cashier'])]);
    }

    public function return(StorePosReturnRequest $request, PosSale $sale, PosService $service): RedirectResponse
    {
        $this->authorize('return', $sale);

        try {
            $service->returnSale($sale, $request->validated(), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['return' => $exception->getMessage()]);
        }

        return redirect()->route('retail.sales.show', $sale)
            ->with('notification', ['type' => 'success', 'message' => 'Retur POS berhasil dicatat tanpa menghapus transaksi asal.']);
    }
}
