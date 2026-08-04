<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\B2bOrderStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\RejectB2bOrderRequest;
use App\Http\Requests\Warehouse\ReviewB2bOrderRequest;
use App\Http\Requests\Warehouse\ShipB2bOrderRequest;
use App\Models\B2bOrder;
use App\Models\CreditLimit;
use App\Models\Customer;
use App\Services\B2B\B2bOrderWorkflowService;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class B2bOrderController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('b2b_orders.view'), 403);
        $filters = [
            'status' => $request->query('status'),
            'q' => trim((string) $request->query('q')),
            'customer_id' => $request->integer('customer_id') ?: null,
        ];

        return view('warehouse.b2b-orders.index', [
            'filters' => $filters,
            'statuses' => B2bOrderStatus::options(),
            'orders' => B2bOrder::query()
                ->with(['customer', 'requester'])
                ->withCount('items')
                ->when($filters['customer_id'], fn ($query, $customerId) => $query->where('customer_id', $customerId))
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['q'], function ($query, string $term): void {
                    $like = '%'.$term.'%';
                    $query->where(fn ($search) => $search->where('number', 'like', $like)->orWhereHas('customer', fn ($customer) => $customer->where('business_name', 'like', $like)));
                })
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function review(B2bOrder $order): View
    {
        $order->load([
            'customer.creditLimit',
            'requester',
            'address',
            'items.product',
            'reservations.product',
            'reservations.workLocation',
            'reservations.warehouseLocation',
            'statusHistories.actor',
            'messages.user',
            'invoices',
            'shipments',
        ]);

        return view('warehouse.b2b-orders.review', [
            'order' => $order,
            'creditUsage' => $this->creditUsage($order),
        ]);
    }

    public function reserve(ReviewB2bOrderRequest $request, B2bOrder $order, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        try {
            $workflow->reserve($order, $request->user(), $request->validated());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['review' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.b2b-orders.review', $order)
            ->with('notification', ['type' => 'success', 'message' => 'Order berhasil divalidasi dan stok sudah di-reserve.']);
    }

    public function reject(RejectB2bOrderRequest $request, B2bOrder $order, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        try {
            $workflow->reject($order, $request->user(), $request->validated('reason'));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['reject' => $exception->getMessage()]);
        }

        return redirect()->route('warehouse.b2b-orders.review', $order)
            ->with('notification', ['type' => 'success', 'message' => 'Order B2B ditolak dan reservation aktif dilepas.']);
    }

    public function pack(Request $request, B2bOrder $order, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('b2b_orders.approve'), 403);

        try {
            $workflow->pack($order, $request->user(), $request->input('internal_note'));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['pack' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Order masuk proses packing.']);
    }

    public function ship(ShipB2bOrderRequest $request, B2bOrder $order, B2bOrderWorkflowService $workflow): RedirectResponse
    {
        try {
            if (filled($request->validated('courier_name'))) {
                $order->forceFill(['courier_name' => $request->validated('courier_name')])->save();
            }
            $workflow->ship($order, $request->user(), $request->validated('internal_note'));
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['ship' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Order dikirim dan reserved stock sudah dikonversi menjadi issue stock.']);
    }

    /** @return array{limit: string, used: string, remaining: string, excess: string, percentage: string, bar_percentage: string, color: string, label: string} */
    private function creditUsage(B2bOrder $order): array
    {
        $limit = (string) ($order->credit_limit_snapshot ?? '0');
        $used = (string) ($order->receivable_balance_snapshot ?? '0');
        $customer = $order->getRelation('customer');

        if ($customer instanceof Customer) {
            $limit = (string) $customer->credit_limit;
            $used = (string) $customer->receivable_balance;
            $creditLimit = $customer->getRelation('creditLimit');

            if ($creditLimit instanceof CreditLimit) {
                $limit = (string) $creditLimit->credit_limit;
                $used = (string) $creditLimit->current_balance;
            }
        }

        if (Decimal::compare($limit, '0', 2) < 0) {
            $limit = '0.00';
        }

        if (Decimal::compare($used, '0', 2) < 0) {
            $used = '0.00';
        }

        $remaining = Decimal::sub($limit, $used, 2);
        $excess = '0.00';

        if (Decimal::compare($remaining, '0', 2) < 0) {
            $excess = Decimal::sub($used, $limit, 2);
            $remaining = '0.00';
        }

        $percentage = '0.00';
        if (Decimal::compare($limit, '0', 2) > 0) {
            $ratio = Decimal::div($used, $limit, 2, 2, 4);
            $percentage = Decimal::mul($ratio, '100', 4, 0, 2);
        }

        $barPercentage = Decimal::compare($percentage, '100', 2) > 0 ? '100.00' : $percentage;

        [$color, $label] = match (true) {
            Decimal::compare($limit, '0', 2) === 0 => ['secondary', 'Limit belum diatur'],
            Decimal::compare($percentage, '100', 2) > 0 => ['danger', 'Melebihi limit'],
            Decimal::compare($percentage, '80', 2) > 0 => ['danger', 'Kritis'],
            Decimal::compare($percentage, '60', 2) > 0 => ['warning', 'Perlu perhatian'],
            default => ['success', 'Aman'],
        };

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'excess' => $excess,
            'percentage' => $percentage,
            'bar_percentage' => $barPercentage,
            'color' => $color,
            'label' => $label,
        ];
    }
}
