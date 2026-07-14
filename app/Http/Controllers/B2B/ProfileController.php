<?php

namespace App\Http\Controllers\B2B;

use App\Http\Controllers\Controller;
use App\Http\Requests\B2B\UpdateB2bProfileRequest;
use App\Services\B2B\B2bPortalService;
use App\Services\Party\CustomerAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function edit(Request $request, B2bPortalService $portal): View
    {
        $customer = $portal->activeCustomerFor($request->user())->load(['addresses', 'users', 'documents']);

        return view('b2b.profile.edit', ['customer' => $customer]);
    }

    public function update(UpdateB2bProfileRequest $request, B2bPortalService $portal, CustomerAccessService $access): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());

        DB::transaction(function () use ($request, $customer, $access): void {
            $customer->fill($request->safe()->only(['business_name', 'pic_name', 'whatsapp_number', 'email', 'business_address', 'city']))->save();
            $validated = $request->validated();
            $addresses = [];
            foreach (($validated['addresses'] ?? []) as $address) {
                if (is_array($address) && filled($address['label'] ?? null) && filled($address['address'] ?? null)) {
                    $addresses[] = $address;
                }
            }
            $access->syncAddresses($customer, $addresses, $request->integer('primary_address_index'));
        });

        activity()->causedBy($request->user())->performedOn($customer)->log('b2b.profile.updated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Profil usaha dan alamat berhasil diperbarui.']);
    }
}
