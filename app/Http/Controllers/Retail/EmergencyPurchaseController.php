<?php

namespace App\Http\Controllers\Retail;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\EmergencyPurchase;
use App\Models\GoodsReceipt;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\Control\ApprovalWorkflowService;
use App\Services\Retail\EmergencyPurchaseService;
use App\Services\Retail\EmergencyStockService;
use App\Services\Retail\PosCatalogService;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmergencyPurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $branches = $this->branches($request);
        $branchId = $request->integer('branch_id');
        if ($branchId && ! $branches->contains('id', $branchId)) {
            abort(403);
        }
        $branchId = $branchId ?: $branches->first()?->id;
        $purpose = $request->query('purpose');
        if (! in_array($purpose, ['customer_request', 'proactive_restock'], true)) {
            $purpose = null;
        }

        return view('retail.emergency.index', [
            'branches' => $branches, 'branchId' => $branchId, 'purpose' => $purpose,
            'purchases' => EmergencyPurchase::query()->with(['requester', 'items.product'])
                ->whereIn('branch_id', $branches->pluck('id'))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->when($purpose, fn ($query) => $query->where('purpose', $purpose))
                ->latest('id')->paginate(20)->withQueryString(),
            'rule' => $branchId ? DB::table('emergency_purchase_rules')->where('branch_id', $branchId)->first() : null,
            'roles' => $request->user()->can('emergency_purchases.manage') ? Role::query()->orderBy('name')->pluck('name') : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $branches = $this->branches($request);
        $branchId = $request->integer('branch_id');
        if ($branchId && ! $branches->contains('id', $branchId)) {
            abort(403);
        }
        $branchId = $branchId ?: $branches->first()?->id;

        return view('retail.emergency.create', [
            'branches' => $branches, 'branchId' => $branchId,
            'products' => Product::query()->where('status', 'active')->with('baseUnit')->orderBy('name')->get(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('business_name')->limit(200)->get(),
            'productId' => $request->integer('product_id'),
            'purpose' => in_array($request->query('purpose'), ['customer_request', 'proactive_restock'], true)
                ? $request->query('purpose') : 'customer_request',
        ]);
    }

    public function store(Request $request, EmergencyPurchaseService $service): RedirectResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'in:customer_request,proactive_restock'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'prohibited_if:purpose,proactive_restock', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirmation_key' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'gt:0'],
        ]);
        try {
            $purchase = $service->confirm($data, $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['purchase' => $exception->getMessage()]);
        }

        $message = $purchase->purpose === 'proactive_restock'
            ? 'Restok darurat toko telah diajukan.'
            : 'Kekurangan stok telah dikonfirmasi dan dicatat.';

        return redirect()->route('retail.emergency.show', $purchase)->with('notification',
            ['type' => 'success', 'message' => $message]);
    }

    public function preview(Request $request, PosCatalogService $catalog): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['nullable', 'in:customer_request,proactive_restock'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);
        $branch = $this->branches($request)->firstWhere('id', (int) $data['branch_id']);
        abort_unless($branch instanceof Branch, 403);
        $quote = $catalog->quote($branch, $request->user(), $data);

        return response()->json(['stock_base' => $quote['stock_base'],
            'regular_stock_base' => $quote['regular_stock_base'],
            'emergency_stock_base' => $quote['emergency_stock_base'],
            'sellable_stock_base' => $quote['sellable_stock_base'],
            'unit_factor' => $quote['unit_factor'],
            'discounted_price' => $quote['pricing']['discounted_price']]);
    }

    public function show(Request $request, EmergencyPurchase $purchase, EmergencyStockService $emergencyStock): View
    {
        $this->access($request, $purchase);
        $purchase->load(['items.product', 'items.unit', 'histories', 'requester', 'branch', 'sale', 'shiftExpense', 'purchaseOrder', 'goodsReceipt']);

        $receiptMimeType = $purchase->receipt_path && Storage::disk('local')->exists($purchase->receipt_path)
            ? Storage::disk('local')->mimeType($purchase->receipt_path)
            : null;

        return view('retail.emergency.show', [
            'purchase' => $purchase,
            'receiptIsImage' => in_array($receiptMimeType, ['image/jpeg', 'image/png', 'image/webp'], true),
            'itemBalances' => $purchase->items->mapWithKeys(fn ($item): array => [$item->id => $emergencyStock->remaining($item)]),
            'linkedSales' => PosSale::query()->whereHas('items.allocations.emergencyItem', fn ($query) => $query
                ->where('emergency_purchase_id', $purchase->id))->latest('id')->get(),
            'approval' => ApprovalRequest::query()->where('subject_type', $purchase->getMorphClass())
                ->where('subject_id', $purchase->id)->where('approval_type', 'emergency_purchase')->latest('id')->first(),
            'reallocationTargets' => $request->user()->can('emergency_purchases.manage') && $purchase->status === 'unallocated'
                ? EmergencyPurchase::query()->where('branch_id', $purchase->branch_id)->where('status', 'approved')
                    ->where('id', '!=', $purchase->id)->latest('id')->limit(50)->get()
                : collect(),
            'openShifts' => $request->user()->can('emergency_purchases.manage') && $purchase->status === 'unallocated'
                ? CashShift::query()->where('branch_id', $purchase->branch_id)->where('status', 'open')->orderBy('number')->get()
                : collect(),
            'availableOrders' => $request->user()->can('emergency_purchases.manage') && $purchase->status === 'unallocated'
                ? PurchaseOrder::query()->where('warehouse_id', $purchase->branch?->primary_warehouse_id)
                    ->whereIn('status', ['approved', 'sent_to_supplier'])->latest('id')->limit(50)->get()
                : collect(),
            'postedReceipts' => $request->user()->can('emergency_purchases.manage') && $purchase->status === 'warehouse_pending'
                ? GoodsReceipt::query()->where('purchase_order_id', $purchase->purchase_order_id)
                    ->where('status', 'posted')->latest('id')->get()
                : collect(),
        ]);
    }

    public function receipt(Request $request, EmergencyPurchase $purchase): StreamedResponse
    {
        $this->access($request, $purchase);
        abort_unless($purchase->receipt_path && Storage::disk('local')->exists($purchase->receipt_path), 404);

        return Storage::disk('local')->download($purchase->receipt_path);
    }

    public function receiptPreview(Request $request, EmergencyPurchase $purchase): StreamedResponse
    {
        $this->access($request, $purchase);
        abort_unless($purchase->receipt_path && Storage::disk('local')->exists($purchase->receipt_path), 404);

        $mimeType = Storage::disk('local')->mimeType($purchase->receipt_path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 415);

        return Storage::disk('local')->response(
            $purchase->receipt_path,
            basename($purchase->receipt_path),
            [
                'Cache-Control' => 'private, max-age=300',
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline'
        );
    }

    public function reimbursementProof(Request $request, EmergencyPurchase $purchase): StreamedResponse
    {
        $this->access($request, $purchase);
        abort_unless($request->user()->can('emergency_purchases.manage'), 403);
        abort_unless($purchase->reimbursement_proof_path && Storage::disk('local')->exists($purchase->reimbursement_proof_path), 404);

        return Storage::disk('local')->download($purchase->reimbursement_proof_path);
    }

    public function purchased(Request $request, EmergencyPurchase $purchase, EmergencyPurchaseService $service): RedirectResponse
    {
        $this->access($request, $purchase);
        $data = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'fund_source' => ['required', 'in:store_cash,personal'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:emergency_purchase_items,id'],
            'items.*.purchased_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'gt:0'],
        ]);
        $data['receipt_path'] = $request->file('receipt')->store('retail/emergency-receipts', 'local');
        try {
            $service->purchased($purchase, $data, $request->user());
        } catch (ServiceException $exception) {
            Storage::disk('local')->delete($data['receipt_path']);
            throw ValidationException::withMessages(['purchase' => $exception->getMessage()]);
        }

        return $this->back($purchase, 'Barang dan nota pembelian berhasil dicatat.');
    }

    public function approve(Request $request, EmergencyPurchase $purchase, ApprovalWorkflowService $service): RedirectResponse
    {
        abort_unless($request->user()->can('emergency_purchases.approve'), 403);
        $this->access($request, $purchase);
        $approval = ApprovalRequest::query()->where('subject_type', $purchase->getMorphClass())
            ->where('subject_id', $purchase->id)->where('current_status', 'pending')->firstOrFail();
        try {
            $service->approve($approval, $request->user(), $request->input('notes'));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return $this->back($purchase, 'Permintaan disetujui.');
    }

    public function reject(Request $request, EmergencyPurchase $purchase, ApprovalWorkflowService $service): RedirectResponse
    {
        abort_unless($request->user()->can('emergency_purchases.approve'), 403);
        $this->access($request, $purchase);
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        $approval = ApprovalRequest::query()->where('subject_type', $purchase->getMorphClass())
            ->where('subject_id', $purchase->id)->where('current_status', 'pending')->firstOrFail();
        try {
            $service->reject($approval, $request->user(), $data['notes']);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return $this->back($purchase, 'Permintaan ditolak.');
    }

    public function cancel(Request $request, EmergencyPurchase $purchase, EmergencyPurchaseService $service): RedirectResponse
    {
        $this->access($request, $purchase);
        abort_unless((int) $purchase->requested_by === (int) $request->user()->id || $request->user()->can('emergency_purchases.manage'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $service->cancel($purchase, $request->user(), $data['reason']);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['purchase' => $exception->getMessage()]);
        }

        return $this->back($purchase, 'Pembatalan dicatat. Barang yang sudah dibeli tetap belum teralokasi.');
    }

    public function reimburse(Request $request, EmergencyPurchase $purchase, EmergencyPurchaseService $service): RedirectResponse
    {
        $this->access($request, $purchase);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:255'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $path = $request->file('proof')->store('retail/emergency-reimbursements', 'local');
        try {
            $service->reimburse($purchase, $request->user(), $data['reference'], $path);
        } catch (ServiceException $exception) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['reimbursement' => $exception->getMessage()]);
        }

        return $this->back($purchase, 'Pembayaran reimbursement dicatat.');
    }

    public function supplierReturn(Request $request, EmergencyPurchase $purchase, EmergencyPurchaseService $service): RedirectResponse
    {
        $this->access($request, $purchase);
        $data = $request->validate(['reference' => ['required', 'string', 'max:255'],
            'refund_amount' => ['required', 'numeric', 'min:0'],
            'refund_recipient' => ['required', 'in:store,purchaser'],
            'refund_method' => ['required', 'in:cash,bank_transfer'],
            'cash_shift_id' => ['nullable', 'integer', 'exists:cash_shifts,id']]);
        try {
            $service->supplierReturn($purchase, $request->user(), $data['reference'], (string) $data['refund_amount'],
                $data['refund_recipient'], $data['refund_method'], $data['cash_shift_id'] ?? null);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['supplier_return' => $exception->getMessage()]);
        }

        return $this->back($purchase, 'Retur ke pemasok dicatat.');
    }

    public function regularize(Request $request, EmergencyPurchase $purchase, EmergencyPurchaseService $service): RedirectResponse
    {
        $this->access($request, $purchase);
        try {
            $service->regularize($purchase, $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['regularization' => $exception->getMessage()]);
        }

        return $this->back($purchase, 'Saldo bebas pembelian darurat sudah masuk ke stok reguler toko dan dapat dijual lewat POS.');
    }

    public function reassign(Request $request, EmergencyPurchase $purchase, EmergencyPurchaseService $service): RedirectResponse
    {
        $this->access($request, $purchase);
        $data = $request->validate(['target_id' => ['required', 'integer', 'exists:emergency_purchases,id']]);
        try {
            $target = $service->reassign($purchase, EmergencyPurchase::query()->findOrFail($data['target_id']), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['reallocation' => $exception->getMessage()]);
        }

        return $this->back($target, 'Barang belum teralokasi siap dipakai pada permintaan POS tujuan.');
    }

    public function forwardWarehouse(Request $request, EmergencyPurchase $purchase, EmergencyPurchaseService $service): RedirectResponse
    {
        $this->access($request, $purchase);
        $data = $request->validate(['purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id']]);
        try {
            $service->forwardToWarehouse($purchase, PurchaseOrder::query()->findOrFail($data['purchase_order_id']), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['warehouse' => $exception->getMessage()]);
        }

        return $this->back($purchase, 'Barang ditautkan ke PO. Stok hanya berubah lewat posting penerimaan gudang.');
    }

    public function completeWarehouse(Request $request, EmergencyPurchase $purchase, EmergencyPurchaseService $service): RedirectResponse
    {
        $this->access($request, $purchase);
        $data = $request->validate(['goods_receipt_id' => ['required', 'integer', 'exists:goods_receipts,id']]);
        try {
            $service->completeWarehouse($purchase, GoodsReceipt::query()->findOrFail($data['goods_receipt_id']), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['warehouse' => $exception->getMessage()]);
        }

        return $this->back($purchase, 'Penerimaan gudang posted telah diverifikasi dan ditautkan.');
    }

    public function report(Request $request, EmergencyStockService $emergencyStock): View
    {
        $request->validate(['branch_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $branches = $this->branches($request);
        $branchId = $request->integer('branch_id');
        if ($branchId && ! $branches->contains('id', $branchId)) {
            abort(403);
        }
        $branchId = $branchId ?: $branches->first()?->id;
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        $purchases = EmergencyPurchase::query()->with(['branch', 'items.product'])
            ->where('branch_id', $branchId)->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->latest('created_at')->get();
        $stockouts = DB::table('retail_stockout_events')->where('branch_id', $branchId)
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->count();
        $allocations = DB::table('pos_sale_allocations')->join('pos_sale_items', 'pos_sale_items.id', '=', 'pos_sale_allocations.pos_sale_item_id')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->where('pos_sales.branch_id', $branchId)->where('pos_sale_allocations.source', 'emergency')
            ->whereIn('pos_sales.status', ['completed', 'returned'])
            ->whereDate('pos_sales.completed_at', '>=', $from)->whereDate('pos_sales.completed_at', '<=', $to)
            ->selectRaw('COALESCE(SUM((pos_sale_allocations.base_quantity - pos_sale_allocations.returned_quantity) * pos_sale_allocations.actual_cost_unit), 0) AS cost, COALESCE(SUM((pos_sale_allocations.base_quantity - pos_sale_allocations.returned_quantity) * (pos_sale_allocations.actual_cost_unit - pos_sale_allocations.normal_hpp_unit)), 0) AS lost_margin, COUNT(DISTINCT pos_sale_items.pos_sale_id) AS sale_count')->first();
        $purchasedCost = EmergencyPurchase::query()->where('branch_id', $branchId)
            ->whereDate('purchased_at', '>=', $from)->whereDate('purchased_at', '<=', $to)->sum('total_cost');
        $supplierRefund = EmergencyPurchase::query()->where('branch_id', $branchId)
            ->whereDate('supplier_returned_at', '>=', $from)->whereDate('supplier_returned_at', '<=', $to)->sum('supplier_refund_amount');
        $spent = Decimal::sub((string) $purchasedCost, (string) $supplierRefund, 2);
        $purposeCounts = $purchases->countBy('purpose');
        $poolQuantity = '0.0000';
        $poolCostValue = '0.00';
        $poolProducts = [];
        $selectedBranch = $branches->firstWhere('id', $branchId);
        if ($branchId) {
            $reportBranch = Branch::query()->findOrFail($branchId);
            $poolProducts = $emergencyStock->summaryByProduct($reportBranch);
            foreach ($poolProducts as $poolProduct) {
                $poolQuantity = Decimal::add($poolQuantity, $poolProduct['quantity'], 4);
                $poolCostValue = Decimal::add($poolCostValue, $poolProduct['cost_value'], 2);
            }
        }

        return view('retail.emergency.report', compact('branches', 'branchId', 'selectedBranch', 'from', 'to', 'purchases', 'stockouts', 'allocations', 'spent', 'purposeCounts', 'poolQuantity', 'poolCostValue', 'poolProducts'));
    }

    public function rule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'approval_above_amount' => ['nullable', 'numeric', 'min:0'],
            'required_role' => ['nullable', 'string', 'max:80', 'exists:roles,name'],
        ]);
        abort_unless($this->branches($request)->contains('id', $data['branch_id']), 403);
        DB::table('emergency_purchase_rules')->updateOrInsert(['branch_id' => $data['branch_id']], [
            'approval_above_amount' => $data['approval_above_amount'] ?? null,
            'required_role' => $data['required_role'] ?? null, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('retail.emergency.index', ['branch_id' => $data['branch_id']]);
    }

    public function deleteRule(Request $request): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id']]);
        abort_unless($this->branches($request)->contains('id', (int) $data['branch_id']), 403);
        DB::table('emergency_purchase_rules')->where('branch_id', $data['branch_id'])->delete();

        return redirect()->route('retail.emergency.index', ['branch_id' => $data['branch_id']]);
    }

    private function access(Request $request, EmergencyPurchase $purchase): void
    {
        abort_unless($request->user()->canAccessWorkLocation((int) $purchase->work_location_id), 403);
    }

    /** @return Collection<int, Branch> */
    private function branches(Request $request): Collection
    {
        return Branch::query()->where('is_active', true)
            ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
            ->orderBy('name')->get();
    }

    private function back(EmergencyPurchase $purchase, string $message): RedirectResponse
    {
        return redirect()->route('retail.emergency.show', $purchase)
            ->with('notification', ['type' => 'success', 'message' => $message]);
    }
}
