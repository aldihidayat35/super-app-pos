<?php

namespace App\Http\Controllers\Retail;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retail\StoreProductRequestProposal;
use App\Models\Branch;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductRequest;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\Retail\ProductRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductRequest::class);
        $query = ProductRequest::query()->with(['branch', 'requester', 'createdProduct']);
        if (! $request->user()->can('product_requests.approve')) {
            $query->whereHas('branch', fn ($branch) => $branch->whereIn('work_location_id', $request->user()->permittedWorkLocationIds()));
        }

        return view('retail.product-requests.index', [
            'requests' => $query->latest()->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProductRequest::class);

        return view('retail.product-requests.create', [
            'branches' => Branch::query()->where('is_active', true)->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'brands' => ProductBrand::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequestProposal $request, ProductRequestService $service): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')?->store('product-requests', 'public');
        }

        try {
            $proposal = $service->create($data, $request->user());
        } catch (ServiceException $exception) {
            if (isset($data['photo_path'])) {
                Storage::disk('public')->delete($data['photo_path']);
            }
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return redirect()->route('retail.product-requests.show', $proposal)->with('notification', ['type' => 'success', 'message' => 'Pengajuan produk dikirim untuk pemeriksaan.']);
    }

    public function show(ProductRequest $productRequest): View
    {
        $this->authorize('view', $productRequest);

        return view('retail.product-requests.show', ['proposal' => $productRequest->load(['branch', 'requester', 'reviewer', 'category', 'brand', 'baseUnit', 'supplier', 'createdProduct'])]);
    }

    public function approve(Request $request, ProductRequest $productRequest, ProductRequestService $service): RedirectResponse
    {
        $this->authorize('approve', $productRequest);
        try {
            $service->approve($productRequest, $request->user(), $request->validate(['notes' => ['nullable', 'string', 'max:1000']])['notes'] ?? null);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Produk disetujui, dibuat aktif, dan harga toko disiapkan.']);
    }

    public function reject(Request $request, ProductRequest $productRequest, ProductRequestService $service): RedirectResponse
    {
        $this->authorize('approve', $productRequest);
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        try {
            $service->reject($productRequest, $request->user(), $data['notes']);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Pengajuan produk ditolak.']);
    }
}
