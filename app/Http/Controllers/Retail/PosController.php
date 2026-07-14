<?php

namespace App\Http\Controllers\Retail;

use App\Enums\PaymentMethod;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\CancelPosHoldRequest;
use App\Http\Requests\Retail\StorePosHoldRequest;
use App\Http\Requests\Retail\StorePosSaleRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PosHold;
use App\Models\Product;
use App\Models\Stock;
use App\Services\Retail\PosService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('pos.view'), 403);

        $locationIds = $request->user()->permittedWorkLocationIds();
        $products = Product::query()
            ->with(['baseUnit', 'barcodes'])
            ->where('status', 'active')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->query('q').'%';
                $query->where(function ($search) use ($term): void {
                    $search->where('sku', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhereHas('barcodes', fn ($barcode) => $barcode->where('code', 'like', $term));
                });
            })
            ->whereHas('stocks', fn ($query) => $query->whereIn('work_location_id', $locationIds))
            ->orderBy('name')
            ->limit(80)
            ->get();

        return view('retail.pos.index', [
            'branches' => Branch::query()->where('is_active', true)->whereIn('work_location_id', $locationIds)->orderBy('name')->get(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('business_name')->limit(200)->get(),
            'products' => $products,
            'stocks' => Stock::query()->whereIn('work_location_id', $locationIds)->get()->keyBy('product_id'),
            'paymentMethods' => PaymentMethod::options(),
            'filters' => $request->only(['q']),
        ]);
    }

    public function store(StorePosSaleRequest $request, PosService $service): RedirectResponse
    {
        try {
            $sale = $service->checkout($request->validated(), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['checkout' => $exception->getMessage()]);
        }

        return redirect()->route('retail.sales.show', $sale)
            ->with('notification', ['type' => 'success', 'message' => 'Transaksi POS berhasil disimpan dan stok sudah berkurang.']);
    }

    public function checkout(Request $request): View
    {
        abort_unless($request->user()->can('pos.create'), 403);

        return view('retail.pos.checkout', [
            'branches' => Branch::query()->where('is_active', true)->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('business_name')->limit(200)->get(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function holds(Request $request): View
    {
        abort_unless($request->user()->can('pos.create'), 403);

        return view('retail.pos.holds', [
            'holds' => PosHold::query()
                ->with(['branch', 'customer', 'cashier'])
                ->where('cashier_user_id', $request->user()->id)
                ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function storeHold(StorePosHoldRequest $request, PosService $service): RedirectResponse
    {
        try {
            $service->hold($request->validated(), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['hold' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Keranjang berhasil ditahan.']);
    }

    public function resumeHold(Request $request, PosHold $hold, PosService $service): RedirectResponse
    {
        $this->authorize('update', $hold);

        try {
            $service->resumeHold($hold, $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['hold' => $exception->getMessage()]);
        }

        return redirect()->route('retail.pos.index')
            ->with('notification', ['type' => 'success', 'message' => 'Keranjang hold ditandai untuk dilanjutkan.']);
    }

    public function cancelHold(CancelPosHoldRequest $request, PosHold $hold, PosService $service): RedirectResponse
    {
        $this->authorize('update', $hold);

        try {
            $service->cancelHold($hold, $request->user(), $request->validated()['reason']);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['hold' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Keranjang hold dibatalkan.']);
    }
}
