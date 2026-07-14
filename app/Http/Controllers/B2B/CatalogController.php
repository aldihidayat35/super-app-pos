<?php

namespace App\Http\Controllers\B2B;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\B2B\AddCartItemRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\B2B\B2bPortalService;
use App\Services\Pricing\PriceResolverService;
use App\Support\CurrencyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public function index(Request $request, B2bPortalService $portal, PriceResolverService $priceResolver): View
    {
        $customer = $portal->activeCustomerFor($request->user());
        $filters = ['q' => trim((string) $request->query('q')), 'category_id' => $request->query('category_id'), 'sort' => $request->query('sort', 'name')];
        $products = Product::query()
            ->with(['category', 'baseUnit', 'units.unit'])
            ->where('status', 'active')
            ->when($filters['q'], function ($query, string $term): void {
                $like = '%'.$term.'%';
                $query->where(fn ($search) => $search->where('sku', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->when($filters['category_id'], fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['sort'] === 'newest', fn ($query) => $query->latest('id'), fn ($query) => $query->orderBy('name'))
            ->paginate(12)
            ->withQueryString();

        $cards = $products->getCollection()->map(function (Product $product) use ($portal, $customer, $request, $priceResolver): array {
            $resolved = $priceResolver->resolve($product, quantity: max(1, (int) $product->minimum_order), unitId: (int) $product->base_unit_id, customer: $customer, channel: 'b2b', user: $request->user());

            return [
                'product' => $product,
                'price' => CurrencyFormatter::rupiah((string) $resolved['selected_price']),
                'raw_price' => $resolved,
                'availability' => $portal->availabilityLabel($product, max(1, (int) $product->minimum_order)),
            ];
        });

        return view('b2b.catalog.index', [
            'customer' => $customer,
            'products' => $products,
            'cards' => $cards,
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Product $product, B2bPortalService $portal, PriceResolverService $priceResolver): View
    {
        $customer = $portal->activeCustomerFor($request->user());
        abort_unless($product->status->value === 'active', 404);
        $product->load(['category', 'brand', 'baseUnit', 'units.unit', 'images']);
        $unitPrices = [];
        foreach ($product->units->where('is_active', true)->where('is_sellable', true) as $productUnit) {
            $unitPrices[] = $priceResolver->resolve($product, quantity: max(1, (int) $product->minimum_order), unitId: (int) $productUnit->unit_id, customer: $customer, channel: 'b2b', user: $request->user());
        }
        if ($unitPrices === []) {
            $unitPrices[] = $priceResolver->resolve($product, quantity: max(1, (int) $product->minimum_order), unitId: (int) $product->base_unit_id, customer: $customer, channel: 'b2b', user: $request->user());
        }

        return view('b2b.catalog.show', [
            'customer' => $customer,
            'product' => $product,
            'prices' => $unitPrices,
            'availability' => $portal->availabilityLabel($product, max(1, (int) $product->minimum_order)),
        ]);
    }

    public function add(AddCartItemRequest $request, B2bPortalService $portal): RedirectResponse
    {
        $customer = $portal->activeCustomerFor($request->user());

        try {
            $portal->addToCart($customer, $request->user(), $request->validated());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage()]);
        }

        return redirect()->route('langganan.keranjang.index')->with('notification', ['type' => 'success', 'message' => 'Produk berhasil ditambahkan ke keranjang.']);
    }
}
