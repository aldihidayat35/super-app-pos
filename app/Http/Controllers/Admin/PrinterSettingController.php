<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePrinterSettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PrinterSettingController extends Controller
{
    public function edit(): View
    {
        $this->authorize('view', SystemSetting::class);

        return view('admin.settings.printer', [
            'settings' => SystemSetting::getPrinterSettings(),
            'companyName' => SystemSetting::getCompanyName(),
            'companyLogo' => SystemSetting::getCompanyLogo(),
        ]);
    }

    public function update(UpdatePrinterSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data): void {
            foreach ($data as $key => $value) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'group' => 'printer'],
                );
            }

            activity()->causedBy($request->user())->log('admin.settings.printer_updated');
        });

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Pengaturan printer dan format cetak berhasil disimpan.',
        ]);
    }

    public function testPrint(string $type): View
    {
        $this->authorize('view', SystemSetting::class);

        $settings = SystemSetting::getPrinterSettings();
        $companyName = SystemSetting::getCompanyName();

        if ($type === 'dotmatrix') {
            return view('admin.settings.test-print-dotmatrix', [
                'settings' => $settings,
                'companyName' => $companyName,
            ]);
        }

        return view('admin.settings.test-print-thermal', [
            'settings' => $settings,
            'companyName' => $companyName,
        ]);
    }
}
