<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PriceHistoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('prices.view'), 403);

        return view('pricing.history.index', [
            'histories' => $this->query($request)->paginate(15)->withQueryString(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(500)->get(),
            'users' => User::query()->orderBy('name')->limit(500)->get(),
            'filters' => $request->only(['product_id', 'channel', 'user_id', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('prices.view'), 403);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Produk', 'Channel', 'Ring', 'Old', 'New', 'HPP', 'Minimum', 'User', 'Waktu', 'Alasan']);
            $this->query($request)->each(function (PriceHistory $history) use ($handle): void {
                fputcsv($handle, [$history->product?->sku, $history->channel, $history->price_ring, $history->old_price, $history->new_price, $history->hpp_snapshot, $history->minimum_price_snapshot, $history->changer?->name, $history->created_at?->format('Y-m-d H:i'), $history->reason]);
            });
            fclose($handle);
        }, 'price-history.csv');
    }

    private function query(Request $request): mixed
    {
        return PriceHistory::query()->with(['product', 'changer'])
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->query('channel')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('changed_by', $request->integer('user_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->query('date_to')))
            ->latest('created_at');
    }
}
