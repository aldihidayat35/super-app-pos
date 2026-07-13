<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductBrandRequest;
use App\Http\Requests\Admin\UpdateProductBrandRequest;
use App\Models\ProductBrand;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductBrandController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductBrand::class);
        $search = trim((string) $request->query('q'));

        $brands = ProductBrand::query()
            ->withCount('products')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.product-brands.index', compact('brands', 'search'));
    }

    public function create(): View
    {
        $this->authorize('create', ProductBrand::class);

        return view('admin.product-brands.create', ['brand' => new ProductBrand(['is_active' => true])]);
    }

    public function store(StoreProductBrandRequest $request): RedirectResponse
    {
        $brand = DB::transaction(function () use ($request): ProductBrand {
            $data = $request->validated();
            unset($data['logo']);
            $data['is_active'] = $request->boolean('is_active');
            if ($request->hasFile('logo')) {
                $data['logo_path'] = $request->file('logo')?->store('product-brands', 'public');
            }
            $brand = ProductBrand::query()->create($data);
            activity()->causedBy($request->user())->performedOn($brand)->log('product.brand.created');

            return $brand;
        });

        return redirect()->route('admin.product-brands.index')->with('notification', ['type' => 'success', 'message' => "Merek {$brand->name} berhasil dibuat."]);
    }

    public function edit(ProductBrand $productBrand): View
    {
        $this->authorize('update', $productBrand);

        return view('admin.product-brands.edit', ['brand' => $productBrand]);
    }

    public function update(UpdateProductBrandRequest $request, ProductBrand $productBrand): RedirectResponse
    {
        DB::transaction(function () use ($request, $productBrand): void {
            $data = $request->validated();
            unset($data['logo']);
            $data['is_active'] = $request->boolean('is_active');
            if ($request->hasFile('logo')) {
                $data['logo_path'] = $request->file('logo')?->store('product-brands', 'public');
            }
            $productBrand->fill($data)->save();
            activity()->causedBy($request->user())->performedOn($productBrand)->log('product.brand.updated');
        });

        return redirect()->route('admin.product-brands.index')->with('notification', ['type' => 'success', 'message' => 'Merek berhasil diperbarui.']);
    }

    public function deactivate(Request $request, ProductBrand $productBrand): RedirectResponse
    {
        $this->authorize('update', $productBrand);
        $productBrand->forceFill(['is_active' => false])->save();
        activity()->causedBy($request->user())->performedOn($productBrand)->log('product.brand.deactivated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Merek berhasil dinonaktifkan.']);
    }
}
