<?php

namespace App\Http\Controllers\Retail;

use App\Enums\PaymentMethod;
use App\Enums\PosSaleStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\StorePosReturnRequest;
use App\Http\Requests\Retail\VoidPosSaleRequest;
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
        $completedQuery = (clone $query)->where('status', PosSaleStatus::COMPLETED->value);
        $totalRevenue = (string) $completedQuery->sum('grand_total_amount');
        $totalCompletedTransactions = $completedQuery->count();

        $saleIdsSubquery = (clone $query)->select('id');
        $totalItemsSold = (string) PosSaleItem::whereIn('pos_sale_id', $saleIdsSubquery)
            ->whereHas('sale', fn ($q) => $q->where('status', PosSaleStatus::COMPLETED->value))
            ->sum('quantity');

        $voidReturQuery = (clone $query)->whereIn('status', [PosSaleStatus::VOID_APPROVED->value, PosSaleStatus::RETURNED->value]);
        $totalVoidReturCount = $voidReturQuery->count();
        $totalVoidReturAmount = (string) $voidReturQuery->sum('grand_total_amount');

        $kpi = [
            'revenue' => $totalRevenue,
            'transactions' => $totalCompletedTransactions,
            'items_sold' => $totalItemsSold,
            'void_retur_count' => $totalVoidReturCount,
            'void_retur_amount' => $totalVoidReturAmount,
        ];

        $sales = (clone $query)
            ->with(['cashier', 'customer', 'items', 'payments', 'branch'])
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
            'sale' => $sale->load(['items.product', 'items.unit', 'items.allocations.emergencyItem.purchase', 'payments', 'branch', 'cashier', 'customer', 'stockMutations']),
        ]);
    }

    public function print(Request $request, PosSale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->increment('receipt_print_count');
        $sale->forceFill(['last_printed_at' => now()])->save();

        $settings = SystemSetting::getPrinterSettings();
        $companyName = SystemSetting::getCompanyName();

        $sale->load(['items.product', 'items.unit', 'payments', 'branch', 'cashier', 'customer']);

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
