<?php

namespace App\Http\Controllers\Admin;

use App\Enums\B2bOrderStatus;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Shipment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);
        $filters = ['q' => trim((string) $request->query('q')), 'type' => $request->query('type'), 'price_category' => $request->query('price_category'), 'status' => $request->query('status'), 'over_limit' => $request->query('over_limit')];
        $user = $request->user();
        $customers = Customer::query()
            ->withCount('users')
            ->when(! $user?->can('customers.view'), fn ($query) => $query->whereHas('users', fn ($inner) => $inner->where('users.id', $user?->id)->where('customer_users.is_active', true)))
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "%{$filters['q']}%")->orWhere('business_name', 'like', "%{$filters['q']}%")->orWhere('pic_name', 'like', "%{$filters['q']}%")))
            ->when($filters['type'], fn ($query, $value) => $query->where('type', $value))
            ->when($filters['price_category'], fn ($query, $value) => $query->where('price_category', $value))
            ->when($filters['status'], fn ($query, $value) => $query->where('account_status', $value))
            ->when($filters['over_limit'] === 'yes', fn ($query) => $query->whereColumn('receivable_balance', '>', 'credit_limit'))
            ->orderBy('business_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.customers.index', ['customers' => $customers, 'filters' => $filters, 'types' => CustomerType::options(), 'statuses' => CustomerStatus::options()]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('admin.customers.create', $this->formData(new Customer(['type' => CustomerType::GENERAL, 'verification_status' => CustomerStatus::PENDING_VERIFICATION, 'account_status' => CustomerStatus::PENDING_VERIFICATION, 'price_category' => 'retail', 'is_active' => true])));
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = DB::transaction(function () use ($request): Customer {
            $customer = Customer::query()->create([...$request->validated(), 'is_active' => $request->boolean('is_active')]);
            $customer->creditLimit()->create(['credit_limit' => $customer->credit_limit, 'payment_term_days' => $customer->payment_term_days, 'current_balance' => 0, 'effective_from' => now()->toDateString()]);
            activity()->causedBy($request->user())->performedOn($customer)->log('customer.created');

            return $customer;
        });

        return redirect()->route('admin.customers.show', $customer)->with('notification', ['type' => 'success', 'message' => 'Pelanggan berhasil dibuat.']);
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);

        $user = $request->user();
        $access = [
            'orders' => (bool) $user?->can('b2b_orders.view'),
            'invoices' => (bool) ($user?->can('invoices.view') || $user?->can('receivables.view')),
            'payments' => (bool) ($user?->can('payments.verify') || $user?->can('receivables.view')),
            'receivables' => (bool) $user?->can('receivables.view'),
            'shipments' => (bool) ($user?->can('shipments.view') || $user?->can('b2b_orders.view')),
            'pricing' => (bool) ($user?->can('prices.view') || $user?->can('customers.manage_settings')),
        ];

        $relations = ['addresses', 'users', 'documents', 'creditLimit'];

        if ($access['pricing']) {
            $relations['priceOverrides'] = fn ($query) => $query->with('product')->latest('id')->limit(10);
        }

        if ($access['orders']) {
            $relations['b2bOrders'] = fn ($query) => $query->withCount('items')->latest('id')->limit(10);
        }

        if ($access['invoices']) {
            $relations['invoices'] = fn ($query) => $query->with('order')->latest('id')->limit(10);
        }

        if ($access['payments']) {
            $relations['payments'] = fn ($query) => $query->latest('id')->limit(10);
        }

        if ($access['shipments']) {
            $relations['shipments'] = fn ($query) => $query->with('order')->latest('id')->limit(10);
        }

        if ($access['receivables']) {
            $workLocationIds = $user?->permittedWorkLocationIds() ?? [];
            $unrestrictedLocationScope = (bool) $user?->hasUnrestrictedLocationScope();
            $relations['receivables'] = fn ($query) => $query
                ->with('workLocation')
                ->when(! $unrestrictedLocationScope, fn ($scope) => $scope->where(
                    fn ($location) => $location->whereNull('work_location_id')->orWhereIn('work_location_id', $workLocationIds)
                ))
                ->latest('id')
                ->limit(10);
        }

        $customer->load($relations);

        $availableCredit = bcsub((string) $customer->credit_limit, (string) $customer->receivable_balance, 2);
        if (bccomp($availableCredit, '0', 2) < 0) {
            $availableCredit = '0.00';
        }

        $metrics = [
            'orders' => $access['orders'] ? $customer->b2bOrders()->count() : null,
            'open_invoices' => $access['invoices'] ? $customer->invoices()->whereNotIn('status', ['paid', 'cancelled'])->count() : null,
            'receivable_balance' => $access['receivables'] ? (string) $customer->receivable_balance : null,
            'available_credit' => $access['receivables'] ? $availableCredit : null,
        ];

        $shippableOrder = null;
        if ($access['orders'] && $user?->can('create', Shipment::class)) {
            $shippableOrder = $customer->b2bOrders()
                ->whereIn('status', [B2bOrderStatus::APPROVED_CREDIT->value, B2bOrderStatus::PACKING->value])
                ->latest('id')
                ->first();
        }

        return view('admin.customers.show', compact('customer', 'access', 'metrics', 'shippableOrder'));
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('admin.customers.edit', $this->formData($customer));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        DB::transaction(function () use ($request, $customer): void {
            $before = $customer->only(['price_category', 'credit_limit', 'payment_term_days', 'verification_status', 'account_status']);
            $customer->fill([...$request->validated(), 'is_active' => $request->boolean('is_active')])->save();
            $customer->creditLimit()->updateOrCreate(['customer_id' => $customer->id], ['credit_limit' => $customer->credit_limit, 'payment_term_days' => $customer->payment_term_days, 'current_balance' => $customer->receivable_balance, 'effective_from' => now()->toDateString()]);
            activity()->causedBy($request->user())->performedOn($customer)->withProperties(['before' => $before, 'after' => $customer->only(array_keys($before))])->log('customer.updated_sensitive_config');
        });

        return redirect()->route('admin.customers.show', $customer)->with('notification', ['type' => 'success', 'message' => 'Pelanggan berhasil diperbarui.']);
    }

    public function deactivate(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        $customer->forceFill(['account_status' => CustomerStatus::INACTIVE, 'is_active' => false, 'status_reason' => $reason])->save();
        activity()->causedBy($request->user())->performedOn($customer)->withProperties(['reason' => $reason])->log('customer.deactivated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Pelanggan berhasil dinonaktifkan.']);
    }

    public function export(): StreamedResponse
    {
        $this->authorize('export', Customer::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['type', 'code', 'business_name', 'pic_name', 'whatsapp_number', 'email', 'price_category', 'credit_limit', 'receivable_balance', 'account_status']);
            Customer::query()->orderBy('code')->each(fn (Customer $customer) => fputcsv($handle, [$customer->getRawOriginal('type'), $customer->code, $customer->business_name, $customer->pic_name, $customer->whatsapp_number, $customer->email, $customer->price_category, $customer->credit_limit, $customer->receivable_balance, $customer->getRawOriginal('account_status')]));
            fclose($handle);
        }, 'pelanggan-'.now()->format('Ymd-His').'.csv');
    }

    /** @return array<string, mixed> */
    private function formData(Customer $customer): array
    {
        return ['customer' => $customer, 'types' => CustomerType::options(), 'statuses' => CustomerStatus::options(), 'priceCategories' => ['retail' => 'Retail', 'grosir' => 'Grosir', 'reseller' => 'Reseller', 'project' => 'Proyek', 'special' => 'Khusus']];
    }
}
