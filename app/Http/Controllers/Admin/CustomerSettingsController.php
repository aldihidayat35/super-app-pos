<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCustomerSettingsRequest;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CustomerSettingsController extends Controller
{
    public function edit(Customer $customer): View
    {
        $this->authorize('manageSettings', $customer);

        return view('admin.customers.settings', [
            'customer' => $customer->load(['documents', 'priceOverrides.product', 'creditLimit']),
            'products' => Product::query()->orderBy('name')->limit(100)->get(),
            'statuses' => CustomerStatus::options(),
            'priceCategories' => ['retail' => 'Retail', 'grosir' => 'Grosir', 'reseller' => 'Reseller', 'project' => 'Proyek', 'special' => 'Khusus'],
        ]);
    }

    public function update(UpdateCustomerSettingsRequest $request, Customer $customer): RedirectResponse
    {
        DB::transaction(function () use ($request, $customer): void {
            $before = $customer->only(['verification_status', 'account_status', 'price_category', 'minimum_order', 'payment_term_days', 'credit_limit']);
            $customer->fill($request->safe()->only(['verification_status', 'account_status', 'price_category', 'minimum_order', 'payment_term_days', 'credit_limit', 'status_reason']))->save();
            $customer->creditLimit()->updateOrCreate(['customer_id' => $customer->id], ['credit_limit' => $customer->credit_limit, 'payment_term_days' => $customer->payment_term_days, 'current_balance' => $customer->receivable_balance, 'effective_from' => now()->toDateString(), 'notes' => $customer->status_reason]);

            if ($request->hasFile('document')) {
                $customer->documents()->create([
                    'type' => $request->input('document_type', 'business_document'),
                    'name' => $request->input('document_name') ?: $request->file('document')?->getClientOriginalName(),
                    'path' => $request->file('document')?->store('customer-documents', 'public'),
                ]);
            }

            foreach ($request->validated('price_overrides', []) as $override) {
                if (blank($override['product_id'] ?? null) || blank($override['price'] ?? null) || blank($override['starts_at'] ?? null)) {
                    continue;
                }
                $customer->priceOverrides()->create([
                    'product_id' => $override['product_id'],
                    'price' => $override['price'],
                    'starts_at' => $override['starts_at'],
                    'ends_at' => $override['ends_at'] ?? null,
                    'notes' => $override['notes'] ?? null,
                    'is_active' => true,
                ]);
            }

            activity()->causedBy($request->user())->performedOn($customer)->withProperties(['before' => $before, 'after' => $customer->only(array_keys($before)), 'reason' => $request->input('status_reason')])->log('customer.settings.updated');
        });

        return redirect()->route('admin.customers.settings.edit', $customer)->with('notification', ['type' => 'success', 'message' => 'Pengaturan pelanggan berhasil disimpan.']);
    }
}
