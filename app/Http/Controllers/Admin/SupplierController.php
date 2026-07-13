<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);
        $filters = ['q' => trim((string) $request->query('q')), 'status' => $request->query('status'), 'city' => trim((string) $request->query('city'))];
        $suppliers = Supplier::query()
            ->withCount('productsSupplied')
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "%{$filters['q']}%")->orWhere('name', 'like', "%{$filters['q']}%")->orWhere('contact_name', 'like', "%{$filters['q']}%")))
            ->when($filters['city'] !== '', fn ($query) => $query->where('city', 'like', "%{$filters['city']}%"))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.suppliers.index', compact('suppliers', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('admin.suppliers.create', ['supplier' => new Supplier(['is_active' => true, 'payment_term_days' => 0])]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = DB::transaction(function () use ($request): Supplier {
            $supplier = Supplier::query()->create([...$request->validated(), 'is_active' => $request->boolean('is_active')]);
            activity()->causedBy($request->user())->performedOn($supplier)->log('supplier.created');

            return $supplier;
        });

        return redirect()->route('admin.suppliers.show', $supplier)->with('notification', ['type' => 'success', 'message' => 'Supplier berhasil dibuat.']);
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        return view('admin.suppliers.show', ['supplier' => $supplier->load(['contacts', 'productsSupplied.product', 'documents'])]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        DB::transaction(function () use ($request, $supplier): void {
            $supplier->fill([...$request->validated(), 'is_active' => $request->boolean('is_active')])->save();
            activity()->causedBy($request->user())->performedOn($supplier)->log('supplier.updated');
        });

        return redirect()->route('admin.suppliers.show', $supplier)->with('notification', ['type' => 'success', 'message' => 'Supplier berhasil diperbarui.']);
    }

    public function deactivate(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);
        $supplier->forceFill(['is_active' => false])->save();
        activity()->causedBy($request->user())->performedOn($supplier)->log('supplier.deactivated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Supplier berhasil dinonaktifkan.']);
    }

    public function export(): StreamedResponse
    {
        $this->authorize('export', Supplier::class);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['code', 'name', 'contact_name', 'whatsapp_number', 'email', 'city', 'payment_term_days', 'is_active']);
            Supplier::query()->orderBy('code')->each(fn (Supplier $supplier) => fputcsv($handle, [$supplier->code, $supplier->name, $supplier->contact_name, $supplier->whatsapp_number, $supplier->email, $supplier->city, $supplier->payment_term_days, $supplier->is_active ? 1 : 0]));
            fclose($handle);
        }, 'supplier-'.now()->format('Ymd-His').'.csv');
    }
}
