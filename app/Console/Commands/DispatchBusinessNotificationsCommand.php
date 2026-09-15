<?php

namespace App\Console\Commands;

use App\Enums\B2bOrderStatus;
use App\Enums\ReceivableStatus;
use App\Models\B2bOrder;
use App\Models\Receivable;
use App\Models\Stock;
use App\Services\Notifications\BusinessNotificationService;
use App\Support\CurrencyFormatter;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchBusinessNotificationsCommand extends Command
{
    protected $signature = 'notifications:business {--owner-report : Kirim laporan owner malam ini}';

    protected $description = 'Memeriksa stok, piutang, order tertunda, dan laporan owner untuk notifikasi WhatsApp.';

    public function handle(BusinessNotificationService $notifications): int
    {
        if ($this->option('owner-report')) {
            $this->info('Laporan owner diantrikan ke '.$notifications->ownerReport('nightly').' penerima.');

            return self::SUCCESS;
        }

        $this->criticalStocks($notifications);
        $this->dueReceivables($notifications);
        $this->pendingOrders($notifications);

        return self::SUCCESS;
    }

    private function criticalStocks(BusinessNotificationService $notifications): void
    {
        $rows = Stock::query()->join('products', 'products.id', '=', 'stocks.product_id')
            ->join('work_locations', 'work_locations.id', '=', 'stocks.work_location_id')
            ->selectRaw('stocks.product_id, stocks.work_location_id, products.sku, products.name, products.minimum_stock, work_locations.name as location_name, SUM(stocks.quantity_on_hand - stocks.quantity_reserved - stocks.quantity_damaged) as available')
            ->groupBy('stocks.product_id', 'stocks.work_location_id', 'products.sku', 'products.name', 'products.minimum_stock', 'work_locations.name')
            ->havingRaw('SUM(stocks.quantity_on_hand - stocks.quantity_reserved - stocks.quantity_damaged) <= products.minimum_stock')
            ->orderBy('stocks.work_location_id')
            ->orderBy('stocks.product_id')
            ->get();

        foreach ($rows as $row) {
            $productId = (int) $row->getAttribute('product_id');
            $locationId = (int) $row->getAttribute('work_location_id');
            $message = $row->getAttribute('sku').' - '.$row->getAttribute('name')
                ."\nLokasi: ".$row->getAttribute('location_name')
                ."\nTersedia: ".$row->getAttribute('available')
                ."\nBatas minimum: ".$row->getAttribute('minimum_stock');
            $notifications->send('critical_stock', 'Stok Perlu Direstok', $message, $locationId, route('warehouse.stocks.index', ['work_location_id' => $locationId, 'status' => 'critical']), $productId);
        }
    }

    private function dueReceivables(BusinessNotificationService $notifications): void
    {
        Receivable::query()->with('customer')->whereIn('status', [ReceivableStatus::OPEN, ReceivableStatus::PARTIAL, ReceivableStatus::OVERDUE])
            ->where('outstanding_amount', '>', 0)->whereDate('due_date', '<=', now('Asia/Jakarta')->addDay())->chunkById(100, function ($rows) use ($notifications): void {
                foreach ($rows as $receivable) {
                    $dueDate = $receivable->getAttribute('due_date');
                    $dueLabel = $dueDate instanceof CarbonInterface ? $dueDate->format('d/m/Y') : (string) $dueDate;
                    $customerName = $receivable->customer?->getAttribute('name') ?? '-';
                    $notifications->send('receivable_due', 'Piutang Jatuh Tempo', "{$receivable->number} - {$customerName}\nJatuh tempo: {$dueLabel}\nSisa: ".CurrencyFormatter::rupiah((string) $receivable->getAttribute('outstanding_amount')), $receivable->work_location_id, route('receivables.customers.show', $receivable->customer_id), $receivable->id);
                }
            });
    }

    private function pendingOrders(BusinessNotificationService $notifications): void
    {
        B2bOrder::query()->with('customer')->whereIn('status', [B2bOrderStatus::PENDING_CONFIRMATION, B2bOrderStatus::WAREHOUSE_VALIDATION])
            ->where('created_at', '<=', now('Asia/Jakarta')->subDay())->chunkById(100, function ($orders) use ($notifications): void {
                foreach ($orders as $order) {
                    $locationId = DB::table('stock_reservations')->where('b2b_order_id', $order->id)->value('work_location_id');
                    $customerName = $order->customer?->getAttribute('name') ?? '-';
                    $notifications->send('b2b_pending_order', 'Order B2B Belum Diproses', "{$order->number} - {$customerName}\nDibuat: {$order->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i')}", $locationId ? (int) $locationId : null, route('warehouse.b2b-orders.review', $order), $order->id);
                }
            });
    }
}
