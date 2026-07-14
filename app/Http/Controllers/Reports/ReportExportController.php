<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportExportRequest;
use App\Models\ReportExport;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportMetricService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function index(Request $request, ReportMetricService $reports): View
    {
        abort_unless($request->user()->can('reports.export') || $request->user()->can('audit.export'), 403);

        $query = ReportExport::query()
            ->with('requester')
            ->when(! $request->user()->hasUnrestrictedLocationScope(), fn ($query) => $query->where('requested_by', $request->user()->id))
            ->latest('id');

        return view('reports.exports.index', [
            'exports' => $query->paginate(20)->withQueryString(),
            'labels' => $reports->reportLabels(),
        ]);
    }

    public function store(StoreReportExportRequest $request, ReportExportService $service): RedirectResponse
    {
        $export = $service->request($request->validated('report_type'), $request->validated('format'), $request->validated(), $request->user());

        return redirect()->route('reports.exports.index')->with('notification', ['type' => 'success', 'message' => "Export #{$export->id} masuk antrian."]);
    }

    public function download(Request $request, ReportExport $export): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export') || $request->user()->can('audit.export') || (int) $export->requested_by === (int) $request->user()->id, 403);
        abort_unless($export->file_path !== null && Storage::disk($export->disk)->exists($export->file_path), 404);

        return Storage::disk($export->disk)->download($export->file_path);
    }
}
