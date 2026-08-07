<?php

namespace App\Http\Controllers\Retail;

use App\Enums\PaymentMethod;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\CancelPosHoldRequest;
use App\Http\Requests\Retail\QuotePosItemRequest;
use App\Http\Requests\Retail\StorePosHoldRequest;
use App\Http\Requests\Retail\StorePosSaleRequest;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\PosHold;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Services\Retail\PosCatalogService;
use App\Services\Retail\PosService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('pos.view'), 403);

        $activeShift = $this->activeShift($request);

        return view('retail.pos.index', [
            'activeShift' => $activeShift,
            'branch' => $activeShift?->branch,
            'customers' => Customer::query()->where('is_active', true)->orderBy('business_name')->limit(200)->get(),
            'categories' => ProductCategory::query()->where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get(),
            'brands' => ProductBrand::query()->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::options(),
            'holdCount' => $activeShift ? PosHold::query()->where('cash_shift_id', $activeShift->id)->where('cashier_user_id', $request->user()->id)->where('status', 'held')->count() : 0,
            'resumeCart' => session('pos_resume_cart'),
        ]);
    }

    public function products(Request $request, PosCatalogService $catalog): JsonResponse
    {
        abort_unless($request->user()->can('pos.view'), 403);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'in_stock' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:12', 'max:48'],
        ]);
        try {
            $shift = $this->requireActiveShift($request);

            return response()->json($catalog->search($shift->branch, $request->user(), $data));
        } catch (ServiceException $exception) {
            return $this->serviceError($exception, 'products');
        }
    }

    public function quote(QuotePosItemRequest $request, PosCatalogService $catalog): JsonResponse
    {
        try {
            $shift = $this->requireActiveShift($request);

            return response()->json(['item' => $catalog->quote($shift->branch, $request->user(), $request->validated())]);
        } catch (ServiceException $exception) {
            return $this->serviceError($exception, 'quote');
        }
    }

    public function store(StorePosSaleRequest $request, PosService $service): RedirectResponse|JsonResponse
    {
        try {
            $shift = $this->requireActiveShift($request);
            $payload = $request->validated();
            $payload['branch_id'] = $shift->branch_id;
            $sale = $service->checkout($payload, $request->user());
        } catch (ServiceException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage(), 'errors' => ['checkout' => [$exception->getMessage()]]], $exception->httpStatus);
            }
            throw ValidationException::withMessages(['checkout' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Transaksi berhasil disimpan.',
                'sale' => [
                    'id' => $sale->id,
                    'number' => $sale->number,
                    'grand_total' => $sale->grand_total_amount,
                    'paid' => $sale->paid_amount,
                    'change' => $sale->change_amount,
                    'show_url' => route('retail.sales.show', $sale),
                    'print_url' => route('retail.sales.print', $sale),
                ],
            ], Response::HTTP_CREATED);
        }

        return redirect()->route('retail.sales.show', $sale)
            ->with('notification', ['type' => 'success', 'message' => 'Transaksi POS berhasil disimpan dan stok sudah berkurang.']);
    }

    public function checkout(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('pos.create'), 403);

        return redirect()->route('retail.pos.index');
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

    public function holdData(Request $request): JsonResponse
    {
        try {
            $shift = $this->requireActiveShift($request);
        } catch (ServiceException $exception) {
            return $this->serviceError($exception, 'hold');
        }

        $holds = PosHold::query()
            ->with(['customer', 'cashier'])
            ->where('cash_shift_id', $shift->id)
            ->where('cashier_user_id', $request->user()->id)
            ->where('status', 'held')
            ->latest('id')->limit(30)->get()
            ->map(fn (PosHold $hold): array => [
                'id' => $hold->id,
                'number' => $hold->number,
                'customer' => $hold->customer?->business_name ?: 'Pelanggan Umum',
                'item_count' => count((array) data_get($hold->cart_snapshot, 'items', $hold->cart_snapshot)),
                'estimated_total' => $hold->estimated_total,
                'cashier' => $hold->cashier?->name,
                'time' => $hold->created_at?->format('H:i'),
                'resume_url' => route('retail.pos.holds.resume', $hold),
            ]);

        return response()->json(['results' => $holds, 'count' => $holds->count()]);
    }

    public function storeHold(StorePosHoldRequest $request, PosService $service): RedirectResponse|JsonResponse
    {
        try {
            $shift = $this->requireActiveShift($request);
            $payload = $request->validated();
            $payload['branch_id'] = $shift->branch_id;
            $hold = $service->hold($payload, $request->user());
        } catch (ServiceException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage(), 'errors' => ['hold' => [$exception->getMessage()]]], $exception->httpStatus);
            }
            throw ValidationException::withMessages(['hold' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Keranjang berhasil ditahan.', 'hold' => ['id' => $hold->id, 'number' => $hold->number]], Response::HTTP_CREATED);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Keranjang berhasil ditahan.']);
    }

    public function resumeHold(Request $request, PosHold $hold, PosService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $hold);

        try {
            $hold = $service->resumeHold($hold, $request->user());
        } catch (ServiceException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage(), 'errors' => ['hold' => [$exception->getMessage()]]], $exception->httpStatus);
            }
            throw ValidationException::withMessages(['hold' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Transaksi hold siap dilanjutkan.', 'cart' => $hold->cart_snapshot]);
        }

        return redirect()->route('retail.pos.index')
            ->with('pos_resume_cart', $hold->cart_snapshot)
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

    private function activeShift(Request $request): ?CashShift
    {
        return CashShift::query()
            ->with('branch.workLocation')
            ->where('cashier_user_id', $request->user()->id)
            ->where('status', 'open')
            ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
            ->latest('opened_at')
            ->first();
    }

    private function requireActiveShift(Request $request): CashShift
    {
        $shift = $this->activeShift($request);
        if (! $shift instanceof CashShift || ! $shift->branch?->is_active) {
            throw ServiceException::validation('Kasir belum memiliki shift aktif. Buka shift sebelum memulai transaksi POS.');
        }

        return $shift;
    }

    private function serviceError(ServiceException $exception, string $key): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'errors' => [$key => [$exception->getMessage()]],
        ], $exception->httpStatus);
    }
}
