<?php

namespace App\Http\Controllers\Tax;

use App\Enums\IncomeTaxScheme;
use App\Enums\TaxType;
use App\Exports\AnnualFinanceExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinancialTransitionRequest;
use App\Http\Requests\Finance\StoreFinancialEntryRequest;
use App\Http\Requests\Finance\StoreFinancialYearRequest;
use App\Http\Requests\Finance\UpdateFinancialYearRequest;
use App\Http\Requests\Finance\UpdateTaxRatesRequest;
use App\Http\Requests\Tax\StoreTaxRuleRequest;
use App\Http\Requests\Tax\UpdateTaxRuleRequest;
use App\Models\FinancialMonthlyClosing;
use App\Models\FinancialYear;
use App\Models\TaxRule;
use App\Models\WorkLocation;
use App\Services\Finance\AnnualFinanceService;
use App\Services\Reports\OwnerOperationalReportService;
use App\Services\Reports\ReportMetricService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnnualFinanceController extends Controller
{
    public function __construct(
        private readonly AnnualFinanceService $service,
        private readonly ReportMetricService $metricService,
        private readonly OwnerOperationalReportService $ownerOperational,
    ) {}

    public function index(Request $request): View
    {
        $yearNumber = $request->integer('year', 0);
        if ($yearNumber <= 0 && $request->filled('start_date')) {
            $yearNumber = (int) date('Y', strtotime($request->string('start_date')->toString()));
        }
        if ($yearNumber <= 0) {
            $yearNumber = (int) now('Asia/Jakarta')->format('Y');
        }

        // Auto-inisialisasi tahun laporan & konfigurasi tarif nasional jika belum ada
        $year = $this->service->getOrInitializeYear($yearNumber, $request->user());
        $years = FinancialYear::query()->latest('year')->get();

        $startDate = $request->string('start_date', "{$yearNumber}-01-01")->toString();
        $endDate = $request->string('end_date', "{$yearNumber}-12-31")->toString();

        $input = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'range' => 'custom',
            'work_location_id' => $request->input('work_location_id'),
        ];
        $filters = $this->metricService->filters($request->user(), $input);
        $executiveTax = $this->ownerOperational->buildTaxReport(
            $filters,
            [],
            $request->user()->can('margins.view_sensitive')
        );

        $workLocations = WorkLocation::query()
            ->whereIn('id', $request->user()->permittedWorkLocationIds())
            ->orderBy('name')
            ->get();

        $selectedLocation = $filters['work_location_id'] !== null
            ? $workLocations->firstWhere('id', $filters['work_location_id'])
            : null;

        $tab = $request->string('tab', 'spt')->toString();

        return view('tax.annual', [
            'years' => $years,
            'year' => $year,
            'report' => $this->service->report($year),
            'executiveTax' => $executiveTax,
            'filters' => $filters,
            'workLocations' => $workLocations,
            'selectedLocation' => $selectedLocation,
            'accounts' => AnnualFinanceService::ACCOUNTS,
            'schemes' => IncomeTaxScheme::cases(),
            'tab' => $tab,
            'taxRules' => TaxRule::query()->latest('effective_from')->latest('id')->get(),
            'taxTypes' => TaxType::options(),
        ]);
    }

    public function storeYear(StoreFinancialYearRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $year = $this->service->createYear($data, $request->user());

        return redirect()->route('tax.index', ['year' => $year->year])->with('success', 'Tahun laporan dan 12 rekap bulanan berhasil dibuat.');
    }

    public function updateYear(UpdateFinancialYearRequest $request, FinancialYear $financialYear): RedirectResponse
    {
        $data = $request->validated();
        $data['tax_scheme_confirmed'] = $request->boolean('tax_scheme_confirmed');
        $this->service->updateYear($financialYear, $data, $request->user());

        return back()->with('success', 'Identitas, rekonsiliasi fiskal, dan skema pajak tersimpan.');
    }

    public function updateTaxRates(UpdateTaxRatesRequest $request, FinancialYear $financialYear): RedirectResponse
    {
        $this->service->updateTaxRates($financialYear, $request->validated(), $request->user());

        return redirect()->route('tax.index', ['year' => $financialYear->year, 'tab' => 'rates'])
            ->with('success', 'Persentase tarif pajak tahun buku berhasil diperbarui dan laporan dihitung ulang.');
    }

    public function storeRule(StoreTaxRuleRequest $request): RedirectResponse
    {
        $this->service->storeTaxRule($request->validated(), $request->user());

        return redirect()->route('tax.index', ['year' => $request->integer('year', (int) now('Asia/Jakarta')->format('Y')), 'tab' => 'rates'])
            ->with('success', 'Aturan tarif pajak transaksi bertanggal efektif berhasil ditambahkan.');
    }

    public function updateRule(UpdateTaxRuleRequest $request, TaxRule $taxRule): RedirectResponse
    {
        $this->service->updateTaxRule($taxRule, $request->validated(), $request->user());

        return redirect()->route('tax.index', ['year' => $request->integer('year', (int) now('Asia/Jakarta')->format('Y')), 'tab' => 'rates'])
            ->with('success', 'Aturan tarif pajak transaksi berhasil diperbarui.');
    }

    public function toggleRule(Request $request, TaxRule $taxRule): RedirectResponse
    {
        $this->service->toggleTaxRule($taxRule, $request->user());

        return redirect()->route('tax.index', ['year' => $request->integer('year', (int) now('Asia/Jakarta')->format('Y')), 'tab' => 'rates'])
            ->with('success', 'Status aktif aturan tarif pajak berhasil diubah.');
    }

    public function snapshot(Request $request, FinancialMonthlyClosing $financialMonth): RedirectResponse
    {
        $this->service->snapshot($financialMonth, $request->user());

        return back()->with('success', 'Sumber transaksi bulan berhasil diambil dan disimpan sebagai snapshot.');
    }

    public function storeEntry(StoreFinancialEntryRequest $request, FinancialMonthlyClosing $financialMonth): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')->store('finance-evidence', config('filesystems.default'));
        }
        $this->service->addEntry($financialMonth, $data, $request->user());

        return back()->with('success', 'Input atau koreksi manual berhasil dicatat.');
    }

    public function monthTransition(FinancialTransitionRequest $request, FinancialMonthlyClosing $financialMonth): RedirectResponse
    {
        $data = $request->validated();
        if (in_array($data['action'], ['lock', 'reopen'], true) && ! $request->user()->can('finance_annual.approve')) {
            abort(403);
        }
        $this->service->monthTransition($financialMonth, $data['action'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Status rekap bulanan diperbarui.');
    }

    public function yearTransition(FinancialTransitionRequest $request, FinancialYear $financialYear): RedirectResponse
    {
        $data = $request->validated();
        $this->service->yearTransition($financialYear, $data['action'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Status laporan tahunan diperbarui.');
    }

    public function pdf(FinancialYear $financialYear): Response
    {
        $financialYear->load('months.entries');

        return Pdf::loadView('tax.annual-pdf', ['year' => $financialYear, 'report' => $this->service->report($financialYear)])->setPaper('a4')->download("laporan-keuangan-{$financialYear->year}.pdf");
    }

    public function excel(FinancialYear $financialYear): BinaryFileResponse
    {
        $financialYear->load('months.entries');

        return Excel::download(new AnnualFinanceExport($financialYear, $this->service->report($financialYear)), "laporan-keuangan-{$financialYear->year}.xlsx");
    }
}
