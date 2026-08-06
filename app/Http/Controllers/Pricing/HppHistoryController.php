<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\Supplier;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HppHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductCostHistory::class);

        $filtered = $this->query($request);
        $latest = (clone $filtered)->first();
        $previous = $latest
            ? (clone $filtered)->where('product_id', $latest->product_id)->skip(1)->first()
            : null;
        $difference = $latest ? Decimal::sub((string) $latest->hpp_after, (string) ($previous ? $previous->hpp_after : $latest->hpp_after), 2) : '0.00';
        $percentage = $previous && Decimal::compare((string) $previous->hpp_after, '0', 2) !== 0
            ? Decimal::mul(Decimal::div($difference, (string) $previous->hpp_after, 2, 2, 4), '100', 4, 2, 2)
            : '0.00';

        $chartHistories = (clone $filtered)->limit(40)->get()->sortBy([
            ['effective_at', 'asc'],
            ['id', 'asc'],
        ])->values();

        return view('pricing.hpp-history.index', [
            'histories' => (clone $filtered)->paginate(15)->withQueryString(),
            'chartHistories' => $chartHistories,
            'chartData' => $chartHistories->map(function (ProductCostHistory $history): array {
                $effectiveAt = $history->getAttribute('effective_at');

                return [
                    'x' => $effectiveAt instanceof \DateTimeInterface ? $effectiveAt->getTimestamp() * 1000 : null,
                    'y' => (string) $history->hpp_after,
                    'dateLabel' => $effectiveAt instanceof \DateTimeInterface ? $effectiveAt->format('d/m/Y H:i') : '-',
                    'product' => $history->product?->name ?: 'Tanpa nama',
                    'supplier' => $history->supplier?->name ?: '-',
                    'receipt' => $history->goodsReceipt?->number ?: '-',
                ];
            })->values(),
            'summary' => [
                'latest' => $latest,
                'previous' => $previous,
                'difference' => $difference,
                'percentage' => $percentage,
            ],
            'products' => Product::query()->orderBy('name')->limit(200)->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'filters' => $request->only(['product_id', 'supplier_id', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', ProductCostHistory::class);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Produk', 'Supplier', 'Receipt', 'Metode', 'Qty Sebelum', 'Qty Masuk', 'Qty Setelah', 'HPP Sebelum', 'Incoming Cost', 'Landed Cost', 'HPP Setelah', 'Tanggal']);

            $this->query($request)->chunk(200, function ($histories) use ($handle): void {
                foreach ($histories as $history) {
                    fputcsv($handle, [
                        $history->product?->name,
                        $history->supplier?->name,
                        $history->goodsReceipt?->number,
                        $history->method,
                        $history->qty_before,
                        $history->qty_incoming,
                        $history->qty_after,
                        $history->hpp_before,
                        $history->incoming_cost,
                        $history->landed_cost_allocated,
                        $history->hpp_after,
                        optional($history->effective_at)->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, 'hpp-history.csv');
    }

    private function query(Request $request): mixed
    {
        return ProductCostHistory::query()
            ->with(['product', 'supplier', 'goodsReceipt'])
            ->when($request->integer('product_id') > 0, fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->integer('supplier_id') > 0, fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('effective_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('effective_at', '<=', $request->query('date_to')))
            ->latest('effective_at')
            ->latest('id');
    }
}
