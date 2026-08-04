<?php

namespace App\Http\Controllers\Pricing;

use App\Enums\ProductPriceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StoreProductPriceRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Pricing\PriceManagementService;
use App\Services\Pricing\PriceResolverService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPriceController extends Controller
{
    public function index(Request $request, PriceResolverService $resolver): View
    {
        $this->authorize('viewAny', ProductPrice::class);

        $prices = ProductPrice::query()
            ->with(['product', 'branch'])
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->query('channel')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('pricing.product-prices.index', [
            'prices' => $prices,
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'resolver' => $resolver,
            'filters' => $request->only(['product_id', 'channel']),
        ]);
    }

    public function store(StoreProductPriceRequest $request, PriceManagementService $service): RedirectResponse
    {
        $data = $request->validated();
        $productIds = array_values(array_unique($data['product_ids'] ?? [$data['product_id']]));
        unset($data['product_ids']);

        $price = null;
        foreach ($productIds as $productId) {
            $price = $service->saveProductPrice([...$data, 'product_id' => $productId], $request->user());
        }

        assert($price instanceof ProductPrice);

        $message = $price->status === ProductPriceStatus::DRAFT
            ? 'Harga disimpan sebagai draft dan masuk antrian approval.'
            : count($productIds).' harga produk berhasil disimpan.';

        return back()->with('notification', ['type' => 'success', 'message' => $message]);
    }

    public function edit(ProductPrice $productPrice): View
    {
        $this->authorize('update', $productPrice);

        return view('pricing.product-prices.edit', [
            'price' => $productPrice->load(['product', 'branch']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(StoreProductPriceRequest $request, ProductPrice $productPrice, PriceManagementService $service): RedirectResponse
    {
        $this->authorize('update', $productPrice);
        if ($productPrice->status !== ProductPriceStatus::DRAFT) {
            throw ValidationException::withMessages(['price' => 'Hanya harga berstatus draft yang dapat diedit. Gunakan Buat Revisi untuk harga yang sudah aktif.']);
        }

        $data = $request->validated();
        $service->saveProductPrice([...$data, 'id' => $productPrice->id, 'product_id' => $productPrice->product_id], $request->user());

        return redirect()->route('pricing.product-prices.edit', $productPrice)
            ->with('notification', ['type' => 'success', 'message' => 'Draft harga berhasil diperbarui.']);
    }

    public function revise(StoreProductPriceRequest $request, ProductPrice $productPrice, PriceManagementService $service): RedirectResponse
    {
        $this->authorize('update', $productPrice);
        $data = $request->validated();
        $revision = $service->saveProductPrice([...$data, 'product_id' => $productPrice->product_id], $request->user());

        return redirect()->route('pricing.product-prices.edit', $revision)
            ->with('notification', ['type' => 'success', 'message' => $revision->status === ProductPriceStatus::DRAFT
                ? 'Revisi dibuat sebagai draft dan menunggu approval. Harga lama tetap aktif.'
                : 'Revisi harga berhasil dibuat. Akhiri harga lama agar tidak ada periode yang bertumpuk.']);
    }

    public function end(Request $request, ProductPrice $productPrice, PriceManagementService $service): RedirectResponse
    {
        $this->authorize('update', $productPrice);
        $data = Validator::make($request->all(), [
            'ends_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ], [], ['ends_at' => 'tanggal selesai', 'reason' => 'alasan'])->validate();

        $service->endProductPrice($productPrice, $data['ends_at'], $request->user(), $data['reason']);

        return redirect()->route('pricing.product-prices.edit', $productPrice)
            ->with('notification', ['type' => 'success', 'message' => 'Masa berlaku harga berhasil diperbarui tanpa menghapus histori.']);
    }

    public function destroy(ProductPrice $productPrice): RedirectResponse
    {
        $this->authorize('delete', $productPrice);

        DB::transaction(fn () => $productPrice->delete());

        return redirect()->route('pricing.product-prices.index')
            ->with('notification', ['type' => 'success', 'message' => 'Harga produk berhasil dihapus dari daftar. Histori audit tetap disimpan.']);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductPrice::class);

        $query = $request->input('q', '');
        $exclude = $request->input('exclude', []);
        if (is_array($exclude)) {
            $exclude = array_map('intval', $exclude);
        } else {
            $exclude = array_filter(array_map('intval', explode(',', (string) $exclude)));
        }

        $results = Product::query()
            ->where('status', 'active')
            ->with('category')
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($w) use ($query) {
                    $w->where('sku', 'like', "%{$query}%")
                        ->orWhere('name', 'like', "%{$query}%");
                    if (preg_match('/^[\p{L}0-9\s\-_]+$/u', $query)) {
                        $w->orWhere('description', 'like', "%{$query}%");
                    }
                });
            })
            ->when(! empty($exclude), fn ($q) => $q->whereNotIn('id', $exclude))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'sku', 'name', 'category_id']);

        return response()->json([
            'products' => $results->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category?->name,
            ]),
            'total' => $results->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', ProductPrice::class);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Produk', 'Nama', 'Cabang', 'Channel', 'Ring', 'Kategori', 'Min', 'Rekomendasi', 'Maks', 'Min Qty', 'Status', 'Mulai', 'Selesai']);

            ProductPrice::query()
                ->with(['product', 'branch'])
                ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
                ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->query('channel')))
                ->latest('id')
                ->each(function (ProductPrice $price) use ($handle): void {
                    fputcsv($handle, [
                        $price->product?->sku,
                        $price->product?->name,
                        $price->branch?->name,
                        $price->channel,
                        $price->price_ring,
                        $price->customer_category,
                        $price->min_price,
                        $price->recommended_price,
                        $price->max_price,
                        $price->minimum_qty,
                        $price->status->value,
                        $price->starts_at,
                        $price->ends_at,
                    ]);
                });
            fclose($handle);
        }, 'product-prices.csv');
    }
}
