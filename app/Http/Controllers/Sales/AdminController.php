<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AssignCustomerSalesRequest;
use App\Http\Requests\Sales\StoreSalesTargetRequest;
use App\Models\Customer;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\Sales\SalesPerformanceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function performance(Request $request, SalesPerformanceService $performance): View
    {
        abort_unless($request->user()->can('sales.performance.view'), 403);
        $month = max(1, min(12, $request->integer('month', now()->month)));
        $year = max(2020, min(2100, $request->integer('year', now()->year)));

        return view('sales.admin.performance', [
            'month' => $month,
            'year' => $year,
            'rows' => $performance->all($month, $year),
        ]);
    }

    public function targets(Request $request): View
    {
        abort_unless($request->user()->can('sales.targets.manage'), 403);
        $month = max(1, min(12, $request->integer('month', now()->month)));
        $year = max(2020, min(2100, $request->integer('year', now()->year)));

        return view('sales.admin.targets', [
            'month' => $month,
            'year' => $year,
            'salesUsers' => $this->salesUsers(),
            'targets' => SalesTarget::query()->with(['sales', 'bonus'])->where('month', $month)->where('year', $year)->orderBy('sales_user_id')->get(),
        ]);
    }

    public function storeTarget(StoreSalesTargetRequest $request, SalesPerformanceService $performance): RedirectResponse
    {
        $data = $request->validated();
        $sales = User::query()->findOrFail((int) $data['sales_user_id']);
        if (! $sales->hasRole('sales')) {
            throw ValidationException::withMessages(['sales_user_id' => 'Pengguna yang dipilih tidak memiliki role Sales.']);
        }

        $period = CarbonImmutable::create((int) $data['year'], (int) $data['month'], 1)->startOfMonth();
        if ($period->lt(CarbonImmutable::now()->startOfMonth())) {
            throw ValidationException::withMessages(['month' => 'Target periode yang sudah lewat tidak dapat diubah agar histori bonus tetap konsisten.']);
        }

        $target = SalesTarget::query()->updateOrCreate(
            ['sales_user_id' => $sales->id, 'month' => $data['month'], 'year' => $data['year']],
            ['target_amount' => $data['target_amount'], 'bonus_percentage' => $data['bonus_percentage'], 'created_by' => $request->user()->id],
        );
        $performance->forUser($sales, $target->month, $target->year);

        activity()->causedBy($request->user())->performedOn($target)->log('sales.target.saved');

        return back()->with('notification', ['type' => 'success', 'message' => 'Target dan persentase bonus Sales berhasil disimpan.']);
    }

    public function assignments(Request $request): View
    {
        abort_unless($request->user()->can('sales.customers.assign'), 403);
        $term = trim((string) $request->query('q'));

        return view('sales.admin.assignments', [
            'term' => $term,
            'salesUsers' => $this->salesUsers(),
            'customers' => Customer::query()
                ->with('sales')
                ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('business_name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")))
                ->orderBy('business_name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function assign(AssignCustomerSalesRequest $request, Customer $customer): RedirectResponse
    {
        $salesId = $request->validated('sales_user_id');
        if ($salesId !== null && ! User::query()->findOrFail((int) $salesId)->hasRole('sales')) {
            throw ValidationException::withMessages(['sales_user_id' => 'Pengguna yang dipilih tidak memiliki role Sales.']);
        }

        $customer->forceFill(['sales_user_id' => $salesId])->save();
        activity()->causedBy($request->user())->performedOn($customer)->withProperties(['sales_user_id' => $salesId])->log('sales.customer.assigned');

        return back()->with('notification', ['type' => 'success', 'message' => 'Assignment customer berhasil diperbarui.']);
    }

    /** @return Collection<int, User> */
    private function salesUsers(): mixed
    {
        return User::query()->role('sales')->where('is_active', true)->orderBy('name')->get();
    }
}
