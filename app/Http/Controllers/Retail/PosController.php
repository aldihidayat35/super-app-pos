<?php

namespace App\Http\Controllers\Retail;

use App\Enums\CashShiftStatus;
use App\Enums\CreditLimitStatus;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\PaymentMethod;
use App\Enums\PosSaleStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\CancelPosHoldRequest;
use App\Http\Requests\Retail\QuotePosItemRequest;
use App\Http\Requests\Retail\StorePosHoldRequest;
use App\Http\Requests\Retail\StorePosSaleRequest;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\CreditLimit;
use App\Models\Customer;
use App\Models\EmergencyPurchase;
use App\Models\PosHold;
use App\Models\PosSale;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Organization\DocumentNumberService;
use App\Services\Retail\PosCatalogService;
use App\Services\Retail\PosService;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function index(Request $request, PosCatalogService $catalog): View
    {
        abort_unless($request->user()->can('pos.view'), 403);

        $activeShift = $this->activeShift($request);
        $emergencyPurchase = null;
        $resumeCart = session('pos_resume_cart');
        if ($request->filled('emergency_purchase_id')) {
            $emergencyPurchase = EmergencyPurchase::query()->with('items.product')->findOrFail($request->integer('emergency_purchase_id'));
            abort_unless($activeShift && (int) $activeShift->branch_id === (int) $emergencyPurchase->branch_id
                && $request->user()->canAccessWorkLocation((int) $emergencyPurchase->work_location_id), 403);
            abort_unless($emergencyPurchase->status === 'purchased', 422);
            $resumeCart = ['customer_id' => $emergencyPurchase->customer_id, 'items' => $emergencyPurchase->items
                ->map(fn ($item): array => $catalog->quote($activeShift->branch, $request->user(), [
                    'product_id' => $item->product_id, 'unit_id' => $item->unit_id,
                    'quantity' => $item->requested_quantity, 'customer_id' => $emergencyPurchase->customer_id,
                ]))->all()];
        }

        return view('retail.pos.index', [
            'activeShift' => $activeShift,
            'branch' => $activeShift?->branch,
            'customers' => Customer::query()->where('is_active', true)->orderBy('business_name')->limit(200)->get(),
            'categories' => ProductCategory::query()->where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get(),
            'brands' => ProductBrand::query()->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::options(),
            'holdCount' => $activeShift ? PosHold::query()->where('cash_shift_id', $activeShift->id)->where('cashier_user_id', $request->user()->id)->where('status', 'held')->count() : 0,
            'resumeCart' => $resumeCart,
            'emergencyPurchase' => $emergencyPurchase,
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

        $holds = PosHold::query()
            ->with(['branch', 'customer', 'cashier'])
            ->where('cashier_user_id', $request->user()->id)
            ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
            ->latest('id')
            ->paginate(15);
        $holds->getCollection()->each(function (PosHold $hold): void {
            $items = collect((array) data_get($hold->cart_snapshot, 'items', $hold->cart_snapshot));
            $hold->setAttribute('snapshot_item_count', $items->count());
            $hold->setAttribute('snapshot_total_qty', $items->reduce(
                fn (string $carry, mixed $item): string => Decimal::add($carry, (string) data_get($item, 'quantity', 0)),
                '0.0000',
            ));
        });

        return view('retail.pos.holds', ['holds' => $holds]);
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

    private function resolveActiveBranch(User $user): Branch
    {
        $shift = CashShift::query()
            ->with('branch')
            ->where('cashier_user_id', $user->id)
            ->where('status', CashShiftStatus::OPEN)
            ->latest('opened_at')
            ->first();

        if ($shift?->branch) {
            return $shift->branch;
        }

        $permittedIds = $user->permittedWorkLocationIds();
        if (! empty($permittedIds)) {
            $branch = Branch::query()->whereIn('work_location_id', $permittedIds)->where('is_active', true)->first();
            if ($branch) {
                return $branch;
            }
        }

        $firstBranch = Branch::query()->where('is_active', true)->first();
        if ($firstBranch) {
            return $firstBranch;
        }

        throw ServiceException::validation('Cabang aktif tidak ditemukan.');
    }

    public function storeQuickCustomer(Request $request, DocumentNumberService $numbers): JsonResponse
    {
        abort_unless($request->user()->can('pos.create'), 403);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:general,retail_credit,b2b'],
            'name' => ['required', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:50'],
            'minimum_order' => ['nullable', 'numeric', 'min:0'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $type = CustomerType::from($validated['type']);
        $businessName = trim($validated['name']);
        $creditLimit = (float) ($validated['credit_limit'] ?? 0);
        $paymentTerms = (int) ($validated['payment_term_days'] ?? 0);
        $minimumOrder = (float) ($validated['minimum_order'] ?? 0);
        $phone = $validated['whatsapp_number'] ?? null;

        $customer = DB::transaction(function () use ($numbers, $type, $businessName, $phone, $creditLimit, $paymentTerms, $minimumOrder): Customer {
            do {
                $code = $numbers->nextCustomerCode($type);
            } while (Customer::withTrashed()->where('code', $code)->exists());

            $cust = Customer::query()->create([
                'type' => $type,
                'code' => $code,
                'business_name' => $businessName,
                'owner_name' => $businessName,
                'whatsapp_number' => $phone,
                'minimum_order' => $minimumOrder,
                'payment_term_days' => $paymentTerms,
                'credit_limit' => $creditLimit,
                'receivable_balance' => 0,
                'verification_status' => CustomerStatus::ACTIVE,
                'account_status' => CustomerStatus::ACTIVE,
                'price_category' => 'retail',
                'is_active' => true,
            ]);

            CreditLimit::query()->create([
                'customer_id' => $cust->id,
                'credit_limit' => $creditLimit,
                'payment_term_days' => $paymentTerms,
                'current_balance' => 0,
                'status' => CreditLimitStatus::ACTIVE,
                'effective_from' => now()->toDateString(),
            ]);

            return $cust;
        });

        return response()->json([
            'success' => true,
            'message' => 'Pelanggan berhasil ditambahkan.',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->business_name,
                'business_name' => $customer->business_name,
                'phone' => $customer->whatsapp_number ?: '',
                'payment_terms' => (int) $customer->payment_term_days,
                'credit_limit' => (float) $customer->credit_limit,
                'receivable_balance' => (float) $customer->receivable_balance,
                'available_credit' => max(0, (float) ($customer->credit_limit - $customer->receivable_balance)),
                'minimum_order' => (float) $customer->minimum_order,
            ],
        ]);
    }

    public function lookupSale(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('pos.view'), 403);

        $branch = $this->resolveActiveBranch($request->user());
        $query = trim((string) $request->query('query', ''));

        $salesQuery = PosSale::query()
            ->with(['items.product', 'items.unit', 'items.allocations', 'customer', 'cashier'])
            ->where('branch_id', $branch->id)
            ->whereIn('status', [PosSaleStatus::COMPLETED, PosSaleStatus::RETURNED]);

        if ($query !== '') {
            $salesQuery->where(function ($q) use ($query) {
                $q->where('number', $query)
                    ->orWhere('number', 'like', "%{$query}%");
            });
        }

        $sales = $salesQuery->latest('id')->limit(15)->get();

        $data = $sales->map(function (PosSale $sale) {
            $canReturnAny = false;
            $items = $sale->items->map(function ($item) use (&$canReturnAny) {
                $remainingQty = max(0, (float) Decimal::sub((string) $item->base_quantity, (string) $item->returned_quantity, 4));
                if ($remainingQty > 0) {
                    $canReturnAny = true;
                }

                $normalAllocation = $item->allocations->firstWhere('source', 'normal');
                $emergencyAllocation = $item->allocations->firstWhere('source', 'emergency');
                $normalRemaining = $item->allocations->isEmpty()
                    ? $remainingQty
                    : ($normalAllocation ? max(0, (float) Decimal::sub((string) $normalAllocation->base_quantity, (string) $normalAllocation->returned_quantity, 4)) : 0);
                $emergencyRemaining = $emergencyAllocation ? max(0, (float) Decimal::sub((string) $emergencyAllocation->base_quantity, (string) $emergencyAllocation->returned_quantity, 4)) : 0;

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name_snapshot ?? $item->product?->name ?? 'Produk',
                    'sku' => $item->product?->sku ?? '-',
                    'barcode' => $item->product?->barcode ?? '',
                    'unit' => $item->unit?->code ?? 'PCS',
                    'quantity' => (float) $item->quantity,
                    'returned_quantity' => (float) $item->returned_quantity,
                    'remaining_quantity' => $remainingQty,
                    'selected_price' => (float) $item->selected_price,
                    'line_total' => (float) $item->line_total,
                    'normal_remaining' => $normalRemaining,
                    'emergency_remaining' => $emergencyRemaining,
                    'has_emergency' => $item->allocations->contains(fn ($a) => $a->source === 'emergency'),
                ];
            });

            return [
                'id' => $sale->id,
                'number' => $sale->number,
                'status' => $sale->status->value,
                'status_label' => $sale->status->label(),
                'date' => $sale->completed_at?->format('d/m/Y H:i') ?? $sale->created_at->format('d/m/Y H:i'),
                'cashier_name' => $sale->cashier?->name ?? '-',
                'customer_name' => $sale->customer?->business_name ?? $sale->customer?->name ?? 'Pelanggan Umum',
                'grand_total' => (float) $sale->grand_total_amount,
                'can_return' => $canReturnAny,
                'items' => $items,
            ];
        });

        return response()->json([
            'success' => true,
            'sales' => $data,
        ]);
    }

    public function processReturn(Request $request, PosService $posService): JsonResponse
    {
        abort_unless($request->user()->can('returns.create') || $request->user()->can('pos.create') || $request->user()->can('pos.void'), 403);

        $validated = $request->validate([
            'pos_sale_id' => ['required', 'integer', 'exists:pos_sales,id'],
            'resolution' => ['nullable', 'string', 'in:refund,exchange,credit'],
            'refund_method' => ['required', 'string', 'in:cash,bank_transfer,qris,manual'],
            'reason' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.pos_sale_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.condition' => ['nullable', 'string', 'in:good,damaged'],
        ]);

        $branch = $this->resolveActiveBranch($request->user());
        $sale = PosSale::query()->where('branch_id', $branch->id)->findOrFail($validated['pos_sale_id']);

        $activeShift = CashShift::query()
            ->where('branch_id', $branch->id)
            ->where('cashier_user_id', $request->user()->id)
            ->where('status', CashShiftStatus::OPEN)
            ->latest('id')
            ->first();

        try {
            $posReturn = $posService->returnSale($sale, $validated, $request->user(), $activeShift);
        } catch (ServiceException $e) {
            return $this->serviceError($e, 'return');
        }

        return response()->json([
            'success' => true,
            'message' => 'Retur penjualan '.$sale->number.' berhasil diproses.',
            'return' => [
                'id' => $posReturn->id,
                'number' => $posReturn->number,
                'refund_amount' => (float) $posReturn->refund_amount,
                'refund_method' => $posReturn->refund_method,
                'print_receipt_url' => route('retail.sales.print', $sale),
                'print_return_url' => route('retail.sales.return-receipt', $posReturn),
            ],
            'sale' => [
                'id' => $sale->id,
                'number' => $sale->number,
                'status' => $sale->status->value,
                'status_label' => $sale->status->label(),
            ],
        ]);
    }

    private function serviceError(ServiceException $exception, string $key): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'errors' => [$key => [$exception->getMessage()]],
        ], $exception->httpStatus);
    }
}
