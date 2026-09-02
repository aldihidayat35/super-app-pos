<?php

namespace App\Http\Controllers\Tax;

use App\Enums\TaxDirection;
use App\Enums\TaxDocumentStatus;
use App\Enums\TaxPeriodStatus;
use App\Enums\TaxType;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreTaxDocumentRequest;
use App\Http\Requests\Tax\StoreTaxProfileRequest;
use App\Http\Requests\Tax\StoreTaxRuleRequest;
use App\Http\Requests\Tax\TaxPeriodActionRequest;
use App\Http\Requests\Tax\UpdateCounterpartyTaxRequest;
use App\Http\Requests\Tax\UpdateProductTaxRequest;
use App\Http\Requests\Tax\UpdateTaxPeriodRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxDocument;
use App\Models\TaxPeriod;
use App\Models\TaxProfile;
use App\Models\TaxRule;
use App\Models\WorkLocation;
use App\Services\Tax\TaxComplianceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxComplianceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', TaxPeriod::class);
        [$month, $year] = $this->requestedPeriod($request);
        $period = TaxPeriod::query()->where('month', $month)->where('year', $year)->first();
        $documents = TaxDocument::query()
            ->with(['rule', 'period'])
            ->when($period, fn ($query) => $query->where('tax_period_id', $period->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->query('direction')))
            ->when($request->filled('reconciliation_status'), fn ($query) => $query->where('reconciliation_status', $request->query('reconciliation_status')))
            ->latest('tax_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('tax.index', [
            'month' => $month,
            'year' => $year,
            'period' => $period,
            'periods' => TaxPeriod::query()->latest('year')->latest('month')->limit(24)->get(),
            'documents' => $documents,
            'directions' => TaxDirection::options(),
            'taxTypes' => TaxType::options(),
            'rules' => TaxRule::query()->where('is_active', true)->orderBy('name')->get(),
            'workLocations' => WorkLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['direction', 'reconciliation_status']),
            'issueCounts' => [
                'unmatched' => $period ? $period->documents()->where('reconciliation_status', 'unmatched')->count() : 0,
                'missing_tax_number' => $period ? $period->documents()->where('counterparty_type', '!=', 'consumer')->whereNull('counterparty_tax_number')->count() : 0,
                'draft' => $period ? $period->documents()->where('status', TaxDocumentStatus::DRAFT->value)->count() : 0,
            ],
        ]);
    }

    public function settings(): View
    {
        $this->authorize('manage', TaxPeriod::class);

        return view('tax.settings', [
            'profile' => TaxProfile::query()->where('scope_key', 'company')->first(),
            'rules' => TaxRule::query()->latest('effective_from')->latest('id')->get(),
            'products' => Product::query()->with('taxRule')->orderBy('name')->limit(100)->get(),
            'customers' => Customer::query()->orderBy('business_name')->limit(100)->get(),
            'suppliers' => Supplier::query()->orderBy('name')->limit(100)->get(),
            'taxTypes' => TaxType::options(),
        ]);
    }

    public function storeProfile(StoreTaxProfileRequest $request): RedirectResponse
    {
        TaxProfile::query()->updateOrCreate(
            ['scope_key' => 'company'],
            [...$request->validated(), 'is_pkp' => $request->boolean('is_pkp'), 'calculation_enabled' => $request->boolean('calculation_enabled'), 'updated_by' => $request->user()->id],
        );
        activity()->causedBy($request->user())->log('tax.profile.saved');

        return back()->with('notification', ['type' => 'success', 'message' => 'Profil pajak perusahaan berhasil disimpan.']);
    }

    public function storeRule(StoreTaxRuleRequest $request): RedirectResponse
    {
        $rule = TaxRule::query()->create([
            ...$request->validated(),
            'is_creditable' => $request->boolean('is_creditable'),
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);
        activity()->causedBy($request->user())->performedOn($rule)->log('tax.rule.created');

        return back()->with('notification', ['type' => 'success', 'message' => 'Aturan pajak bertanggal efektif berhasil ditambahkan.']);
    }

    public function updateProduct(UpdateProductTaxRequest $request, Product $product): RedirectResponse
    {
        $product->forceFill([
            ...$request->validated(),
            'is_taxable' => $request->boolean('is_taxable'),
        ])->save();
        activity()->causedBy($request->user())->performedOn($product)->log('tax.product.classified');

        return back()->with('notification', ['type' => 'success', 'message' => 'Klasifikasi pajak produk berhasil diperbarui.']);
    }

    public function updateCounterparty(UpdateCounterpartyTaxRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $party = $data['party_type'] === 'customer'
            ? Customer::query()->findOrFail($data['party_id'])
            : Supplier::query()->findOrFail($data['party_id']);
        $party->forceFill([
            'tax_number' => $data['tax_number'] ?? null,
            'tax_identity_type' => $data['tax_identity_type'] ?? null,
            'tax_address' => $data['tax_address'] ?? null,
            'is_pkp' => $request->boolean('is_pkp'),
        ])->save();
        activity()->causedBy($request->user())->performedOn($party)->log('tax.counterparty.classified');

        return back()->with('notification', ['type' => 'success', 'message' => 'Identitas pajak lawan transaksi berhasil diperbarui.']);
    }

    public function storeDocument(StoreTaxDocumentRequest $request, TaxComplianceService $service): RedirectResponse
    {
        try {
            $service->storeManualDocument($request->validated(), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['document' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Dokumen pajak berhasil diposting ke masa pajak.']);
    }

    public function sync(Request $request, TaxComplianceService $service): RedirectResponse
    {
        $this->authorize('manage', TaxPeriod::class);
        [$month, $year] = $this->requestedPeriod($request);
        $period = $service->ensurePeriod($month, $year);

        try {
            $service->syncPeriod($period, $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['period' => $exception->getMessage()]);
        }

        return redirect()->route('tax.index', ['month' => $month, 'year' => $year])
            ->with('notification', ['type' => 'success', 'message' => 'Transaksi penjualan, invoice, dan retur berhasil disinkronkan.']);
    }

    public function updatePeriod(UpdateTaxPeriodRequest $request, TaxPeriod $taxPeriod, TaxComplianceService $service): RedirectResponse
    {
        if ($taxPeriod->status === TaxPeriodStatus::LOCKED) {
            throw ValidationException::withMessages(['period' => 'Masa pajak yang dikunci tidak dapat diubah.']);
        }
        $taxPeriod->forceFill($request->validated())->save();
        $service->recalculate($taxPeriod);
        activity()->causedBy($request->user())->performedOn($taxPeriod)->log('tax.period.updated');

        return back()->with('notification', ['type' => 'success', 'message' => 'Kompensasi dan catatan masa pajak berhasil diperbarui.']);
    }

    public function transition(TaxPeriodActionRequest $request, TaxPeriod $taxPeriod, TaxComplianceService $service): RedirectResponse
    {
        try {
            $service->transition($taxPeriod, $request->validated('action'), $request->user(), $request->validated());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['period' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Status masa pajak berhasil diperbarui.']);
    }

    public function reconcile(Request $request, TaxDocument $taxDocument, TaxComplianceService $service): RedirectResponse
    {
        $this->authorize('manage', TaxPeriod::class);
        $data = $request->validate([
            'coretax_reference' => ['nullable', 'string', 'max:160'],
            'reconciliation_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $service->reconcile($taxDocument, $request->user(), $data['coretax_reference'] ?? null, $data['reconciliation_notes'] ?? null);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['document' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Dokumen berhasil ditandai cocok dengan data pajak eksternal.']);
    }

    public function reverse(Request $request, TaxDocument $taxDocument, TaxComplianceService $service): RedirectResponse
    {
        $this->authorize('manage', TaxPeriod::class);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], [], ['reason' => 'alasan reversal']);

        try {
            $service->reverse($taxDocument, $request->user(), $data['reason']);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['document' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Dokumen pajak dibalik tanpa menghapus histori.']);
    }

    public function export(Request $request, TaxPeriod $taxPeriod): StreamedResponse
    {
        $this->authorize('export', $taxPeriod);
        $filename = sprintf('tax-register-%04d-%02d.csv', $taxPeriod->year, $taxPeriod->month);

        return response()->streamDownload(function () use ($taxPeriod): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['Arah', 'Jenis Pajak', 'Jenis Dokumen', 'Nomor Dokumen', 'Tanggal Pajak', 'Nama Lawan Transaksi', 'NPWP/NIK', 'DPP', 'Tarif', 'Faktor DPP', 'PPN', 'PPnBM', 'PPh Dipotong', 'Total', 'Dapat Dikreditkan', 'Status Rekonsiliasi', 'Referensi Coretax']);
            $taxPeriod->documents()->whereNotIn('status', [TaxDocumentStatus::CANCELLED->value, TaxDocumentStatus::REVERSED->value])->orderBy('tax_date')->orderBy('id')->each(function (TaxDocument $document) use ($handle): void {
                fputcsv($handle, [
                    $document->direction->value,
                    $document->tax_type->value,
                    $document->document_type,
                    $document->document_number,
                    $document->tax_date->format('Y-m-d'),
                    $document->counterparty_name,
                    $document->counterparty_tax_number,
                    $document->dpp_amount,
                    $document->tax_rate,
                    $document->dpp_factor,
                    $document->tax_amount,
                    $document->luxury_tax_amount,
                    $document->withholding_tax_amount,
                    $document->total_amount,
                    $document->is_creditable ? '1' : '0',
                    $document->reconciliation_status,
                    $document->coretax_reference,
                ]);
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{int, int} */
    private function requestedPeriod(Request $request): array
    {
        return [
            max(1, min(12, $request->integer('month', now()->month))),
            max(2020, min(2100, $request->integer('year', now()->year))),
        ];
    }
}
