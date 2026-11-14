<?php

namespace App\Http\Controllers\Retail;

use App\Enums\PaymentMethod;
use App\Enums\PosSaleStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\StorePosReturnRequest;
use App\Http\Requests\Retail\VoidPosSaleRequest;
use App\Models\Branch;
use App\Models\PosReturn;
use App\Models\PosReturnItem;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Retail\PosService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosSaleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PosSale::class);

        $user = $request->user();

        // Default rentang tanggal ke hari ini jika tidak ditentukan di request
        $hasExplicitDate = $request->has('start_date') || $request->has('end_date');
        $startDate = $hasExplicitDate ? $request->input('start_date') : now()->toDateString();
        $endDate = $hasExplicitDate ? $request->input('end_date') : now()->toDateString();

        $query = PosSale::query()
            ->whereIn('work_location_id', $user->permittedWorkLocationIds());

        // Batasi hanya bisa lihat transaksi sendiri jika bukan kepala toko/supervisor (tidak punya akses void atau manage)
        if (! $user->can('pos.void') && ! $user->hasRole('super_admin')) {
            $query->where('cashier_user_id', $user->id);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                $startDate.' 00:00:00',
                $endDate.' 23:59:59',
            ]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('cashier_id')) {
            $query->where('cashier_user_id', $request->cashier_id);
        }

        if ($request->filled('payment_method')) {
            $method = $request->payment_method;
            $query->whereHas('payments', fn ($q) => $q->where('method', $method));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q2) => $q2->where('business_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%")
                        ->orWhere('pic_name', 'like', "%{$search}%")
                        ->orWhere('whatsapp_number', 'like', "%{$search}%"));
            });
        }

        // Ringkasan KPI dinamis sesuai filter yang sedang aktif
        $completedOrReturnedQuery = (clone $query)->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value]);
        $totalGrossRevenue = (string) $completedOrReturnedQuery->sum('grand_total_amount');
        $totalCompletedTransactions = $completedOrReturnedQuery->count();

        $saleIdsSubquery = (clone $query)->select('id');
        $totalGrossItemsSold = (string) PosSaleItem::whereIn('pos_sale_id', $saleIdsSubquery)
            ->whereHas('sale', fn ($q) => $q->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value]))
            ->sum('quantity');

        // Retur dan Pengembalian Dana
        $returnsSubquery = PosReturn::query()->whereIn('pos_sale_id', $saleIdsSubquery);
        $totalRefundAmount = (string) (clone $returnsSubquery)->sum('refund_amount');
        $totalReturnsCount = (clone $returnsSubquery)->count();
        $totalReturnedItemsQty = (string) PosReturnItem::whereIn('pos_return_id', (clone $returnsSubquery)->select('id'))->sum('quantity');

        // Omzet Bersih & Qty Bersih Riil
        $netRevenue = (string) max(0, (float) $totalGrossRevenue - (float) $totalRefundAmount);
        $netItemsSold = (string) max(0, (float) $totalGrossItemsSold - (float) $totalReturnedItemsQty);

        // Void (Dibatalkan)
        $voidQuery = (clone $query)->where('status', PosSaleStatus::VOID_APPROVED->value);
        $totalVoidCount = $voidQuery->count();
        $totalVoidAmount = (string) $voidQuery->sum('grand_total_amount');

        $kpi = [
            'gross_revenue' => $totalGrossRevenue,
            'revenue' => $totalGrossRevenue, // fallback kompatibilitas
            'transactions' => $totalCompletedTransactions,
            'gross_items_sold' => $totalGrossItemsSold,
            'items_sold' => $netItemsSold,
            'net_items_sold' => $netItemsSold,
            'refund_amount' => $totalRefundAmount,
            'returns_count' => $totalReturnsCount,
            'returned_items_qty' => $totalReturnedItemsQty,
            'net_revenue' => $netRevenue,
            'void_count' => $totalVoidCount,
            'void_amount' => $totalVoidAmount,
            'void_retur_count' => $totalVoidCount + $totalReturnsCount,
            'void_retur_amount' => (string) ((float) $totalVoidAmount + (float) $totalRefundAmount),
        ];

        $sales = (clone $query)
            ->with(['cashier', 'customer', 'items.product', 'items.unit', 'payments', 'branch', 'returns.items.product', 'returns.cashier'])
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $cashiers = User::whereHas('posSales', function ($q) use ($user): void {
            $q->whereIn('work_location_id', $user->permittedWorkLocationIds());
        })->get();

        $paymentMethods = PaymentMethod::options();

        return view('retail.sales.index', [
            'sales' => $sales,
            'cashiers' => $cashiers,
            'paymentMethods' => $paymentMethods,
            'kpi' => $kpi,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function show(PosSale $sale): View
    {
        $this->authorize('view', $sale);

        return view('retail.sales.show', [
            'sale' => $sale->load([
                'items.product',
                'items.unit',
                'items.allocations.emergencyItem.purchase',
                'payments',
                'branch',
                'cashier',
                'customer',
                'stockMutations.product',
                'returns.items.product',
                'returns.items.saleItem',
                'returns.cashier',
                'returns.cashShift',
            ]),
        ]);
    }

    public function print(Request $request, PosSale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->increment('receipt_print_count');
        $sale->forceFill(['last_printed_at' => now()])->save();

        $settings = SystemSetting::getPrinterSettings();
        $companyName = SystemSetting::getCompanyName();

        $sale->load(['items.product', 'items.unit', 'payments', 'branch', 'cashier', 'customer', 'returns.items.product']);

        if ($request->query('format') === 'dotmatrix') {
            return view('retail.sales.invoice-dotmatrix', [
                'sale' => $sale,
                'settings' => $settings,
                'companyName' => $companyName,
            ]);
        }

        return view('retail.sales.receipt', [
            'sale' => $sale,
            'settings' => $settings,
            'companyName' => $companyName,
        ]);
    }

    public function printReturn(PosReturn $posReturn): View
    {
        $this->authorize('view', $posReturn->sale);

        $posReturn->load(['sale.branch', 'sale.cashier', 'sale.customer', 'items.product', 'items.saleItem.unit', 'cashier']);
        $settings = SystemSetting::getPrinterSettings();
        $companyName = SystemSetting::getCompanyName();

        return view('retail.sales.return-receipt', [
            'return' => $posReturn,
            'settings' => $settings,
            'companyName' => $companyName,
        ]);
    }

    public function printHistory(Request $request): View
    {
        $this->authorize('viewAny', PosSale::class);

        $user = $request->user();

        $startDate = $request->input('start_date', now()->toDateString());
        $startTime = $request->input('start_time', '00:00');
        $endDate = $request->input('end_date', now()->toDateString());
        $endTime = $request->input('end_time', '23:59');

        $startTimeFormatted = strlen($startTime) === 5 ? "{$startTime}:00" : (strlen($startTime) === 8 ? $startTime : '00:00:00');
        $endTimeFormatted = strlen($endTime) === 5 ? "{$endTime}:59" : (strlen($endTime) === 8 ? $endTime : '23:59:59');

        $startDateTime = "{$startDate} {$startTimeFormatted}";
        $endDateTime = "{$endDate} {$endTimeFormatted}";

        $query = PosSale::query()
            ->whereIn('work_location_id', $user->permittedWorkLocationIds());

        if (! $user->can('pos.void') && ! $user->hasRole('super_admin')) {
            $query->where('cashier_user_id', $user->id);
        }

        $query->whereBetween('created_at', [$startDateTime, $endDateTime]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('cashier_id')) {
            $query->where('cashier_user_id', $request->cashier_id);
        }

        if ($request->filled('payment_method')) {
            $method = $request->payment_method;
            $query->whereHas('payments', fn ($q) => $q->where('method', $method));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q2) => $q2->where('business_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%")
                        ->orWhere('pic_name', 'like', "%{$search}%")
                        ->orWhere('whatsapp_number', 'like', "%{$search}%"));
            });
        }

        $sales = $query
            ->with(['cashier', 'customer', 'branch', 'payments', 'items.product', 'items.unit', 'returns.items.product'])
            ->orderBy('created_at', 'asc')
            ->get();

        $settings = SystemSetting::getPrinterSettings();
        $companyName = SystemSetting::getCompanyName();

        $totalCount = $sales->count();
        $activeSales = $sales->whereIn('status', [PosSaleStatus::COMPLETED, PosSaleStatus::RETURNED]);
        $totalCompleted = $activeSales->count();
        $totalVoid = $sales->where('status', PosSaleStatus::VOID_APPROVED)->count();
        $totalReturned = $sales->where('status', PosSaleStatus::RETURNED)->count();
        $totalVoidOrReturned = $totalVoid + $totalReturned;

        $totalQuantitySold = 0;
        $totalReturnedQuantity = 0;
        foreach ($activeSales as $s) {
            $totalQuantitySold += (float) $s->items->sum('quantity');
            $totalReturnedQuantity += (float) $s->items->sum('returned_quantity');
        }

        $totalGrossAmount = $activeSales->sum(fn ($s) => (float) $s->subtotal_amount);
        $totalDiscountAmount = $activeSales->sum(fn ($s) => (float) $s->discount_amount);
        $totalGrandAmount = $activeSales->sum(fn ($s) => (float) $s->grand_total_amount);
        $totalRefundAmount = $sales->sum(fn ($s) => (float) $s->returns->sum('refund_amount'));
        $totalNetAmount = max(0, $totalGrandAmount - $totalRefundAmount);
        $totalNetQuantity = max(0, $totalQuantitySold - $totalReturnedQuantity);

        $paymentSummary = [];
        foreach ($activeSales as $s) {
            foreach ($s->payments as $p) {
                $label = $p->method?->label() ?? 'Tunai';
                if (! isset($paymentSummary[$label])) {
                    $paymentSummary[$label] = 0;
                }
                $paymentSummary[$label] += (float) $p->amount;
            }
        }

        $branch = $sales->first()?->branch ?? Branch::first();

        return view('retail.sales.print-history-dotmatrix', [
            'sales' => $sales,
            'startDate' => $startDate,
            'startTime' => $startTime,
            'endDate' => $endDate,
            'endTime' => $endTime,
            'startDateTime' => $startDateTime,
            'endDateTime' => $endDateTime,
            'totalCount' => $totalCount,
            'totalCompleted' => $totalCompleted,
            'totalVoidOrReturned' => $totalVoidOrReturned,
            'totalQuantitySold' => $totalQuantitySold,
            'totalReturnedQuantity' => $totalReturnedQuantity,
            'totalNetQuantity' => $totalNetQuantity,
            'totalGrossAmount' => $totalGrossAmount,
            'totalDiscountAmount' => $totalDiscountAmount,
            'totalGrandAmount' => $totalGrandAmount,
            'totalRefundAmount' => $totalRefundAmount,
            'totalNetAmount' => $totalNetAmount,
            'paymentSummary' => $paymentSummary,
            'settings' => $settings,
            'companyName' => $companyName,
            'currentUser' => $user,
            'branch' => $branch,
            'filters' => [
                'status' => $request->input('status'),
                'cashier_id' => $request->input('cashier_id'),
                'payment_method' => $request->input('payment_method'),
            ],
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

        return view('retail.sales.return', ['sale' => $sale->load(['items.product', 'items.allocations', 'branch', 'cashier'])]);
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
