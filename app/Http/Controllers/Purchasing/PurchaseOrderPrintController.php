<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderPrintController extends Controller
{
    public function __invoke(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('print', $purchaseOrder);
        $purchaseOrder->load(['warehouse', 'supplier', 'items.product', 'creator', 'approver']);

        if ($request->query('download') === 'pdf') {
            return Pdf::loadView('purchasing.purchase-orders.print', compact('purchaseOrder'))
                ->download($purchaseOrder->number.'.pdf');
        }

        return response()->view('purchasing.purchase-orders.print', compact('purchaseOrder'));
    }
}
