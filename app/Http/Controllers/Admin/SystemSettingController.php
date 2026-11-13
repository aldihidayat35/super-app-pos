<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGeneralSettingsRequest;
use App\Models\DocumentSequence;
use App\Models\NotificationLog;
use App\Models\StockAdjustmentAccess;
use App\Models\StockDirectAdjustment;
use App\Models\StockDirectAdjustmentItem;
use App\Models\SystemSetting;
use App\Models\WorkLocation;
use App\Services\Notifications\WhatsappConnectionService;
use App\Services\Organization\DocumentNumberService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorize('view', SystemSetting::class);

        // 1. General settings
        $settings = $this->generalSettings();

        // 2. Printer settings
        $printerSettings = SystemSetting::getPrinterSettings();
        $companyName = SystemSetting::getCompanyName();
        $companyLogo = SystemSetting::getCompanyLogo();

        // 3. Document sequences
        foreach (DocumentNumberService::DEFAULT_PREFIXES as $type => $prefix) {
            DocumentSequence::query()->firstOrCreate(
                [
                    'document_type' => $type,
                    'location_type' => null,
                    'location_id' => null,
                    'scope_key' => 'global',
                    'year' => (int) now()->format('Y'),
                ],
                [
                    'prefix' => $prefix,
                    'next_number' => 1,
                    'padding' => 5,
                    'reset_yearly' => true,
                    'format' => '{prefix}/{location}/{year}/{sequence}',
                ],
            );
        }
        $sequences = DocumentSequence::query()
            ->where('scope_key', 'global')
            ->where('year', (int) now()->format('Y'))
            ->orderBy('document_type')
            ->get();
        $numberService = app(DocumentNumberService::class);

        // 4. Stock Adjustment Access
        $stockLocations = WorkLocation::query()->whereIn('type', ['warehouse', 'branch'])->where('is_active', true)->with('users')->orderBy('type')->orderBy('name')->get();
        $stockAccesses = StockAdjustmentAccess::query()->with(['workLocation', 'histories' => fn ($query) => $query->latest('occurred_at')->limit(5)])->get()->keyBy('work_location_id');
        $stockActiveCount = $stockAccesses->filter->isCurrentlyActive()->count();
        $stockPendingCount = StockDirectAdjustment::query()->where('status', 'pending_approval')->count();
        $stockTotalDifferenceQty = StockDirectAdjustmentItem::query()->whereHas('adjustment', fn ($query) => $query->where('status', 'completed'))->sum('difference_qty');
        $stockTotalDifferenceValue = StockDirectAdjustmentItem::query()->whereHas('adjustment', fn ($query) => $query->where('status', 'completed'))->sum('difference_value');

        // 5. WhatsApp Connection
        $whatsappData = [
            'connection' => null,
            'configured' => false,
            'messages' => collect(),
        ];
        if ($request->user()?->can('whatsapp_connection.view')) {
            try {
                $waService = app(WhatsappConnectionService::class);
                $whatsappData = $waService->refresh($request->user());
                $whatsappData['messages'] = NotificationLog::query()
                    ->where('channel', NotificationChannelType::WHATSAPP)
                    ->latest('sent_at')
                    ->limit(5)
                    ->get();
            } catch (Throwable) {
                // Safe fallback if not configured
            }
        }

        return view('admin.settings.general', [
            'settings' => $settings,
            'printerSettings' => $printerSettings,
            'companyName' => $companyName,
            'companyLogo' => $companyLogo,
            'sequences' => $sequences,
            'numberService' => $numberService,
            'stockLocations' => $stockLocations,
            'stockAccesses' => $stockAccesses,
            'stockActiveCount' => $stockActiveCount,
            'stockPendingCount' => $stockPendingCount,
            'stockTotalDifferenceQty' => $stockTotalDifferenceQty,
            'stockTotalDifferenceValue' => $stockTotalDifferenceValue,
            'whatsappData' => $whatsappData,
        ]);
    }

    public function update(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')?->store('logos', 'public');
        }

        unset($data['logo']);

        DB::transaction(function () use ($request, $data): void {
            foreach ($data as $key => $value) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'group' => 'general'],
                );
            }

            activity()->causedBy($request->user())->log('admin.settings.general_updated');
        });

        return redirect()->to(route('admin.settings.general.edit').'#tab_general')->with('notification', [
            'type' => 'success',
            'message' => 'Pengaturan umum berhasil disimpan.',
        ]);
    }

    /** @return array<string, mixed> */
    private function generalSettings(): array
    {
        $defaults = [
            'company_name' => 'GudangToko',
            'company_address' => null,
            'company_phone' => null,
            'company_email' => null,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'upload_limit_mb' => 2,
            'default_minimum_margin_percent' => '10',
            'overpricing_tolerance_percent' => '20',
            'invoice_template' => 'default',
            'receipt_template' => 'default',
            'logo_path' => null,
        ];

        $stored = SystemSetting::query()
            ->where('group', 'general')
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->value])
            ->all();

        return array_merge($defaults, $stored);
    }
}
