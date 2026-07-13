<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewPartyImportRequest;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Party\PartyImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartyImportController extends Controller
{
    public function index(string $type): View
    {
        $this->authorizeImport($type);

        return view('admin.parties.import', ['type' => $type, 'preview' => session("{$type}_import_preview"), 'result' => session("{$type}_import_result")]);
    }

    public function preview(PreviewPartyImportRequest $request, string $type, PartyImportService $service): RedirectResponse
    {
        $preview = $service->preview($type, $request->file('file'));

        return redirect()->route('admin.parties.import.index', $type)->with("{$type}_import_preview", $preview);
    }

    public function commit(Request $request, string $type, PartyImportService $service): RedirectResponse
    {
        $this->authorizeImport($type);
        $preview = session("{$type}_import_preview");
        if (! is_array($preview) || filled($preview['errors'] ?? [])) {
            return back()->with('notification', ['type' => 'danger', 'message' => 'Import belum dapat diproses karena masih ada error validasi.']);
        }
        $result = $service->commit($type, $preview['rows'] ?? []);
        activity()->causedBy($request->user())->log("{$type}.import.committed");

        return redirect()->route('admin.parties.import.index', $type)->with("{$type}_import_result", $result)->with('notification', ['type' => 'success', 'message' => 'Import berhasil diproses.']);
    }

    public function template(string $type): StreamedResponse
    {
        $this->authorizeImport($type);

        return response()->streamDownload(function () use ($type): void {
            $handle = fopen('php://output', 'w');
            if ($type === 'suppliers') {
                fputcsv($handle, ['code', 'name', 'contact_name', 'whatsapp_number', 'email', 'city', 'address', 'payment_term_days']);
                fputcsv($handle, ['SUP-CONTOH', 'Supplier Contoh', 'Budi', '081234567890', 'supplier@example.test', 'Jakarta', 'Alamat Supplier', '30']);
            } else {
                fputcsv($handle, ['type', 'code', 'business_name', 'owner_name', 'pic_name', 'whatsapp_number', 'email', 'city', 'business_address', 'price_category', 'minimum_order', 'payment_term_days', 'credit_limit', 'verification_status', 'account_status']);
                fputcsv($handle, ['b2b', 'CUS-CONTOH', 'Pelanggan Contoh', 'Sari', 'Sari', '081234567891', 'customer@example.test', 'Jakarta', 'Alamat Customer', 'grosir', '500000', '14', '5000000', 'active', 'active']);
            }
            fclose($handle);
        }, "template-{$type}.csv");
    }

    private function authorizeImport(string $type): void
    {
        abort_unless(in_array($type, ['suppliers', 'customers'], true), 404);
        $this->authorize('import', $type === 'suppliers' ? Supplier::class : Customer::class);
    }
}
