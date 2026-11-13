<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PriceHistoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('prices.view'), 403);

        return view('pricing.history.index', [
            'histories' => $this->query($request)->paginate(20)->withQueryString(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)->get(),
            'users' => User::query()->orderBy('name')->limit(500)->get(),
            'locations' => WorkLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['product_id', 'work_location_id', 'user_id', 'trend', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('prices.view'), 403);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Produk', 'SKU', 'Lokasi', 'Harga Lama', 'Harga Baru', 'Status', 'Selisih', 'HPP Snapshot', 'Sumber', 'Pengubah', 'Waktu', 'Catatan']);
            $this->query($request)->each(function (PriceHistory $history) use ($handle): void {
                fputcsv($handle, [
                    $history->product?->name,
                    $history->product?->sku,
                    $history->workLocation ? $history->workLocation->name : 'Global',
                    $history->old_price,
                    $history->new_price,
                    $history->trendLabel(),
                    $history->priceDifference(),
                    $history->hpp_snapshot,
                    $history->source,
                    $history->changer?->name,
                    $history->created_at?->format('Y-m-d H:i'),
                    $history->reason,
                ]);
            });
            fclose($handle);
        }, 'price-history.csv');
    }

    private function query(Request $request): mixed
    {
        return PriceHistory::query()
            ->with(['product', 'changer', 'workLocation'])
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('work_location_id'), fn ($query) => $query->where('work_location_id', $request->integer('work_location_id')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('changed_by', $request->integer('user_id')))
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->query('channel')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->query('date_to')))
            ->when($request->filled('trend'), function ($query) use ($request) {
                $trend = $request->query('trend');
                if ($trend === 'up') {
                    $query->whereRaw('new_price > old_price AND old_price > 0');
                } elseif ($trend === 'down') {
                    $query->whereRaw('new_price < old_price AND old_price > 0');
                } elseif ($trend === 'initial') {
                    $query->where(fn ($q) => $q->whereNull('old_price')->orWhere('old_price', '<=', 0));
                }
            })
            ->latest('created_at');
    }
}
