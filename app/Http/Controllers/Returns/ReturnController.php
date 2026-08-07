<?php

namespace App\Http\Controllers\Returns;

use App\Enums\ReturnCondition;
use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\InspectReturnRequest;
use App\Http\Requests\Returns\SettleReturnRequest;
use App\Http\Requests\Returns\StoreReturnRequest;
use App\Models\Attachment;
use App\Models\Product;
use App\Models\ReturnDocument;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Returns\ReturnService;
use App\Services\Returns\ReturnSourceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReturnController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ReturnDocument::class);

        $returns = ReturnDocument::query()
            ->with(['workLocation', 'requester', 'items'])
            ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->query('source_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search->where('number', 'like', '%'.$request->query('q').'%')->orWhere('reference_no', 'like', '%'.$request->query('q').'%')->orWhere('source_name', 'like', '%'.$request->query('q').'%')))
            ->latest('return_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('returns.index', [
            'returns' => $returns,
            'statuses' => ReturnStatus::options(),
            'filters' => $request->only(['source_type', 'status', 'q']),
        ]);
    }

    public function create(Request $request, ReturnSourceService $sources): View
    {
        $this->authorize('create', ReturnDocument::class);

        return view('returns.create', $this->formData($request, $sources, new ReturnDocument([
            'return_date' => now(),
            'status' => ReturnStatus::DRAFT,
        ])));
    }

    public function store(StoreReturnRequest $request, ReturnService $service): RedirectResponse
    {
        $data = $request->validated();
        $existing = filled($data['idempotency_key'] ?? null)
            ? ReturnDocument::query()->where('idempotency_key', $data['idempotency_key'])->first()
            : null;
        if ($existing instanceof ReturnDocument) {
            return redirect()->route('returns.show', $existing);
        }

        [$data, $storedFiles] = $this->storeEvidenceFiles($request, $data);
        try {
            $return = DB::transaction(function () use ($data, $storedFiles, $request, $service): ReturnDocument {
                $return = $service->create($data, $request->user());
                $this->attachEvidence($return, $storedFiles, $request);

                return $return;
            });
        } catch (ServiceException $exception) {
            $this->deleteStoredFiles($storedFiles);

            return back()->withInput()->withErrors(['return' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);
            throw $exception;
        }

        $message = $return->status === ReturnStatus::DRAFT
            ? "Draft retur {$return->number} berhasil disimpan."
            : "Retur {$return->number} berhasil diajukan untuk pemeriksaan QC.";

        return redirect()->route('returns.show', $return)->with('notification', ['type' => 'success', 'message' => $message]);
    }

    public function edit(Request $request, ReturnDocument $return, ReturnSourceService $sources): View
    {
        $this->authorize('update', $return);

        return view('returns.edit', $this->formData($request, $sources, $return->load(['items.product.baseUnit', 'items.warehouseLocation', 'attachments'])));
    }

    public function update(StoreReturnRequest $request, ReturnDocument $return, ReturnService $service): RedirectResponse
    {
        $this->authorize('update', $return);
        $data = $request->validated();
        [$data, $storedFiles] = $this->storeEvidenceFiles($request, $data);

        try {
            $return = DB::transaction(function () use ($data, $storedFiles, $request, $return, $service): ReturnDocument {
                $return = $service->updateDraft($return, $data, $request->user());
                $this->attachEvidence($return, $storedFiles, $request);

                return $return;
            });
        } catch (ServiceException $exception) {
            $this->deleteStoredFiles($storedFiles);

            return back()->withInput()->withErrors(['return' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);
            throw $exception;
        }

        $message = $return->status === ReturnStatus::DRAFT
            ? "Draft retur {$return->number} berhasil diperbarui."
            : "Retur {$return->number} berhasil diajukan untuk pemeriksaan QC.";

        return redirect()->route('returns.show', $return)->with('notification', ['type' => 'success', 'message' => $message]);
    }

    public function show(ReturnDocument $return): View
    {
        $this->authorize('view', $return);

        return view('returns.show', ['return' => $this->loadReturn($return)]);
    }

    public function inspection(ReturnDocument $return, Request $request): View
    {
        $this->authorize('inspect', $return);

        return view('returns.inspection', [
            'return' => $this->loadReturn($return),
            'conditions' => ReturnCondition::options(),
            'resolutions' => ReturnResolution::options(),
            'warehouseLocations' => WarehouseLocation::query()
                ->where('is_active', true)
                ->whereHas('warehouse', fn ($query) => $query->where('work_location_id', $return->work_location_id))
                ->orderBy('full_code')->limit(300)->get(),
        ]);
    }

    public function inspect(InspectReturnRequest $request, ReturnDocument $return, ReturnService $service): RedirectResponse
    {
        $this->authorize('inspect', $return);
        $updated = $service->inspect($return, $request->validated(), $request->user());

        return redirect()->route('returns.show', $updated)->with('notification', ['type' => 'success', 'message' => 'QC retur berhasil disimpan.']);
    }

    public function approval(ReturnDocument $return): View
    {
        $this->authorize('view', $return);

        return view('returns.approval', ['return' => $this->loadReturn($return)]);
    }

    public function approve(Request $request, ReturnDocument $return, ReturnService $service): RedirectResponse
    {
        $this->authorize('approve', $return);
        $service->approve($return, $request->user(), $request->input('notes'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Retur berhasil disetujui.']);
    }

    public function settlement(ReturnDocument $return): View
    {
        $this->authorize('settle', $return);

        return view('returns.settlement', ['return' => $this->loadReturn($return), 'resolutions' => ReturnResolution::options()]);
    }

    public function settle(SettleReturnRequest $request, ReturnDocument $return, ReturnService $service): RedirectResponse
    {
        $this->authorize('settle', $return);
        $updated = $service->settle($return, $request->validated(), $request->user());

        return redirect()->route('returns.show', $updated)->with('notification', ['type' => 'success', 'message' => 'Retur berhasil diselesaikan.']);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', ReturnDocument::class);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nomor', 'Tanggal', 'Sumber', 'Referensi', 'Status', 'Qty', 'Nilai', 'Loss']);
            ReturnDocument::query()
                ->whereIn('work_location_id', $request->user()?->permittedWorkLocationIds() ?? [])
                ->orderBy('return_date')
                ->chunk(200, function ($returns) use ($handle): void {
                    foreach ($returns as $return) {
                        $returnDate = $return->getAttribute('return_date');
                        fputcsv($handle, [$return->number, $returnDate instanceof Carbon ? $returnDate->format('Y-m-d') : (string) $return->return_date, $return->source_name, $return->reference_no, $return->status->label(), $return->total_quantity, $return->total_value, $return->total_loss_value]);
                    }
                });
            fclose($handle);
        }, 'returns.csv');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, ReturnSourceService $sources, ReturnDocument $return): array
    {
        $showCost = $request->user()?->can('margins.view_sensitive') ?? false;
        $sourceType = (string) old('source_type', $return->source_type ?: 'pos');
        $referenceId = (int) old('reference_id', (string) ($return->reference_id ?: 0));
        $workLocationId = (int) old('work_location_id', (string) ($return->work_location_id ?: 0));
        $selectedSourceDocument = null;
        $resolvedSourceItems = collect();
        if (! in_array($sourceType, ['manual', 'branch'], true) && $referenceId > 0 && $workLocationId > 0) {
            try {
                $selectedSourceDocument = $sources->document($sourceType, $referenceId, $workLocationId);
                $resolvedSourceItems = collect($sources->documentItems($sourceType, $referenceId, $workLocationId, $showCost, $return->exists ? $return->id : null))->keyBy('source_item_id');
            } catch (ServiceException) {
                $selectedSourceDocument = null;
            }
        }

        $oldItems = old('items');
        if (is_array($oldItems)) {
            $products = Product::query()->with('baseUnit')->whereIn('id', collect($oldItems)->pluck('product_id')->filter())->get()->keyBy('id');
            $locationIds = collect($oldItems)->pluck('warehouse_location_id')->filter();
            $locations = WarehouseLocation::query()->whereIn('id', $locationIds)->get()->keyBy('id');
            $formItems = collect($oldItems)->map(function (array $item) use ($products, $locations, $resolvedSourceItems, $showCost): array {
                $product = $products->get((int) ($item['product_id'] ?? 0));
                $source = $resolvedSourceItems->get((int) ($item['source_item_id'] ?? 0), []);
                $location = $locations->get((int) ($item['warehouse_location_id'] ?? 0));

                return [
                    'product_id' => $product?->id,
                    'source_item_id' => $item['source_item_id'] ?? null,
                    'source_item_type' => $source['source_item_type'] ?? null,
                    'sku' => $source['sku'] ?? $product?->sku,
                    'name' => $source['name'] ?? $product?->name,
                    'thumbnail' => $source['thumbnail'] ?? $product?->main_image_url,
                    'unit' => $source['unit'] ?? $product?->baseUnit?->name,
                    'source_quantity' => $source['source_quantity'] ?? null,
                    'already_returned' => $source['already_returned'] ?? '0.0000',
                    'maximum_quantity' => $source['maximum_quantity'] ?? null,
                    'quantity_requested' => $item['quantity_requested'] ?? null,
                    'unit_cost' => $showCost ? ($source['unit_cost'] ?? $product?->cost_price) : null,
                    'condition' => $item['condition'] ?? ReturnCondition::GOOD->value,
                    'warehouse_location_id' => $item['warehouse_location_id'] ?? null,
                    'warehouse_location_text' => $location?->full_code,
                    'notes' => $item['notes'] ?? null,
                ];
            })->values()->all();
        } elseif ($return->exists) {
            $formItems = $return->items->map(function ($item) use ($resolvedSourceItems, $showCost): array {
                $source = $resolvedSourceItems->get((int) $item->source_item_id, []);

                return [
                    'product_id' => $item->product_id,
                    'source_item_id' => $item->source_item_id,
                    'source_item_type' => $item->source_item_type,
                    'sku' => $item->product_sku_snapshot,
                    'name' => $item->product_name_snapshot,
                    'thumbnail' => $item->product?->main_image_url,
                    'unit' => $item->unit_name_snapshot ?: $item->product?->baseUnit?->name,
                    'source_quantity' => $source['source_quantity'] ?? (string) $item->source_quantity,
                    'already_returned' => $source['already_returned'] ?? '0.0000',
                    'maximum_quantity' => $source['maximum_quantity'] ?? (string) $item->source_quantity,
                    'quantity_requested' => (string) $item->quantity_requested,
                    'unit_cost' => $showCost ? ($source['unit_cost'] ?? (string) $item->unit_cost_snapshot) : null,
                    'condition' => (string) $item->getRawOriginal('condition'),
                    'warehouse_location_id' => $item->warehouse_location_id,
                    'warehouse_location_text' => $item->warehouseLocation?->full_code,
                    'notes' => $item->notes,
                ];
            })->values()->all();
        } else {
            $formItems = [];
        }

        return [
            'workLocations' => WorkLocation::query()->whereIn('id', $request->user()?->permittedWorkLocationIds() ?? [])->where('is_active', true)->orderBy('name')->get(),
            'return' => $return,
            'sourceTypes' => ReturnSourceService::sourceOptions(),
            'conditions' => ReturnCondition::options(),
            'resolutions' => ReturnResolution::options(),
            'showCost' => $showCost,
            'approvalThreshold' => ReturnService::APPROVAL_THRESHOLD,
            'selectedSourceDocument' => $selectedSourceDocument,
            'formItems' => $formItems,
        ];
    }

    private function loadReturn(ReturnDocument $return): ReturnDocument
    {
        return $return->load(['workLocation', 'requester', 'checker', 'approver', 'items.product', 'items.warehouseLocation', 'attachments.uploader', 'inspections', 'settlements', 'stockMutations.product', 'statusHistories.actor']);
    }

    /** @param array<string, mixed> $data
     * @return array{array<string, mixed>, list<array<string, mixed>>}
     */
    private function storeEvidenceFiles(StoreReturnRequest $request, array $data): array
    {
        $storedFiles = [];
        try {
            foreach ((array) $request->file('evidence_files', []) as $file) {
                $path = $file->store('returns', 'public');
                $storedFiles[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);
            throw $exception;
        }
        unset($data['evidence_files']);
        if ($storedFiles !== []) {
            $data['evidence_path'] = $storedFiles[0]['path'];
        }

        return [$data, $storedFiles];
    }

    /** @param list<array<string, mixed>> $storedFiles */
    private function attachEvidence(ReturnDocument $return, array $storedFiles, StoreReturnRequest $request): void
    {
        foreach ($storedFiles as $file) {
            Attachment::query()->create([
                'document_type' => 'return',
                'document_id' => $return->id,
                'disk' => 'public',
                'path' => $file['path'],
                'original_name' => $file['original_name'],
                'mime_type' => $file['mime_type'],
                'size' => $file['size'],
                'uploaded_by' => $request->user()?->id,
            ]);
        }
    }

    /** @param list<array<string, mixed>> $storedFiles */
    private function deleteStoredFiles(array $storedFiles): void
    {
        Storage::disk('public')->delete(array_column($storedFiles, 'path'));
    }
}
