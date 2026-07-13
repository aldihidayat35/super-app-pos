<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductCategoryRequest;
use App\Http\Requests\Admin\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductCategory::class);
        $search = trim((string) $request->query('q'));

        $categories = ProductCategory::query()
            ->with('parent')
            ->withCount('products')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.product-categories.index', compact('categories', 'search'));
    }

    public function create(): View
    {
        $this->authorize('create', ProductCategory::class);

        return view('admin.product-categories.create', [
            'category' => new ProductCategory(['is_active' => true, 'sort_order' => 0]),
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        $category = DB::transaction(function () use ($request): ProductCategory {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            $category = ProductCategory::query()->create($data);
            activity()->causedBy($request->user())->performedOn($category)->log('product.category.created');

            return $category;
        });

        return redirect()->route('admin.product-categories.index')->with('notification', ['type' => 'success', 'message' => "Kategori {$category->name} berhasil dibuat."]);
    }

    public function edit(ProductCategory $productCategory): View
    {
        $this->authorize('update', $productCategory);

        return view('admin.product-categories.edit', [
            'category' => $productCategory,
            'parents' => $this->parentOptions($productCategory->id),
        ]);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        DB::transaction(function () use ($request, $productCategory): void {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            $productCategory->fill($data)->save();
            activity()->causedBy($request->user())->performedOn($productCategory)->log('product.category.updated');
        });

        return redirect()->route('admin.product-categories.index')->with('notification', ['type' => 'success', 'message' => 'Kategori berhasil diperbarui.']);
    }

    public function deactivate(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $this->authorize('update', $productCategory);

        if ($productCategory->products()->exists()) {
            return back()->with('notification', ['type' => 'danger', 'message' => 'Kategori yang sudah dipakai produk tidak boleh dihapus. Kategori dinonaktifkan saja.']);
        }

        $productCategory->forceFill(['is_active' => false])->save();
        activity()->causedBy($request->user())->performedOn($productCategory)->log('product.category.deactivated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Kategori berhasil dinonaktifkan.']);
    }

    private function parentOptions(?int $exceptId = null): mixed
    {
        return ProductCategory::query()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
