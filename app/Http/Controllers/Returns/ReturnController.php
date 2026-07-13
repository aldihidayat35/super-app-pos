<?php

namespace App\Http\Controllers\Returns;

use App\Enums\ReturnCondition;
use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\InspectReturnRequest;
use App\Http\Requests\Returns\SettleReturnRequest;
use App\Http\Requests\Returns\StoreReturnRequest;
use App\Models\Product;
use App\Models\ReturnDocument;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Returns\ReturnService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('returns.create'), 403);

        return view('returns.create', $this->formData($request));
    }

    public function store(StoreReturnRequest $request, ReturnService $service): RedirectResponse
    {
        $data = $this->validatedWithEvidence($request);
        abort_unless($request->user()?->canAccessWorkLocation((int) $data['work_location_id']), 403);

        $return = $service->create($data, $request->user());

        return redirect()->route('returns.show', $return)->with('notification', ['type' => 'success', 'message' => "Retur {$return->number} berhasil dibuat."]);
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
            'warehouseLocations' => WarehouseLocation::query()->where('is_active', true)->orderBy('full_code')->limit(300)->get(),
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
    private function formData(Request $request): array
    {
        return [
            'workLocations' => WorkLocation::query()->whereIn('id', $request->user()?->permittedWorkLocationIds() ?? [])->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)->get(),
            'warehouseLocations' => WarehouseLocation::query()->where('is_active', true)->orderBy('full_code')->limit(300)->get(),
            'conditions' => ReturnCondition::options(),
            'resolutions' => ReturnResolution::options(),
        ];
    }

    private function loadReturn(ReturnDocument $return): ReturnDocument
    {
        return $return->load(['workLocation', 'requester', 'checker', 'approver', 'items.product', 'items.warehouseLocation', 'inspections', 'settlements', 'stockMutations.product', 'statusHistories.actor']);
    }

    /** @return array<string, mixed> */
    private function validatedWithEvidence(StoreReturnRequest $request): array
    {
        $data = $request->validated();
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')?->store('returns', 'public');
        }

        return $data;
    }
}
