<?php

namespace App\Http\Controllers\Tax;

use App\Enums\IncomeTaxScheme;
use App\Exports\AnnualFinanceExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinancialTransitionRequest;
use App\Http\Requests\Finance\StoreFinancialEntryRequest;
use App\Http\Requests\Finance\StoreFinancialYearRequest;
use App\Http\Requests\Finance\UpdateFinancialYearRequest;
use App\Models\FinancialMonthlyClosing;
use App\Models\FinancialYear;
use App\Services\Finance\AnnualFinanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnnualFinanceController extends Controller
{
    public function __construct(private readonly AnnualFinanceService $service) {}

    public function index(Request $request): View
    {
        $years = FinancialYear::query()->latest('year')->get();
        $year = FinancialYear::query()->with('months.entries.creator')->where('year', $request->integer('year', (int) now('Asia/Jakarta')->format('Y')))->first() ?? $years->first();

        return view('tax.annual', ['years' => $years, 'year' => $year, 'report' => $year ? $this->service->report($year) : null, 'accounts' => AnnualFinanceService::ACCOUNTS, 'schemes' => IncomeTaxScheme::cases(), 'tab' => $request->string('tab', 'summary')->toString()]);
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
