<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BusinessRelationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user?->can('suppliers.view') && ! $user?->can('customers.view')) {
            abort(403);
        }

        $supplierQ = trim((string) $request->query('supplier_q'));
        $supplierStatus = $request->query('supplier_status');

        $suppliers = Supplier::query()
            ->withCount('productsSupplied')
            ->when($supplierQ !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "%{$supplierQ}%")->orWhere('name', 'like', "%{$supplierQ}%")->orWhere('contact_name', 'like', "%{$supplierQ}%")))
            ->when($supplierStatus === 'active', fn ($query) => $query->where('is_active', true))
            ->when($supplierStatus === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(10, ['*'], 'supplier_page')
            ->withQueryString();

        $customerQ = trim((string) $request->query('customer_q'));
        $customerType = $request->query('customer_type');

        $customers = Customer::query()
            ->when(! $user->can('customers.view'), fn ($query) => $query->whereHas('users', fn ($inner) => $inner->where('users.id', $user->id)->where('customer_users.is_active', true)))
            ->when($customerQ !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "%{$customerQ}%")->orWhere('business_name', 'like', "%{$customerQ}%")->orWhere('owner_name', 'like', "%{$customerQ}%")))
            ->when($customerType, fn ($query, $value) => $query->where('type', $value))
            ->orderBy('business_name')
            ->paginate(10, ['*'], 'customer_page')
            ->withQueryString();

        $supplierKpis = [
            'total' => Supplier::query()->count(),
            'active' => Supplier::query()->where('is_active', true)->count(),
        ];

        $customerKpis = [
            'total' => Customer::query()->count(),
            'b2b' => Customer::query()->where('type', CustomerType::B2B)->count(),
            'retail' => Customer::query()->whereIn('type', [CustomerType::GENERAL, CustomerType::RETAIL_CREDIT])->count(),
            'total_receivable' => Customer::query()->sum('receivable_balance'),
        ];

        return view('admin.business-relations.index', [
            'suppliers' => $suppliers,
            'supplierQ' => $supplierQ,
            'supplierStatus' => $supplierStatus,
            'supplierKpis' => $supplierKpis,
            'customers' => $customers,
            'customerQ' => $customerQ,
            'customerType' => $customerType,
            'customerKpis' => $customerKpis,
        ]);
    }
}
