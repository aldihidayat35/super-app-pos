<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCustomerAccessRequest;
use App\Models\Customer;
use App\Services\Party\CustomerAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class CustomerAccessController extends Controller
{
    public function edit(Customer $customer): View
    {
        $this->authorize('manageAccess', $customer);

        return view('admin.customers.access', ['customer' => $customer->load(['addresses', 'users'])]);
    }

    public function update(UpdateCustomerAccessRequest $request, Customer $customer, CustomerAccessService $service): RedirectResponse
    {
        $service->syncAddresses($customer, $request->validated('addresses', []), $request->integer('primary_address_index'));
        $service->syncUsers($customer, $request->validated('users', []));
        activity()->causedBy($request->user())->performedOn($customer)->log('customer.access.updated');

        return redirect()->route('admin.customers.access.edit', $customer)->with('notification', ['type' => 'success', 'message' => 'Alamat dan akses B2B berhasil disimpan.']);
    }

    public function sendReset(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('manageAccess', $customer);
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($data);
        activity()->causedBy($request->user())->performedOn($customer)->withProperties(['email' => $data['email']])->log('customer.b2b.reset_password_sent');

        return back()->with('notification', ['type' => 'success', 'message' => 'Link reset password dikirim melalui mailer yang aktif.']);
    }
}
