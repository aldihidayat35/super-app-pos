<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierScore;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SupplierPerformanceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', SupplierScore::class);

        $dateFrom = $request->query('date_from', now('Asia/Jakarta')->startOfMonth()->toDateString());
        $dateTo = $request->query('date_to', now('Asia/Jakarta')->toDateString());
        $base = SupplierScore::query()
            ->with(['supplier', 'goodsReceipt.items'])
            ->when($request->integer('supplier_id') > 0, fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->integer('product_id') > 0, fn ($query) => $query->whereHas('goodsReceipt.items', fn ($items) => $items->where('product_id', $request->integer('product_id'))))
            ->whereDate('received_at', '>=', $dateFrom)
            ->whereDate('received_at', '<=', $dateTo);

        $summaryRows = (clone $base)->get([
            'supplier_id', 'quantity_received', 'quantity_accepted', 'quality_score', 'delivery_score', 'price_score', 'total_score', 'received_at',
        ]);
        $received = (string) $summaryRows->sum(fn (SupplierScore $score): float => (float) $score->quantity_received);
        $accepted = (string) $summaryRows->sum(fn (SupplierScore $score): float => (float) $score->quantity_accepted);
        $scoreTrend = $summaryRows
            ->groupBy(fn (SupplierScore $score): string => Carbon::parse($score->received_at)->toDateString())
            ->map(fn (Collection $items, string $date): array => [
                'date' => $date,
                'total_score' => round((float) $items->avg('total_score'), 2),
                'quality_score' => round((float) $items->avg('quality_score'), 2),
                'delivery_score' => round((float) $items->avg('delivery_score'), 2),
            ])
            ->sortBy('date')
            ->values();
        $ranking = (clone $base)
            ->join('suppliers', 'suppliers.id', '=', 'supplier_scores.supplier_id')
            ->selectRaw('suppliers.name as supplier, AVG(supplier_scores.total_score) as total_score, AVG(supplier_scores.quality_score) as quality_score, COUNT(supplier_scores.id) as receipt_count')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_score')
            ->limit(8)
            ->get();
        $scores = (clone $base)
            ->latest('received_at')
            ->paginate(15)
            ->withQueryString();

        return view('reports.suppliers.index', [
            'scores' => $scores,
            'summary' => [
                'receipts_evaluated' => $summaryRows->count(),
                'average_score' => round((float) ($summaryRows->avg('total_score') ?? 0), 2),
                'average_quality' => round((float) ($summaryRows->avg('quality_score') ?? 0), 2),
                'acceptance_rate' => Decimal::compare($received, '0', 4) === 0
                    ? '0.00'
                    : Decimal::mul(Decimal::div($accepted, $received, 4, 4, 4), '100', 4, 0, 2),
                'suppliers_need_review' => $summaryRows->where('total_score', '<', 80)->pluck('supplier_id')->unique()->count(),
            ],
            'scoreTrend' => $scoreTrend,
            'ranking' => $ranking,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'products' => Product::query()->orderBy('name')->limit(200)->get(),
            'filters' => $request->only(['supplier_id', 'product_id']) + ['date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }
}
